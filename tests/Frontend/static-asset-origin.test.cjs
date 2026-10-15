const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..', '..');
const views = path.join(root, 'resources/views');

function bladeFiles(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) return bladeFiles(full);
    return entry.name.endsWith('.blade.php') ? [full] : [];
  });
}

test('browser pages load public assets origin-relatively, never through asset()', () => {
  /* The document seal feeds PDF/DOCX output and keeps its absolute asset() URL. */
  const allowed = new Set([path.join(views, 'reports/document/header.blade.php')]);
  const offenders = bladeFiles(views)
    .filter((file) => !allowed.has(file))
    .filter((file) => /\{\{\s*asset\(/.test(fs.readFileSync(file, 'utf8')));

  assert.deepEqual(offenders.map((file) => path.relative(root, file)), []);
});

test('layout keeps cache-busting versions on its stylesheet and script', () => {
  const layout = fs.readFileSync(path.join(views, 'layouts/app.blade.php'), 'utf8');
  assert.match(layout, /StaticAsset::url\('css\/app\.css'\) \}\}\?v=\{\{ filemtime\(public_path\('css\/app\.css'\)\) \}\}/);
  assert.match(layout, /StaticAsset::url\('js\/app\.js'\) \}\}\?v=\{\{ filemtime\(public_path\('js\/app\.js'\)\) \}\}/);
});
