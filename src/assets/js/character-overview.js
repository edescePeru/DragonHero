import { Modal, Tooltip } from 'bootstrap';

const root = document.querySelector('[data-character-overview]');

if (root) {
  root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => new Tooltip(element));

  const modalElement = document.getElementById('overview-action-panel');
  const modal = Modal.getOrCreateInstance(modalElement);
  const title = modalElement.querySelector('#overview-action-title');
  const details = modalElement.querySelector('[data-overview-panel-details]');
  const actions = modalElement.querySelector('[data-overview-panel-actions]');
  const error = root.querySelector('[data-overview-error]');

  root.querySelectorAll('[data-overview-open-panel]').forEach((button) => {
    button.addEventListener('click', () => {
      const template = document.getElementById(button.dataset.overviewOpenPanel);
      if (!template) return;
      const fragment = template.content.cloneNode(true);
      const sourceTitle = fragment.querySelector('[data-panel-title]');
      const sourceDetails = fragment.querySelector('[data-panel-details]');
      const sourceActions = fragment.querySelector('[data-panel-actions]');
      title.textContent = sourceTitle ? sourceTitle.textContent : 'Objeto';
      details.replaceChildren(...(sourceDetails ? Array.from(sourceDetails.children).map((node) => node.cloneNode(true)) : []));
      actions.replaceChildren(...(sourceActions ? Array.from(sourceActions.childNodes).map((node) => node.cloneNode(true)) : []));
      modal.show();
    });
  });

  modalElement.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-character-overview-mutation]');
    if (!form) return;
    event.preventDefault();
    const submit = form.querySelector('[type="submit"]');
    if (submit.disabled) return;
    submit.disabled = true;
    error.classList.add('d-none');
    try {
      const response = await fetch(form.action, {
        method: form.method,
        body: new FormData(form),
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'No se pudo completar la acción.');
      window.location.reload();
    } catch (exception) {
      error.textContent = exception.message || 'No se pudo completar la acción.';
      error.classList.remove('d-none');
      modal.hide();
      submit.disabled = false;
      error.focus();
    }
  });
}
