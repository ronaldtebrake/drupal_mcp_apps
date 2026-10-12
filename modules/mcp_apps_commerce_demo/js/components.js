(() => {
  window.addEventListener('message', event => {
    if (event.source !== window.parent || event.data?.type !== 'mcp-apps-commerce-busy') return;
    document.querySelectorAll('button, input, select').forEach(control => {
      if (control.disabled && control.dataset.shopBusy !== 'true') return;
      control.disabled = Boolean(event.data.busy);
      control.dataset.shopBusy = String(Boolean(event.data.busy));
    });
  });
  const send = (action, args = {}) => {
    const detail = {action, args};
    if (window.parent !== window) window.parent.postMessage({type: 'mcp-apps-commerce', ...detail}, '*');
    else document.dispatchEvent(new CustomEvent('mcp-apps-commerce', {detail}));
  };
  const submit = form => {
    if (!form.reportValidity()) return;
    const values = Object.fromEntries(new FormData(form));
    if (form.dataset.shopForm === 'filter') send('filter', {query: values.query, category: values.category, budget: Number(values.budget)});
    else send('cart', {variation_id: Number(values.variation_id), quantity: Number(values.quantity), add: form.dataset.shopForm === 'cart'});
  };
  document.addEventListener('submit', event => {
    const form = event.target.closest('[data-shop-form]');
    if (form) {event.preventDefault();submit(form);}
  });
  document.addEventListener('keydown', event => {
    const form = event.target.closest('[data-shop-form]');
    if (form && event.key === 'Enter' && event.target.tagName === 'INPUT') {event.preventDefault();submit(form);}
  });
  document.addEventListener('change', event => {
    const select = event.target.closest('select[name="variation_id"]');
    if (select) select.closest('.shop-product').querySelector('[data-shop-price]').textContent = select.selectedOptions[0].dataset.price;
    const compare = event.target.closest('[data-shop-compare]');
    if (compare) send('compare', {product_id: Number(compare.dataset.shopCompare), selected: compare.checked});
  });
  document.addEventListener('click', event => {
    const view = event.target.closest('[data-shop-view]');
    if (view) send('view', {view: view.dataset.shopView});
    const submitButton = event.target.closest('[data-shop-submit]');
    if (submitButton) submit(submitButton.closest('[data-shop-form]'));
    const remove = event.target.closest('[data-shop-remove]');
    if (remove) send('cart', {variation_id: Number(remove.dataset.shopRemove), quantity: 0});
    if (event.target.closest('[data-shop-clear-compare]')) send('compare', {clear: true});
    const review = event.target.closest('[data-shop-review]');
    if (review) review.closest('.shop-basket-card').querySelector('dialog').showModal();
    const closeReview = event.target.closest('[data-shop-close-review]');
    if (closeReview) closeReview.closest('dialog').close();
    const checkout = event.target.closest('[data-shop-checkout]');
    if (checkout) {
      checkout.dataset.shopBusy = 'true';
      checkout.disabled = true;
      send('checkout', {order_id: Number(checkout.dataset.order), revision: checkout.dataset.revision});
    }
    const link = event.target.closest('[data-shop-link]');
    if (link) send('link', {url: link.dataset.shopLink});
  });
  const resize = () => {
    const content = document.querySelector('.fieldwork-shop');
    const height = Math.max(150, Math.ceil(content ? content.getBoundingClientRect().bottom + window.scrollY : document.body.getBoundingClientRect().height));
    if (window.parent !== window) window.parent.postMessage({type: 'mcp-apps-commerce-height', height}, '*');
  };
  if (typeof ResizeObserver !== 'undefined') new ResizeObserver(resize).observe(document.body);
  window.addEventListener('load', resize);
})();
