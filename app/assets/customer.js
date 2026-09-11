const moneyFmt = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
let customerCart = [];

function selectedAddons() {
  return Array.from(document.querySelectorAll('.addons-list input:checked')).map((input) => ({
    id: Number(input.value),
    name: input.dataset.name,
    price: Number(input.dataset.price || 0)
  }));
}

function renderCustomerCart() {
  const box = document.getElementById('cartItems');
  const json = document.getElementById('itemsJson');
  const subtotalText = document.getElementById('subtotalText');
  const deliveryText = document.getElementById('deliveryText');
  const totalText = document.getElementById('totalText');
  if (!box) return;
  box.innerHTML = '';
  let subtotal = 0;
  if (customerCart.length === 0) {
    box.className = 'cart-items empty';
    box.textContent = 'Agrega productos del menu';
  } else {
    box.className = 'cart-items';
  }
  customerCart.forEach((item, index) => {
    const addonsTotal = item.addons.reduce((sum, addon) => sum + addon.price, 0);
    const line = item.qty * (item.price + addonsTotal);
    subtotal += line;
    const row = document.createElement('div');
    row.className = 'cart-row';
    row.innerHTML = `
      <strong>${item.name}</strong>
      <small>${item.addons.length ? 'Adiciones: ' + item.addons.map((a) => a.name).join(', ') : 'Sin adiciones'}</small>
      <div class="cart-actions">
        <button type="button" data-action="dec" data-index="${index}">-</button>
        <b>${item.qty}</b>
        <button type="button" data-action="inc" data-index="${index}">+</button>
        <span>${moneyFmt.format(line)}</span>
        <button type="button" class="remove" data-action="remove" data-index="${index}">x</button>
      </div>`;
    box.appendChild(row);
  });
  const delivery = document.getElementById('fulfillment')?.value === 'domicilio' ? Number(window.deliveryFee || 0) : 0;
  subtotalText.textContent = moneyFmt.format(subtotal);
  deliveryText.textContent = moneyFmt.format(delivery);
  totalText.textContent = moneyFmt.format(subtotal + delivery);
  json.value = JSON.stringify(customerCart.map((item) => ({ id: item.id, qty: item.qty, addons: item.addons.map((a) => a.id) })));
}

document.addEventListener('click', (event) => {
  const addonBtn = event.target.closest('.pick-addon');
  if (addonBtn) {
    const addonInput = document.querySelector(`.addons-list input[value="${addonBtn.dataset.addonId}"]`);
    if (addonInput) {
      addonInput.checked = !addonInput.checked;
      const card = addonBtn.closest('.addon-menu-card');
      if (card) card.classList.toggle('is-selected', addonInput.checked);
      addonBtn.textContent = addonInput.checked ? 'Seleccionada para el siguiente producto' : 'Agregar al siguiente producto';
    }
    return;
  }

  const productBtn = event.target.closest('.add-product');
  if (productBtn) {
    customerCart.push({
      id: Number(productBtn.dataset.id),
      name: productBtn.dataset.name,
      price: Number(productBtn.dataset.price || 0),
      qty: 1,
      addons: selectedAddons()
    });
    document.querySelectorAll('.addons-list input:checked').forEach((input) => { input.checked = false; });
    document.querySelectorAll('.addon-menu-card.is-selected').forEach((card) => {
      card.classList.remove('is-selected');
      const button = card.querySelector('.pick-addon');
      if (button) button.textContent = 'Agregar al siguiente producto';
    });
    renderCustomerCart();
    return;
  }

  const cartBtn = event.target.closest('.cart-actions button');
  if (!cartBtn) return;
  const index = Number(cartBtn.dataset.index);
  const action = cartBtn.dataset.action;
  if (!customerCart[index]) return;
  if (action === 'inc') customerCart[index].qty += 1;
  if (action === 'dec') customerCart[index].qty -= 1;
  if (action === 'remove' || customerCart[index].qty <= 0) customerCart.splice(index, 1);
  renderCustomerCart();
});

document.getElementById('fulfillment')?.addEventListener('change', (event) => {
  const isDelivery = event.target.value === 'domicilio';
  document.querySelectorAll('.delivery-field').forEach((field) => {
    field.style.display = isDelivery ? '' : 'none';
  });
  renderCustomerCart();
});

document.getElementById('customerOrderForm')?.addEventListener('submit', (event) => {
  renderCustomerCart();
  if (customerCart.length === 0) {
    event.preventDefault();
    alert('Agrega productos al pedido.');
  }
});

renderCustomerCart();
