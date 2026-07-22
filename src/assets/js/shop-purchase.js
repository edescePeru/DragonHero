import * as bootstrap from 'bootstrap';

const root = document.querySelector('[data-shop-catalog]');

if (root) {
  const offers = Array.from(root.querySelectorAll('[data-shop-offer]'));
  const search = root.querySelector('[data-shop-search]');
  const categoryButtons = Array.from(root.querySelectorAll('[data-shop-category]'));
  const empty = root.querySelector('[data-shop-empty]');
  const feedback = root.querySelector('[data-shop-feedback]');
  const modalElement = root.querySelector('#shop-purchase-modal');
  const modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
  const confirmation = root.querySelector('[data-shop-confirmation]');
  const confirmButton = root.querySelector('[data-shop-confirm]');
  const keys = new Map();
  let activeCategory = 'all';
  let selectedButton = null;
  let requestInFlight = false;

  const uuid = () => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    const values = new Uint8Array(16);
    window.crypto.getRandomValues(values);
    values[6] = (values[6] & 15) | 64;
    values[8] = (values[8] & 63) | 128;
    return Array.from(values, (value, index) => ([4, 6, 8, 10].includes(index) ? '-' : '') + value.toString(16).padStart(2, '0')).join('');
  };

  const filter = () => {
    const term = (search ? search.value : '').trim().toLocaleLowerCase('es');
    let visible = 0;
    offers.forEach((offer) => {
      const matchesCategory = activeCategory === 'all' || offer.dataset.category === activeCategory;
      const matchesSearch = !term || offer.dataset.search.includes(term);
      offer.hidden = !(matchesCategory && matchesSearch);
      if (!offer.hidden) visible += 1;
    });
    if (empty) empty.classList.toggle('d-none', visible !== 0);
  };

  const notify = (message, success) => {
    feedback.textContent = message;
    feedback.className = `alert ${success ? 'alert-success' : 'alert-danger'}`;
  };

  const refreshPresentation = (data, offer) => {
    if (data.current_gold_balance !== null) {
      root.dataset.currentGold = String(data.current_gold_balance);
      root.querySelectorAll('[data-shop-gold],[data-current-gold]').forEach((node) => { node.textContent = String(data.current_gold_balance); });
    }
    if (data.inventory_slots_used !== null) {
      root.dataset.inventoryUsed = String(data.inventory_slots_used);
      root.querySelector('[data-shop-slots-used]').textContent = String(data.inventory_slots_used);
    }
    if (data.inventory_slots_capacity !== null) {
      root.dataset.inventoryCapacity = String(data.inventory_slots_capacity);
      root.querySelector('[data-shop-slots-capacity]').textContent = String(data.inventory_slots_capacity);
    }
    if (data.stock_remaining !== null) offer.querySelector('[data-offer-stock]').textContent = data.stock_remaining === 0 ? 'Agotado' : `${data.stock_remaining} disponibles`;
    const count = offer.querySelector('[data-offer-count]');
    if (count && data.purchase_count_for_character !== null) count.textContent = String(data.purchase_count_for_character);

    offers.forEach((entry) => {
      const button = entry.querySelector('[data-shop-buy]');
      const price = Number(entry.dataset.price);
      const slots = Number(entry.dataset.additionalSlots || 0);
      const lacksGold = Number(root.dataset.currentGold) < price;
      const lacksSpace = Number(root.dataset.inventoryUsed) + slots > Number(root.dataset.inventoryCapacity);
      if (!button.disabled && (lacksGold || lacksSpace)) button.disabled = true;
    });
    const reachedLimit = offer.dataset.purchaseLimit && data.purchase_count_for_character >= Number(offer.dataset.purchaseLimit);
    if (data.stock_remaining === 0 || reachedLimit) {
      offer.querySelector('[data-shop-buy]').disabled = true;
      const state = offer.querySelector('[data-offer-state]');
      state.textContent = data.stock_remaining === 0 ? 'Agotada' : 'Límite alcanzado';
      state.classList.remove('bg-success');
      state.classList.add('bg-secondary');
    }
  };

  categoryButtons.forEach((button) => button.addEventListener('click', () => {
    activeCategory = button.dataset.shopCategory;
    categoryButtons.forEach((candidate) => {
      candidate.classList.toggle('btn-primary', candidate === button);
      candidate.classList.toggle('btn-outline-primary', candidate !== button);
    });
    filter();
  }));
  if (search) search.addEventListener('input', filter);

  root.querySelectorAll('[data-shop-buy]').forEach((button) => button.addEventListener('click', () => {
    selectedButton = button;
    confirmation.textContent = `Comprar ${button.dataset.quantity} × ${button.dataset.itemName} por ${button.dataset.price} oro.`;
    if (modal) modal.show();
  }));

  if (confirmButton) confirmButton.addEventListener('click', async () => {
    if (!selectedButton || requestInFlight) return;
    requestInFlight = true;
    confirmButton.disabled = true;
    const offer = selectedButton.closest('[data-shop-offer]');
    const offerId = offer.dataset.offerId;
    if (!keys.has(offerId)) keys.set(offerId, uuid());
    try {
      const response = await fetch(selectedButton.dataset.purchaseUrl, {
        method: 'POST',
        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrfToken},
        body: JSON.stringify({idempotency_key: keys.get(offerId)}),
      });
      const payload = await response.json();
      keys.delete(offerId);
      if (!response.ok || !payload.success) {
        notify(payload.message || 'No fue posible completar la compra.', false);
        return;
      }
      refreshPresentation(payload.data, offer);
      notify(payload.message, true);
      if (modal) modal.hide();
    } catch (error) {
      notify('No se pudo confirmar la respuesta. Reintenta para recuperar la misma compra.', false);
    } finally {
      requestInFlight = false;
      confirmButton.disabled = false;
    }
  });
}
