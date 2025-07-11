// resources/js/modules/dragdrop.js
import Sortable from 'sortablejs';

export function enableRowDragDrop(tableId, onDropCallback) {
  const table = document.getElementById(tableId);
  if (!table) return;

  const tbody = table.querySelector('tbody');
  if (!tbody) return;

  new Sortable(tbody, {
    animation: 150,
    handle: 'td',
    ghostClass: 'table-warning',
    onEnd: function () {
      const ids = Array.from(tbody.querySelectorAll('tr')).map(tr => tr.dataset.id);
      if (typeof onDropCallback === 'function') {
        onDropCallback(ids);
      }
    }
  });
}
