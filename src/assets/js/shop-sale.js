import * as bootstrap from 'bootstrap';

const root = document.querySelector('[data-shop-sale-catalog]');

if (root) {
  const entries = Array.from(root.querySelectorAll('[data-shop-sale-entry]'));
  const search = root.querySelector('[data-shop-sale-search]');
  const filters = Array.from(root.querySelectorAll('[data-shop-sale-filter]'));
  const empty = root.querySelector('[data-shop-sale-empty]');
  const feedback = root.querySelector('[data-shop-sale-feedback]');
  const modalElement = root.querySelector('#shop-sale-modal');
  const modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
  const confirmation = root.querySelector('[data-shop-sale-confirmation]');
  const refinementWarning = root.querySelector('[data-shop-sale-refinement-warning]');
  const modalFeedback = root.querySelector('[data-shop-sale-modal-feedback]');
  const confirmButton = root.querySelector('[data-shop-sale-confirm]');
  let activeFilter = 'all';
  let operation = null;
  let requestInFlight = false;

  const uuid = () => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    const values = new Uint8Array(16);
    window.crypto.getRandomValues(values);
    values[6] = (values[6] & 15) | 64;
    values[8] = (values[8] & 63) | 128;
    return Array.from(values, (value, index) => ([4, 6, 8, 10].includes(index) ? '-' : '') + value.toString(16).padStart(2, '0')).join('');
  };

  const notify = (message, success, target = feedback) => {
    if (!target) return;
    target.textContent = message;
    target.className = `alert ${success ? 'alert-success' : 'alert-danger'}`;
  };

  const applyFilters = () => {
    const term = (search ? search.value : '').trim().toLocaleLowerCase('es');
    let visible = 0;
    entries.forEach((entry) => {
      if (!entry.isConnected) return;
      const type = entry.dataset.sourceType;
      const sellable = entry.dataset.canSell === '1';
      const matches = activeFilter === 'all' || activeFilter === type || (activeFilter === 'sellable' && sellable) || (activeFilter === 'blocked' && !sellable);
      entry.hidden = !(matches && (!term || entry.dataset.search.includes(term)));
      if (!entry.hidden) visible += 1;
    });
    if (empty) empty.classList.toggle('d-none', visible !== 0);
  };

  filters.forEach((button) => button.addEventListener('click', () => {
    activeFilter = button.dataset.shopSaleFilter;
    filters.forEach((candidate) => {
      candidate.classList.toggle('btn-primary', candidate === button);
      candidate.classList.toggle('btn-outline-primary', candidate !== button);
    });
    applyFilters();
  }));
  if (search) search.addEventListener('input', applyFilters);

  const prepare = (entry) => {
    const quantityInput = entry.querySelector('[data-shop-sale-quantity]');
    const quantity = entry.dataset.sourceType === 'stack' ? Number(quantityInput.value) : 1;
    const maximum = Number(entry.dataset.availableQuantity);
    if (!Number.isInteger(quantity) || quantity < 1 || quantity > maximum) {
      notify(`Selecciona una cantidad entre 1 y ${maximum}.`, false);
      if (quantityInput) quantityInput.focus();
      return;
    }
    operation = {entry, quantity, key: uuid()};
    const total = Number(entry.dataset.unitGold) * quantity;
    confirmation.textContent = `Venderás ${quantity} × ${entry.dataset.itemName} por ${total} de oro.`;
    const refinement = Number(entry.dataset.refinementLevel || 0);
    refinementWarning.textContent = refinement > 0 ? `Este objeto tiene refinamiento +${refinement}. El refinamiento no aumenta su valor de venta y se perderá definitivamente.` : '';
    refinementWarning.classList.toggle('d-none', refinement === 0);
    modalFeedback.className = 'alert d-none mt-3 mb-0';
    if (modal) modal.show();
  };

  root.querySelectorAll('[data-shop-sale-button]:not([disabled])').forEach((button) => button.addEventListener('click', () => prepare(button.closest('[data-shop-sale-entry]'))));

  const updateSharedState = (data) => {
    const pageRoot = document.querySelector('[data-shop-catalog]');
    pageRoot.dataset.currentGold = String(data.current_gold);
    pageRoot.dataset.inventoryUsed = String(data.inventory_used);
    pageRoot.dataset.inventoryCapacity = String(data.inventory_capacity);
    pageRoot.querySelectorAll('[data-shop-gold]').forEach((node) => { node.textContent = String(data.current_gold); });
    document.querySelectorAll('[data-shop-slots-used]').forEach((node) => { node.textContent = String(data.inventory_used); });
    document.querySelectorAll('[data-shop-slots-capacity]').forEach((node) => { node.textContent = String(data.inventory_capacity); });
  };

  const complete = (data, submittedOperation) => {
    const entry = submittedOperation.entry;
    updateSharedState(data);
    if (data.item_removed || entry.dataset.sourceType === 'instance') {
      entry.remove();
    } else {
      const locked = Number(entry.querySelector('[data-sale-total]').textContent) - Number(entry.dataset.availableQuantity);
      const available = Math.max(0, Number(data.remaining_quantity) - locked);
      entry.dataset.availableQuantity = String(available);
      entry.querySelector('[data-sale-available]').textContent = String(available);
      entry.querySelector('[data-sale-total]').textContent = String(data.remaining_quantity);
      entry.querySelector('[data-sale-maximum]').textContent = `${available * Number(entry.dataset.unitGold)} oro`;
      const input = entry.querySelector('[data-shop-sale-quantity]');
      input.max = String(available);
      input.value = '1';
    }
    const count = document.querySelector('[data-shop-sellable-count]');
    if (count && (data.item_removed || entry.dataset.sourceType === 'instance')) count.textContent = String(Math.max(0, Number(count.textContent) - 1));
    applyFilters();
  };

  if (confirmButton) confirmButton.addEventListener('click', async () => {
    if (!operation || requestInFlight) return;
    const submittedOperation = operation;
    const submittedEntry = submittedOperation.entry;
    const submittedButton = submittedEntry.querySelector('[data-shop-sale-button]');
    requestInFlight = true;
    confirmButton.disabled = true;
    submittedButton.disabled = true;
    const body = {source_type: submittedEntry.dataset.sourceType, quantity: submittedOperation.quantity, idempotency_key: submittedOperation.key};
    if (body.source_type === 'stack') body.character_item_id = Number(submittedEntry.dataset.characterItemId);
    else body.item_instance_uuid = submittedEntry.dataset.itemInstanceUuid;
    if (submittedEntry.dataset.zoneId) body.zone_id = Number(submittedEntry.dataset.zoneId);
    try {
      const response = await fetch(submittedEntry.dataset.saleUrl, {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrfToken}, body: JSON.stringify(body)});
      let payload;
      try { payload = await response.json(); } catch (error) { throw new Error('invalid_response'); }
      if (!response.ok || !payload.success) {
        const message = response.status === 409 ? 'La operación entra en conflicto con una solicitud anterior.' : response.status === 404 ? 'El objeto o la tienda ya no están disponibles.' : payload.message || 'No fue posible completar la venta.';
        notify(message, false, modalFeedback);
        return;
      }
      complete(payload.data, submittedOperation);
      notify(`${payload.message || 'Venta realizada correctamente.'} Recibiste ${payload.data.total_gold} de oro.`, true);
      operation = null;
      if (modal) modal.hide();
    } catch (error) {
      notify(error.message === 'invalid_response' ? 'El servidor devolvió una respuesta inesperada.' : 'No se pudo confirmar la respuesta. Reintenta para recuperar la misma venta.', false, modalFeedback);
    } finally {
      requestInFlight = false;
      confirmButton.disabled = false;
      const availableQuantity = Number(submittedEntry.dataset.availableQuantity || 0);
      if (submittedEntry.isConnected && submittedButton.isConnected && submittedEntry.dataset.canSell === '1' && (submittedEntry.dataset.sourceType !== 'stack' || availableQuantity > 0)) submittedButton.disabled = false;
    }
  });
}
