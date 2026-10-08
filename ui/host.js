import { App } from '@modelcontextprotocol/ext-apps';

/** Drupal's HTTP transport locks the session while handling a request. */
export function serializeCalls(call) {
  let pending = Promise.resolve();
  return (...args) => {
    const result = pending.then(() => call(...args));
    pending = result.catch(() => {});
    return result;
  };
}

/** Connect an app to its host; every server call uses the host's MCP session. */
export function createHost({ name, receive, error = () => {}, cancel = () => {}, contextChanged = () => {} }) {
  const app = new App({ name, version: '1.0.0' }, {}, { autoResize: true });
  app.ontoolresult = receive;
  app.ontoolcancelled = cancel;
  app.onhostcontextchanged = contextChanged;
  return {
    app,
    connect: () => app.connect().catch(error),
    call: serializeCalls(async (name, args) => {
      const result = await app.callServerTool({ name, arguments: args });
      if (result.isError) throw new Error(result.content?.find((item) => item.type === 'text')?.text || 'Drupal refused the request.');
      return result;
    }),
    async context(data) {
      if (!app.getHostCapabilities()?.updateModelContext) return;
      await app.updateModelContext({ content: [{ type: 'text', text: JSON.stringify(data) }] });
    },
    openLink: (url) => app.openLink({ url }),
  };
}

export function toolProvider(host, allowed) {
  return Object.fromEntries(allowed.map((name) => [name, (args) => host.call(name, args)]));
}
