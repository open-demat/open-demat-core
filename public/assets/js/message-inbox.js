(() => {
  const refresh = async () => {
    const button = document.querySelector('[data-message-count-url]');
    const badge = document.getElementById('messageInboxCount');
    if (!button || !badge) return;
    try {
      const countUrl = new URL(button.dataset.messageCountUrl, window.location.href);
      if (countUrl.origin !== window.location.origin) return;
      const response = await fetch(countUrl.href, {
        credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store'
      });
      if (!response.ok) return;
      const { count } = await response.json();
      if (!Number.isInteger(count) || count < 0) return;
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.classList.toggle('d-none', count === 0);
      button.setAttribute('aria-label', `Messages, ${count} non lus`);
    } catch (_) { /* The Messages link remains usable if the counter is unavailable. */ }
  };
  document.addEventListener('DOMContentLoaded', refresh);
  window.addEventListener('focus', refresh);
})();
