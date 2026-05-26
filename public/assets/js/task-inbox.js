// public/assets/js/task-inbox.js

(function () {
  function normalizeTaskValue(value) {
    return (value || '')
      .toString()
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  function initTaskInboxes(root) {
    const scope = root || document;

    scope.querySelectorAll('[data-task-inbox]').forEach((inbox) => {
      if (inbox.dataset.taskInboxInitialized === '1') {
        return;
      }

      inbox.dataset.taskInboxInitialized = '1';

      const table = inbox.querySelector('[data-task-table]');
      const tbody = table ? table.querySelector('tbody') : null;
      const searchInput = inbox.querySelector('[data-task-search]');
      const countLabel = inbox.querySelector('[data-task-count]');

      if (!table || !tbody) {
        return;
      }

      const headers = Array.from(table.querySelectorAll('.task-sortable'));

      let rows = Array.from(tbody.querySelectorAll('[data-task-row]'));
      let currentSortIndex = null;
      let currentSortDirection = 'asc';

      function updateRows() {
        rows = Array.from(tbody.querySelectorAll('[data-task-row]'));
      }

      function updateCount() {
        if (!countLabel) {
          return;
        }

        updateRows();

        const count = rows.filter((row) => {
          return !row.classList.contains('task-hidden');
        }).length;

        countLabel.textContent =
          count + ' tâche' + (count > 1 ? 's' : '') +
          ' affichée' + (count > 1 ? 's' : '');
      }

      function applySearch() {
        const query = normalizeTaskValue(searchInput ? searchInput.value : '');

        updateRows();

        rows.forEach((row) => {
          const rowText = normalizeTaskValue(row.textContent);
          const shouldHide = query !== '' && !rowText.includes(query);

          row.classList.toggle('task-hidden', shouldHide);
        });

        updateCount();
      }

      function clearSortIndicators() {
        headers.forEach((header) => {
          header.classList.remove('is-sorted');

          const indicator = header.querySelector('.sort-indicator');

          if (indicator) {
            indicator.textContent = '↕';
          }
        });
      }

      function getCellValue(row, columnIndex, type) {
        const cell = row.children[columnIndex];

        if (!cell) {
          return type === 'number' ? 0 : '';
        }

        const rawValue = cell.dataset.sortValue || cell.textContent || '';

        if (type === 'number') {
          return parseInt(rawValue, 10) || 0;
        }

        return normalizeTaskValue(rawValue);
      }

      function sortRows(columnIndex, type, header) {
        if (currentSortIndex === columnIndex) {
          currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
          currentSortIndex = columnIndex;
          currentSortDirection = 'asc';
        }

        updateRows();

        rows.sort((a, b) => {
          const valueA = getCellValue(a, columnIndex, type);
          const valueB = getCellValue(b, columnIndex, type);

          if (valueA < valueB) {
            return currentSortDirection === 'asc' ? -1 : 1;
          }

          if (valueA > valueB) {
            return currentSortDirection === 'asc' ? 1 : -1;
          }

          return 0;
        });

        rows.forEach((row) => {
          tbody.appendChild(row);
        });

        clearSortIndicators();

        header.classList.add('is-sorted');

        const indicator = header.querySelector('.sort-indicator');

        if (indicator) {
          indicator.textContent = currentSortDirection === 'asc' ? '↑' : '↓';
        }

        applySearch();
      }

      headers.forEach((header) => {
        header.addEventListener('click', () => {
          const columnIndex = parseInt(header.dataset.columnIndex, 10);
          const type = header.dataset.sortType || 'text';

          if (Number.isNaN(columnIndex)) {
            return;
          }

          sortRows(columnIndex, type, header);
        });
      });

      if (searchInput) {
        searchInput.addEventListener('input', applySearch);
      }

      updateCount();
    });
  }

  function setBadgeCount(count) {
    const desktopBadge = document.getElementById('taskInboxCount');
    const mobileBadge = document.getElementById('taskInboxCountMobile');

    [desktopBadge, mobileBadge].forEach((badge) => {
      if (!badge) {
        return;
      }

      if (count > 0) {
        badge.textContent = count;
        badge.classList.remove('d-none');
      } else {
        badge.classList.add('d-none');
      }
    });
  }

  function getCountUrl(taskButtons) {
    const btn = taskButtons.find((button) => {
      return button.getAttribute('data-count-url');
    });

    return btn ? btn.getAttribute('data-count-url') : null;
  }

  function refreshTaskCount(taskButtons) {
    const countUrl = getCountUrl(taskButtons);

    if (!countUrl) {
      return;
    }

    fetch(countUrl, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }

        return response.json();
      })
      .then((data) => {
        setBadgeCount(parseInt(data.count ?? 0, 10));
      })
      .catch(() => {
        // Erreur silencieuse : le badge n'est pas critique.
      });
  }

  function initResizableTaskModal() {
    const modalContent = document.querySelector('#taskInboxModal .task-inbox-modal-content');
    const modalDialog = document.querySelector('#taskInboxModal .task-inbox-modal-dialog');
    const handle = document.querySelector('#taskInboxModal .task-modal-resize-handle');

    if (!modalContent || !modalDialog || !handle) {
      return;
    }

    if (handle.dataset.resizeInitialized === '1') {
      return;
    }

    handle.dataset.resizeInitialized = '1';

    let isResizing = false;
    let startX = 0;
    let startY = 0;
    let startWidth = 0;
    let startHeight = 0;

    const minWidth = 720;
    const minHeight = 420;

    function getMaxWidth() {
      return Math.floor(window.innerWidth * 0.95);
    }

    function getMaxHeight() {
      return Math.floor(window.innerHeight * 0.90);
    }

    function startResize(event) {
      event.preventDefault();

      const pointer = event.touches ? event.touches[0] : event;

      isResizing = true;
      startX = pointer.clientX;
      startY = pointer.clientY;
      startWidth = modalContent.offsetWidth;
      startHeight = modalContent.offsetHeight;

      document.body.style.userSelect = 'none';
      document.body.style.cursor = 'nwse-resize';
    }

    function moveResize(event) {
      if (!isResizing) {
        return;
      }

      const pointer = event.touches ? event.touches[0] : event;

      const deltaX = pointer.clientX - startX;
      const deltaY = pointer.clientY - startY;

      const nextWidth = Math.min(
        Math.max(startWidth + deltaX, minWidth),
        getMaxWidth()
      );

      const nextHeight = Math.min(
        Math.max(startHeight + deltaY, minHeight),
        getMaxHeight()
      );

      modalDialog.style.width = nextWidth + 'px';
      modalDialog.style.maxWidth = '95vw';

      modalContent.style.width = nextWidth + 'px';
      modalContent.style.height = nextHeight + 'px';
    }

    function stopResize() {
      if (!isResizing) {
        return;
      }

      isResizing = false;
      document.body.style.userSelect = '';
      document.body.style.cursor = '';
    }

    handle.addEventListener('mousedown', startResize);
    window.addEventListener('mousemove', moveResize);
    window.addEventListener('mouseup', stopResize);

    handle.addEventListener('touchstart', startResize, { passive: false });
    window.addEventListener('touchmove', moveResize, { passive: false });
    window.addEventListener('touchend', stopResize);
  }

  function initTaskInboxAjax() {
    const taskButtons = Array.from(document.querySelectorAll('[data-task-inbox-btn]'));
    const taskModal = document.getElementById('taskInboxModal');

    if (taskButtons.length === 0) {
      return;
    }

    refreshTaskCount(taskButtons);
    setInterval(() => refreshTaskCount(taskButtons), 30000);

    if (!taskModal) {
      return;
    }

    const content = document.getElementById('taskInboxContent');
    const loader = document.getElementById('taskInboxLoading');
    const errorBox = document.getElementById('taskInboxError');

    initResizableTaskModal();

    taskModal.addEventListener('show.bs.modal', (event) => {
      const trigger = event.relatedTarget;
      const url = trigger && trigger.getAttribute ? trigger.getAttribute('data-url') : null;

      const finalUrl = url || (taskButtons[0] ? taskButtons[0].getAttribute('data-url') : null);

      if (!finalUrl) {
        return;
      }

      if (content) {
        content.classList.add('d-none');
        content.innerHTML = '';
      }

      if (errorBox) {
        errorBox.classList.add('d-none');
      }

      if (loader) {
        loader.classList.remove('d-none');
      }

      fetch(finalUrl, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error('HTTP ' + response.status);
          }

          return response.text();
        })
        .then((html) => {
          if (loader) {
            loader.classList.add('d-none');
          }

          if (content) {
            content.innerHTML = html;
            content.classList.remove('d-none');

            initTaskInboxes(content);

            const rows = content.querySelectorAll('[data-task-row]');
            setBadgeCount(rows.length);
          }
        })
        .catch((error) => {
          console.error('Erreur chargement tâches :', error);

          if (loader) {
            loader.classList.add('d-none');
          }

          if (errorBox) {
            errorBox.classList.remove('d-none');
          }
        });
    });

    taskModal.addEventListener('hidden.bs.modal', () => {
      refreshTaskCount(taskButtons);
    });
  }

  window.initTaskInboxes = initTaskInboxes;

  window.addEventListener('DOMContentLoaded', () => {
    initTaskInboxes(document);
    initTaskInboxAjax();
  });
})();