if (window.parent !== window) {
  let scheduled = false;
  new ResizeObserver(() => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
      scheduled = false;
      window.parent.postMessage({type: 'mcp-apps-activity-height', height: Math.ceil(document.body.getBoundingClientRect().height)}, '*');
    });
  }).observe(document.body);
}
