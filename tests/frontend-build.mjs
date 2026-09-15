import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const root = fileURLToPath(new URL('../', import.meta.url));
fs.mkdirSync(path.join(root, 'test-artifacts'), { recursive: true });
const fixture = fs.mkdtempSync(path.join(root, 'test-artifacts/frontend-fixture-'));
const node = process.execPath;
const cli = path.join(root, 'node_modules/gulp/bin/gulp.js');
for (const name of ['gulpfile.mjs', 'eslint.config.mjs', 'package.json']) {
  fs.copyFileSync(path.join(root, name), path.join(fixture, name));
}
for (const name of ['sass', 'js', 'images/nested']) fs.mkdirSync(path.join(fixture, name), { recursive: true });
const write = (name, data) => fs.writeFileSync(path.join(fixture, name), data);
const read = name => fs.readFileSync(path.join(fixture, name), 'utf8');
const goodSass = '$color: #123456; .example { color: $color; display: flex; user-select: none; }';
const goodJs = '/*! @license fixture */\nfunction publicHandler(value) { return value?.answer ?? 42; }\nwindow.result = publicHandler({answer: 7});';
write('sass/main.scss', goodSass);
write('sass/_partial.scss', '$unused: red;');
write('js/main.js', goodJs);
write('js/vendor.min.js', 'DO NOT PARSE OR OVERWRITE THIS MINIFIED INPUT');
fs.copyFileSync(path.join(root, 'images/school system background.jpg'), path.join(fixture, 'images/nested/photo.jpg'));
write('images/hook.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12"><rect id="application-hook" width="12" height="12" fill="red"/></svg>');
write('images/pixel.png', Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', 'base64'));
write('images/pixel.gif', Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64'));

function run(task, shouldPass = true) {
  const result = spawnSync(node, [cli, '--cwd', fixture, '--gulpfile', path.join(fixture, 'gulpfile.mjs'), task], {
    encoding: 'utf8', timeout: 90000, windowsHide: true,
    env: { ...process.env, PATH: path.dirname(node) + path.delimiter + process.env.PATH }
  });
  const output = result.stdout + result.stderr;
  assert.equal(Boolean(result.error), false, String(result.error));
  if (shouldPass) assert.equal(result.status, 0, output);
  else assert.notEqual(result.status, 0, output);
  assert.ok(!output.includes('Access URLs'), 'CI unexpectedly started BrowserSync');
  return output;
}

run('build');
assert.match(read('css/main.css'), /user-select/);
assert.match(read('css/main.min.css'), /123456/);
assert.equal(fs.existsSync(path.join(fixture, 'css/_partial.css')), false);
for (const name of ['main.css', 'main.min.css']) {
  const css = read('css/' + name);
  const inline = css.match(/sourceMappingURL=data:application\/json[^,]*,([^\s*]+)/);
  const external = css.match(/sourceMappingURL=([^\s*]+)/);
  const map = inline ? JSON.parse(Buffer.from(inline[1], 'base64').toString()) : JSON.parse(read('css/' + external[1]));
  assert.ok(map.sources.some(source => source.endsWith('main.scss')), JSON.stringify(map.sources));
  assert.ok(map.mappings.length > 0);
  assert.ok(map.sourcesContent.some(source => source?.includes('$color')));
}
assert.equal(read('js/vendor.min.js'), 'DO NOT PARSE OR OVERWRITE THIS MINIFIED INPUT');
assert.equal(fs.existsSync(path.join(fixture, 'js/vendor.min.min.js')), false);
assert.match(read('js/main.min.js'), /@license fixture/);
const browserGlobal = { window: {} };
vm.runInNewContext(read('js/main.min.js'), browserGlobal);
assert.equal(browserGlobal.window.result, 7);
assert.equal(browserGlobal.publicHandler(null), 42);
assert.match(read('images-min/hook.svg'), /id="application-hook"/);
for (const name of ['nested/photo.jpg', 'pixel.png', 'pixel.gif']) {
  assert.ok(fs.statSync(path.join(fixture, 'images-min', name)).size > 0);
}
const initial = fs.readFileSync(path.join(fixture, 'images/nested/photo.jpg'));
const optimized = fs.readFileSync(path.join(fixture, 'images-min/nested/photo.jpg'));
assert.ok(optimized.length <= initial.length);
assert.notEqual(optimized.indexOf(Buffer.from([0xff, 0xc2])), -1, 'Expected progressive JPEG');
console.log('PASS: finite build, source maps, CSS/JS outputs, license/public API preservation, minified exclusion, JPEG/PNG/GIF/SVG optimization');

write('js/main.js', 'missingApplicationGlobal();');
assert.match(run('lint', false), /no-undef/);
assert.match(run('build', false), /lint/);
write('js/main.js', goodJs);
write('sass/main.scss', '.broken { color: ; invalid');
assert.match(run('styles', false), /error|Error/);
write('sass/main.scss', goodSass);
write('js/main.js', 'function broken( {');
assert.match(run('scripts', false), /error|Error/);
write('js/main.js', goodJs);
// A recognized PNG signature with corrupt data makes the optimizer fail.
write('images/corrupt.png', Buffer.from([137, 80, 78, 71, 13, 10, 26, 10, 0, 0, 0, 0]));
assert.match(run('images', false), /error|Error/);
fs.unlinkSync(path.join(fixture, 'images/corrupt.png'));
console.log('PASS: lint, CI build, Sass, minifier, and image failures return nonzero');
// Keep fixtures on failure; remove only this test's verified directory on success.
const artifactRoot = fs.realpathSync(path.join(root, 'test-artifacts'));
const resolvedFixture = fs.realpathSync(fixture);
assert.equal(path.dirname(resolvedFixture), artifactRoot);
assert.ok(path.basename(resolvedFixture).startsWith('frontend-fixture-'));
fs.rmSync(resolvedFixture, { recursive: true });
