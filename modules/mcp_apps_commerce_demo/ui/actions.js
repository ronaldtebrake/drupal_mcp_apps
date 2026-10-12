export const names = ['tool_api__commerce_shop_filter', 'tool_api__commerce_shop_cart', 'tool_api__commerce_shop_checkout', 'tool_api__component_composer_preview', 'tool_api__component_composer_asset'];
export function requestFor(boot, action, args = {}) {
  const base = {session_id: boot.session_id};
  if (action === 'filter' || action === 'view') return {name: names[0], arguments: {...base,...boot.filters,view:boot.view||'catalog',...args,compare:JSON.stringify(boot.compare)}};
  if (action === 'compare') {
    const selected = new Set(boot.compare);
    if (args.clear) selected.clear();
    else if (args.selected) selected.add(args.product_id);
    else selected.delete(args.product_id);
    if (selected.size > 3) throw new Error('Compare up to three products at a time.');
    return {name:names[0],arguments:{...base,...boot.filters,view:boot.view||'catalog',compare:JSON.stringify([...selected])}};
  }
  if (action === 'cart') {
    const previous = boot.cart.items.find(item=>item.variation_id===args.variation_id)?.quantity || 0;
    return {name:names[1],arguments:{...base,variation_id:args.variation_id,quantity:args.quantity+(args.add?previous:0),revision:boot.cart.revision}};
  }
  if (action === 'checkout') return {name:names[2],arguments:{...base,order_id:boot.cart.id,revision:args.revision}};
  throw new Error('Unsupported shopping action.');
}
