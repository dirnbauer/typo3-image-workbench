import { build } from 'esbuild';
import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');

const cssOutput = resolve(root, 'Resources/Public/JavaScript/Vendor/filerobot-image-editor.bundle.css');

await build({
  entryPoints: [resolve(here, 'entry.mjs')],
  bundle: true,
  minify: true,
  format: 'iife',
  define: { 'process.env.NODE_ENV': '"production"' },
  loader: { '.js': 'jsx' },
  outfile: resolve(root, 'Resources/Public/JavaScript/Vendor/filerobot-image-editor.bundle.js'),
  legalComments: 'none',
  logLevel: 'info',
});

const css = await readFile(cssOutput, 'utf8');
await writeFile(cssOutput, css.replaceAll('border-radius:4px', 'border-radius:0'), 'utf8');
