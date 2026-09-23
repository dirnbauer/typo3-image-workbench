import { build } from 'esbuild';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');

await build({
  entryPoints: [resolve(here, 'entry.mjs')],
  bundle: true,
  minify: true,
  format: 'esm',
  target: 'es2022',
  define: { 'process.env.NODE_ENV': '"production"' },
  loader: { '.js': 'jsx' },
  outfile: resolve(root, 'Resources/Public/JavaScript/Vendor/filerobot-image-editor.js'),
  legalComments: 'none',
  logLevel: 'info',
});

// The editor labels are backend labels (Resources/Private/Language/locallang_editor.xlf).
// A Filerobot update that adds or renames a label must not ship half-translated.
const { default: defaults } = await import('react-filerobot-image-editor/lib/context/defaultTranslations.js');
const xliff = await readFile(resolve(root, 'Resources/Private/Language/locallang_editor.xlf'), 'utf8');
const known = new Set([...xliff.matchAll(/<unit id="([^"]+)">/g)].map((match) => match[1]));
const missing = Object.keys(defaults).filter((key) => !known.has(key));
const stale = [...known].filter((key) => !(key in defaults));
if (missing.length || stale.length) {
  console.error('locallang_editor.xlf is out of sync with Filerobot.');
  if (missing.length) console.error(`  missing: ${missing.join(', ')}`);
  if (stale.length) console.error(`  unknown: ${stale.join(', ')}`);
  process.exit(1);
}
console.log(`editor labels in sync (${known.size} keys)`);
