import { build } from 'esbuild';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { Script } from 'node:vm';
import { fileURLToPath } from 'node:url';
import { resolve, dirname } from 'node:path';

const moduleRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
await mkdir(resolve(moduleRoot, 'dist'), { recursive: true });
const css = await readFile(resolve(moduleRoot, 'ui/demos.css'), 'utf8');
for (const demo of ['media-picker', 'views-chart']) {
  const bundle = await build({ entryPoints: [resolve(moduleRoot, `ui/${demo}.js`)], bundle: true, format: 'iife', minify: true, target: 'es2022', write: false, legalComments: 'none' });
  const script = bundle.outputFiles[0].text.replace(/<\/script/gi, '<\\/script');
  const template = await readFile(resolve(moduleRoot, `ui/${demo}.html`), 'utf8');
  const html = template.replace('<!-- APP_STYLE -->', () => `<style>${css}</style>`).replace('<!-- APP_SCRIPT -->', () => `<script id="app-script">${script}</script>`);
  const finalScript = html.match(/<script id="app-script">([\s\S]*?)<\/script>/)?.[1];
  if (!finalScript) throw new Error('The compiled app script is missing.');
  new Script(finalScript);
  await writeFile(resolve(moduleRoot, `dist/${demo}.html`), html);
  console.log(`Built ${demo} (${Math.round(Buffer.byteLength(html) / 1024)} KiB), script syntax valid.`);
}
