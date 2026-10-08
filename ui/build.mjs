import { build } from 'esbuild';
import { readFile, writeFile, mkdir, readdir } from 'node:fs/promises';
import { Script } from 'node:vm';
import { fileURLToPath } from 'node:url';
import { resolve, dirname } from 'node:path';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..'), source = resolve(root, 'modules/mcp_apps_openui/ui'), output = resolve(root, 'modules/mcp_apps_openui/dist');
await mkdir(output, { recursive: true });
const result = await build({ entryPoints: [resolve(source, 'composer.jsx')], bundle: true, format: 'iife', minify: true, target: 'es2022', write: false, metafile: true, legalComments: 'inline', define: { 'process.env.NODE_ENV': '"production"' } });
const js = result.outputFiles[0].text.replace(/<\/script/gi, '<\\/script');
new Script(js);
const css = await readFile(resolve(source, 'composer.css'), 'utf8'), template = await readFile(resolve(source, 'composer.html'), 'utf8');
const html = template.replace('<!-- APP_STYLE -->', () => '<style>' + css + '</style>').replace('<!-- APP_SCRIPT -->', () => '<script id="app-script">' + js + '</script>');
await writeFile(resolve(output, 'composer.html'), html.trimEnd() + '\n');
const packages = [...new Set(Object.keys(result.metafile.inputs).filter((path) => path.includes('node_modules/')).map((path) => {
  const parts = path.split('node_modules/').pop().split('/');
  return parts[0].startsWith('@') ? parts.slice(0, 2).join('/') : parts[0];
}))].sort();
let notices = '# Bundled frontend notices\n\nGenerated from packages included by esbuild. The Drupal module code has its own GPL-2.0-or-later license.\n';
for (const name of packages) {
  const path = resolve(root, 'node_modules', name);
  const pkg = JSON.parse(await readFile(resolve(path, 'package.json'), 'utf8'));
  const license = (await readdir(path)).find((file) => /^licen[cs]e(?:\.|$)/i.test(file));
  if (!license) throw new Error('Missing bundled license notice: ' + name);
  notices += '\n## ' + name + ' ' + pkg.version + '\n\n```text\n' + (await readFile(resolve(path, license), 'utf8')).trim().replace(/[ \t]+$/gm, '') + '\n```\n';
}
await writeFile(resolve(root, 'THIRD_PARTY_NOTICES.md'), notices);
console.log('Built Drupal OpenUI composer: ' + Math.round(Buffer.byteLength(html) / 1024) + ' KiB');
