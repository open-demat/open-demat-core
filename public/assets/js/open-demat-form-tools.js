// public/js/open-demat-form-tools.js
window.OpenDematFormTools = (() => {
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char]));
  const qs = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  async function fetchProfile(profileUrl) {
    const res = await fetch(profileUrl, {
      method: 'GET',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    });

    if (!res.ok) {
      throw new Error('Impossible de charger le profil');
    }

    return await res.json();
  }

  function setIfEmpty(selector, value, root = document) {
    const el = qs(selector, root);
    if (!el) return;

    const current = (el.value ?? '').toString().trim();
    if (current !== '') return;

    el.value = (value ?? '').toString();
  }

  async function prefillFields({
    profileUrl = '/profile/me/ajax',
    mapping = {},
    root = document,
    transform = null,
  }) {
    const data = await fetchProfile(profileUrl);
    const user = data.user || {};
    const source = typeof transform === 'function' ? transform(user, data) : user;

    Object.entries(mapping).forEach(([selector, sourceKey]) => {
      setIfEmpty(selector, source[sourceKey], root);
    });

    return data;
  }

  function initProfileDocumentsPicker(rootEl) {
    if (!rootEl) return;

    const profileUrl = rootEl.dataset.profileUrl || '/profile/me/ajax';
    const maxAttachments = parseInt(rootEl.dataset.maxAttachments || '10', 10);
    const inputName = rootEl.dataset.inputName || 'profile_documents[]';

    const elHead = qs('.profile-docs-head', rootEl);
    const elBody = qs('[data-role="body"]', rootEl);
    const elSearch = qs('[data-role="search"]', rootEl);
    const elFilter = qs('[data-role="filter-cat"]', rootEl);
    const elStatus = qs('[data-role="status"]', rootEl);
    const elList = qs('[data-role="list"]', rootEl);
    const elHidden = qs('[data-role="hidden"]', rootEl);
    const elCount = qs('[data-role="selected-count"]', rootEl);
    const btnLoad = qs('[data-action="load"]', rootEl);
    const btnClear = qs('[data-action="clear"]', rootEl);

    let loaded = false;
    let docs = [];

    const showStatus = (msg) => {
      if (!msg) {
        elStatus.style.display = 'none';
        elStatus.textContent = '';
        return;
      }
      elStatus.style.display = 'block';
      elStatus.textContent = msg;
    };

    const getSelectedIds = () =>
      qsa(`input[name="${escapeHtml(inputName)}"]`, elHidden).map(i => Number(i.value));

    const setSelectedIds = (ids) => {
      const unique = Array.from(new Set(ids.map(Number).filter(n => Number.isFinite(n) && n > 0)));
      elHidden.innerHTML = unique.map(id => `<input type="hidden" name="${escapeHtml(inputName)}" value="${id}">`).join('');
      elCount.textContent = String(unique.length);
      btnClear.disabled = unique.length === 0;
    };

    const buildCategories = () => {
      const cats = Array.from(new Set(docs.map(d => (d.category || '').trim()).filter(Boolean))).sort((a, b) => a.localeCompare(b));
      elFilter.innerHTML = '<option value="">Toutes catégories</option>' + cats.map(c => `<option value="${encodeURIComponent(c)}">${escapeHtml(c)}</option>`).join('');
    };

    const matchFilter = (doc) => {
      const q = (elSearch.value || '').trim().toLowerCase();
      const cat = decodeURIComponent(elFilter.value || '');

      if (cat && (doc.category || '') !== cat) return false;
      if (!q) return true;

      const hay = [
        doc.label,
        doc.category,
        doc.document?.originalName,
        doc.document?.mimeType
      ].map(v => (v ?? '').toString().toLowerCase()).join(' ');

      return hay.includes(q);
    };

    const render = () => {
      if (!loaded) {
        elList.innerHTML = `<div class="list-group-item text-muted small">Clique sur <strong>“Charger”</strong> pour afficher tes documents.</div>`;
        return;
      }

      const selected = new Set(getSelectedIds());
      const filtered = docs.filter(matchFilter);

      if (!docs.length) {
        elList.innerHTML = `<div class="list-group-item text-muted small">Aucun fichier dans ton profil.</div>`;
        return;
      }

      if (!filtered.length) {
        elList.innerHTML = `<div class="list-group-item text-muted small">Aucun fichier ne correspond à ta recherche.</div>`;
        return;
      }

      elList.innerHTML = filtered.map(doc => {
        const id = Number(doc.id);
        const checked = selected.has(id) ? 'checked' : '';
        const name = doc.document?.originalName || 'Document';
        const cat = doc.category || '';
        const label = doc.label || '';

        return `
          <label class="list-group-item">
            <div class="d-flex gap-3 align-items-start">
              <input class="form-check-input mt-1" type="checkbox" data-role="doc-checkbox" data-id="${id}" ${checked}>
              <div class="min-w-0">
                <div class="fw-semibold">${escapeHtml(name)}</div>
                <div class="text-muted small">
                  ${cat ? `<span class="badge text-bg-light border me-2">${escapeHtml(cat)}</span>` : ''}
                  ${escapeHtml(label)}
                </div>
              </div>
            </div>
          </label>
        `;
      }).join('');
    };

    const load = async () => {
      showStatus('Chargement…');

      try {
        const data = await fetchProfile(profileUrl);
        docs = Array.isArray(data.documents) ? data.documents : [];
        loaded = true;
        elSearch.disabled = false;
        elFilter.disabled = false;
        buildCategories();
        showStatus('');
        render();
      } catch (e) {
        docs = [];
        loaded = true;
        showStatus('Impossible de charger vos fichiers.');
        render();
      }
    };

    const setOpen = (open) => {
      elBody.style.display = open ? '' : 'none';
      rootEl.dataset.open = open ? '1' : '0';
      elHead.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    elHead?.addEventListener('click', (e) => {
      if (e.target.closest('button')) return;
      setOpen(rootEl.dataset.open !== '1');
    });

    btnLoad?.addEventListener('click', async () => {
      setOpen(true);
      if (!loaded) await load();
      else render();
    });

    btnClear?.addEventListener('click', () => {
      setSelectedIds([]);
      render();
    });

    elSearch?.addEventListener('input', render);
    elFilter?.addEventListener('change', render);

    elList?.addEventListener('change', (e) => {
      const input = e.target;
      if (!(input instanceof HTMLInputElement) || input.dataset.role !== 'doc-checkbox') return;

      const id = Number(input.dataset.id);
      const selected = new Set(getSelectedIds());

      if (input.checked) {
        if (selected.size >= maxAttachments) {
          input.checked = false;
          showStatus(`Maximum ${maxAttachments} pièces jointes.`);
          return;
        }
        selected.add(id);
      } else {
        selected.delete(id);
      }

      showStatus('');
      setSelectedIds(Array.from(selected));
    });

    setSelectedIds([]);
  }

  function autocompleteCommuneFromCp({
    cpSelector,
    citySelector,
    endpoint,
    cityChoiceSelector = null,
    root = document,
  }) {
    const cpInput = qs(cpSelector, root);
    const cityInput = qs(citySelector, root);
    const cityChoice = cityChoiceSelector ? qs(cityChoiceSelector, root) : null;

    if (!cpInput || !cityInput) {
      console.warn('[autocompleteCommuneFromCp] champ introuvable', {
        cpSelector,
        citySelector
      });
      return;
    }

    let timer = null;

    const resetChoice = () => {
      if (!cityChoice) return;
      cityChoice.innerHTML = '<option value="">Choisir une ville</option>';
      cityChoice.classList.add('d-none');
      cityChoice.disabled = true;
    };

    const showChoices = (villes) => {
      if (!cityChoice) {
        cityInput.value = '';
        return;
      }

      cityChoice.innerHTML =
        '<option value="">Choisir une ville</option>' +
        villes.map(v => `<option value="${escapeHtml(v.nom)}">${escapeHtml(v.nom)}</option>`).join('');

      cityChoice.disabled = false;
      cityChoice.classList.remove('d-none');
      cityInput.value = '';
    };

    const fetchCommune = async () => {
      const cp = (cpInput.value || '').trim();

      if (!/^\d{5}$/.test(cp)) {
        cityInput.value = '';
        resetChoice();
        return;
      }

      try {
        const url = endpoint + '?codePostal=' + encodeURIComponent(cp);

        const response = await fetch(url, {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          credentials: 'same-origin'
        });

        const contentType = response.headers.get('content-type') || '';

        if (!response.ok) {
          cityInput.value = '';
          resetChoice();
          return;
        }

        if (!contentType.includes('application/json')) {
          cityInput.value = '';
          resetChoice();
          return;
        }

        const data = await response.json();

        if (!data || !data.ok) {
          cityInput.value = '';
          resetChoice();
          return;
        }

        if (data.mode === 'single' && data.ville) {
          cityInput.value = data.ville;
          resetChoice();
          return;
        }

        if (data.mode === 'multiple' && Array.isArray(data.villes) && data.villes.length > 0) {
          showChoices(data.villes);
          return;
        }

        cityInput.value = '';
        resetChoice();
      } catch (e) {
        console.error('[autocompleteCommuneFromCp] erreur', e);
        cityInput.value = '';
        resetChoice();
      }
    };

    cpInput.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(fetchCommune, 250);
    });

    cpInput.addEventListener('blur', fetchCommune);

    if (cityChoice) {
      cityChoice.addEventListener('change', () => {
        cityInput.value = cityChoice.value || '';
      });
    }

    resetChoice();
  }
  
  return {
    prefillFields,
    initProfileDocumentsPicker,
    autocompleteCommuneFromCp,
  };
})();
window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.profile-docs-picker').forEach(picker => {
    if (picker.dataset.initialized === 'true') return;
    picker.dataset.initialized = 'true';
    window.OpenDematFormTools.initProfileDocumentsPicker(picker);
  });
});
