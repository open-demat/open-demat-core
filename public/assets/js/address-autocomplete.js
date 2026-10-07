(function (window, document) {
  'use strict';

  const DEFAULTS = {
    apiBaseUrl: 'https://data.geopf.fr/geocodage',
    minLength: 3,
    limit: 8,
    debounceMs: 250,
    autocomplete: true,
    previewPrefix: 'Adresse complète : '
  };

  function debounce(fn, delay) {
    let timer = null;

    return function (...args) {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => fn.apply(this, args), delay);
    };
  }

  function qs(selector, root = document) {
    if (!selector) {
      return null;
    }

    return root.querySelector(selector);
  }

  function normalize(value) {
    return String(value || '').trim();
  }

  function cleanPostalCode(value) {
    return normalize(value).replace(/[^\d]/g, '').slice(0, 5);
  }

  function buildApiUrl(config, endpoint, params) {
    const baseUrl = config.apiBaseUrl.replace(/\/$/, '');
    const path = endpoint.replace(/^\//, '');

    return `${baseUrl}/${path}?${params.toString()}`;
  }

  function createElement(tag, attrs = {}, text = '') {
    const el = document.createElement(tag);

    Object.entries(attrs).forEach(([key, value]) => {
      if (value === null || value === undefined) {
        return;
      }

      if (key === 'className') {
        el.className = value;
        return;
      }

      el.setAttribute(key, String(value));
    });

    if (text !== '') {
      el.textContent = text;
    }

    return el;
  }

  function buildFullAddress(address, postalCode, city) {
    const addressPart = normalize(address);
    const postalCodePart = normalize(postalCode);
    const cityPart = normalize(city);

    const parts = [];

    if (addressPart !== '') {
      parts.push(addressPart);
    }

    const cpCity = [postalCodePart, cityPart]
      .filter(Boolean)
      .join(' ');

    if (cpCity !== '') {
      parts.push(cpCity);
    }

    return parts.join(', ');
  }

  function clearSuggestions(container) {
    if (!container) {
      return;
    }

    container.innerHTML = '';
    container.classList.add('d-none');
  }

  function showSuggestions(container) {
    if (!container) {
      return;
    }

    container.classList.remove('d-none');
  }

  async function searchAddress(config, query, postalCode, city) {
    const params = new URLSearchParams();

    params.set('q', query);
    params.set('limit', String(config.limit));
    params.set('autocomplete', config.autocomplete ? '1' : '0');

    if (postalCode && /^\d{5}$/.test(postalCode)) {
      params.set('postcode', postalCode);
    }

    if (city) {
      params.set('city', city);
    }

    const url = buildApiUrl(config, 'search', params);

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        Accept: 'application/json'
      }
    });

    if (!response.ok) {
      throw new Error(`Erreur API géocodage : HTTP ${response.status}`);
    }

    return response.json();
  }

  async function reverseAddress(config, longitude, latitude) {
    const params = new URLSearchParams();

    params.set('lon', String(longitude));
    params.set('lat', String(latitude));

    const url = buildApiUrl(config, 'reverse', params);

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        Accept: 'application/json'
      }
    });

    if (!response.ok) {
      throw new Error(`Erreur API géocodage reverse : HTTP ${response.status}`);
    }

    return response.json();
  }

  function initAddressAutocomplete(options) {
    const config = Object.assign({}, DEFAULTS, options || {});

    const addressInput = qs(config.addressSelector);
    const postalCodeInput = qs(config.postalCodeSelector);
    const cityInput = qs(config.citySelector);
    const fullAddressInput = qs(config.fullAddressSelector);
    const suggestionsContainer = qs(config.suggestionsSelector);
    const preview = qs(config.previewSelector);

    if (!addressInput || !postalCodeInput || !cityInput || !fullAddressInput) {
      return;
    }

    function updatePreview(value) {
      if (!preview) {
        return;
      }

      const text = normalize(value);

      preview.textContent = config.previewPrefix + (text !== '' ? text : '—');
    }

    function syncFullAddress() {
      const fullAddress = buildFullAddress(
        addressInput.value,
        postalCodeInput.value,
        cityInput.value
      );

      fullAddressInput.value = fullAddress;
      updatePreview(fullAddress);

      fullAddressInput.dispatchEvent(new Event('input', { bubbles: true }));
      fullAddressInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function fillFromFeature(feature) {
      const properties = feature && feature.properties ? feature.properties : {};

      const label = normalize(properties.label);
      const name = normalize(properties.name);
      const postcode = cleanPostalCode(properties.postcode);
      const city = normalize(properties.city || properties.citycode);

      addressInput.value = name || label;
      postalCodeInput.value = postcode;
      cityInput.value = city;

      const fullAddress = label || buildFullAddress(name, postcode, city);

      fullAddressInput.value = fullAddress;
      updatePreview(fullAddress);

      fullAddressInput.dispatchEvent(new Event('input', { bubbles: true }));
      fullAddressInput.dispatchEvent(new Event('change', { bubbles: true }));

      addressInput.dispatchEvent(new Event('change', { bubbles: true }));
      postalCodeInput.dispatchEvent(new Event('change', { bubbles: true }));
      cityInput.dispatchEvent(new Event('change', { bubbles: true }));

      clearSuggestions(suggestionsContainer);
    }

    function renderSuggestions(features) {
      if (!suggestionsContainer) {
        return;
      }

      suggestionsContainer.innerHTML = '';

      if (!Array.isArray(features) || features.length === 0) {
        clearSuggestions(suggestionsContainer);
        return;
      }

      const list = createElement('div', {
        className: 'open-demat-address-suggestions-list',
        role: 'listbox'
      });

      features.forEach((feature, index) => {
        const properties = feature.properties || {};
        const label = normalize(properties.label);
        const context = normalize(properties.context);
        const type = normalize(properties.type);

        const btn = createElement('button', {
          type: 'button',
          className: 'open-demat-address-suggestion',
          role: 'option',
          'data-index': index,
          'data-type': type
        });

        const title = createElement('span', {
          className: 'open-demat-address-suggestion-title'
        }, label || 'Adresse sans libellé');

        btn.appendChild(title);

        if (context !== '') {
          const subtitle = createElement('span', {
            className: 'open-demat-address-suggestion-subtitle'
          }, context);

          btn.appendChild(subtitle);
        }

        btn.addEventListener('click', () => {
          fillFromFeature(feature);
        });

        list.appendChild(btn);
      });

      suggestionsContainer.appendChild(list);
      showSuggestions(suggestionsContainer);
    }

    const performSearch = debounce(async function () {
      const query = normalize(addressInput.value);
      const postalCode = cleanPostalCode(postalCodeInput.value);
      const city = normalize(cityInput.value);

      syncFullAddress();

      if (query.length < config.minLength) {
        clearSuggestions(suggestionsContainer);
        return;
      }

      try {
        const data = await searchAddress(config, query, postalCode, city);
        renderSuggestions(data.features || []);
      } catch (error) {
        console.error(error);
        clearSuggestions(suggestionsContainer);
      }
    }, config.debounceMs);

    addressInput.addEventListener('input', performSearch);

    addressInput.addEventListener('change', function () {
      syncFullAddress();
    });

    postalCodeInput.addEventListener('input', function () {
      postalCodeInput.value = cleanPostalCode(postalCodeInput.value);

      syncFullAddress();

      if (normalize(addressInput.value).length >= config.minLength) {
        performSearch();
      }
    });

    postalCodeInput.addEventListener('change', function () {
      postalCodeInput.value = cleanPostalCode(postalCodeInput.value);
      syncFullAddress();
    });

    cityInput.addEventListener('input', function () {
      syncFullAddress();

      if (normalize(addressInput.value).length >= config.minLength) {
        performSearch();
      }
    });

    cityInput.addEventListener('change', syncFullAddress);

    document.addEventListener('click', function (event) {
      if (!suggestionsContainer) {
        return;
      }

      const target = event.target;

      if (
        target === addressInput
        || target === postalCodeInput
        || target === cityInput
        || suggestionsContainer.contains(target)
      ) {
        return;
      }

      clearSuggestions(suggestionsContainer);
    });

    addressInput.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        clearSuggestions(suggestionsContainer);
      }
    });

    syncFullAddress();
  }

  window.OpenDematAddressAutocomplete = {
    init: initAddressAutocomplete,
    buildFullAddress: buildFullAddress,
    searchAddress: searchAddress,
    reverseAddress: reverseAddress
  };
})(window, document);
