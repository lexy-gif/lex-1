# Frontend build

The PHP application keeps its existing CSS and JavaScript paths. Gulp compiles
the Sass entry points, minifies root JavaScript files, checks JavaScript, and
optimizes images. Node is needed for building assets, not for serving PHP.

## Requirements

- Node 24 LTS, version 24.15.0 or newer within the 24.x line.
- npm supplied with that Node installation.
- Internet access for the initial npm install, including image-tool binaries.

The version floor satisfies the selected cssnano and ESLint releases. The
dependency versions are pinned in `package.json`, and `package-lock.json` locks
their transitive dependencies. Commit both files. `node_modules/` is ignored.

## Install and build

```sh
npm ci --include=dev
npm run build
```

The build runs lint first, then styles, scripts, and images in parallel. It
finishes with exit code 0 on success. Compilation, minification, image processing,
and lint errors fail the command. It does not start BrowserSync or file watchers.

| Command | Result |
| --- | --- |
| `npm run styles` | `sass/*.scss` entry points to `css/*.css` and `css/*.min.css`, with source maps |
| `npm run scripts:build` | Root `js/*.js` source files to matching `js/*.min.js` files |
| `npm run lint` | ESLint correctness checks on root source JavaScript |
| `npm run images` | Optimized images in `images-min/`, preserving relative paths |
| `npm run dev` | Prepare styles/scripts, then start BrowserSync on port 9000 |
| `npm run test:build` | Isolated fixtures verify outputs, maps, public JavaScript names, image formats, and failure exit codes |

Sass partials beginning with `_` do not produce standalone CSS.
Normal and minified CSS link to sibling `.css.map` files so debugging data does
not inflate the stylesheet payloads. Both maps embed the source content.

Plain application CSS such as `css/custom.css` and `css/srms/` is outside this build. JavaScript
vendor subdirectories and existing `.min.js` inputs are not minified. Public
top-level functions and property names remain available to page scripts.

## Windows Jenkins Freestyle job

Configure the Jenkins agent's Node installation before this build step. A Node
installation available in an interactive terminal may be missing from the
Jenkins service account's PATH. Verify `where node` and `node --version` in the
job if necessary.

Use an **Execute Windows batch command** step:

```bat
@echo off
call npm ci --include=dev
if errorlevel 1 exit /b %errorlevel%

call npm run build
if errorlevel 1 exit /b %errorlevel%

exit /b 0
```

`call` returns control from `npm.cmd` to the batch file. The explicit checks
preserve failures, and `--include=dev` installs the build dependencies even if
the agent has `NODE_ENV=production`. npm scripts find Gulp in `node_modules/.bin`;
no global Gulp installation is needed.

Run the Docker build after this step succeeds, using this same checkout as its
build context. The current PHP/Apache Dockerfile copies the generated assets,
and `.dockerignore` excludes `node_modules`. Node does not need to be installed
inside the PHP runtime image. A multi-stage Docker build is a separate future
deployment choice.

## Local development

```sh
npm run dev
```

BrowserSync keeps the legacy static server at `http://localhost:9000` and watches
SCSS, JavaScript, HTML, images, and fonts. JavaScript watchers exclude generated
`.min.js` files to prevent rebuild loops. Watch failures are reported and the
watcher remains available for the next edit; standalone tasks and CI fail on
the same errors. The default Gulp task also starts this development server.

This static server does not execute PHP. Use the existing Docker Compose app at
`http://localhost:5000` for PHP pages and authenticated workflows. Jenkins must
run `build`, never the default task or `dev`.

## Migration decisions

- Native Node module syntax in `gulpfile.mjs` replaces Babel 6.
- Gulp 4 task composition replaces Gulp 3 dependency arrays and `gulp.start`.
  `gulp-cli@3.1.0` is pinned directly and for Gulp's nested dependency: the older
  CLI bundled with Gulp 4 cannot load an ES module graph with top-level await
  on Node 24 (`ERR_REQUIRE_ASYNC_MODULE`).
- Dart Sass replaces `node-sass`; no Python 2 or obsolete Sass native build
  toolchain is used. Modern Sass has fixed numeric precision and uses `style`
  and `loadPaths` options.
- Browserslist retains `> 1%`, `last 2 versions`, and `Firefox ESR`. Current
  browser data can change which prefixes are emitted.
- ESLint uses browser/jQuery globals and a focused correctness rule set.
  Minified files, vendor subdirectories, formatting, and unused public functions
  are outside the lint scope. Lint does not automatically rewrite source files.
- Explicit JPEGTran optimization preserves lossless progressive JPEG behavior.
  GIF interlacing, PNG optimization, and SVG IDs are retained. These image tools
  use platform binaries installed through npm. They may still have legacy
  transitive dependencies; removing `node-sass` does not imply every old package
  in Gulp 4 or the image wrappers has disappeared. Image installation/processing
  failures are reported, with no silent fallback to copying or lossy encoding.
  The npm audit includes an unpatched critical advisory in their transitive
  `decompress` archive helper ([GHSA-mp2f-45pm-3cg9](https://github.com/advisories/GHSA-mp2f-45pm-3cg9)).
  This helper belongs to binary download/source-build tooling. These packages
  are excluded from the PHP runtime image. The maintained decompressor fork is
  an ES module and is not a compatible drop-in override for these CommonJS
  callers. Resolving the remaining image-tool advisories requires a separate
  dependency migration; a successful asset build is not a clean audit result.
- `sass/bootstrap.scss` imports pinned `bootstrap-sass@3.4.3`, whose source is
  Bootstrap 3.4.1. The repository's older vendored source was 3.3.6, while the
  deployed CSS already identified itself as 3.4.1. The npm package supplies Sass
  only; the application's Bootstrap JavaScript is unchanged.

Unused Babel, HTML minification, Bower, deletion, bundle, cache, and plugin-loader
packages were removed from the active build dependencies. Generated assets can
have different whitespace, ordering, numeric precision, and source maps. Review
rendered pages when changing the Sass compiler, browser targets, or minifiers.

## Validation for this migration

On Windows with Node 24.21.0 and npm 11.19.0:

- `npm ci --include=dev` and `npm run build` passed in a fresh temporary directory.
- The lockfile contains no `node-sass`, `node-gyp`, `babel-core`, or `gulp-util`.
- Fixture checks verified CSS/source maps, JavaScript license/public function
  preservation, exclusion of already-minified inputs, and JPEG/PNG/GIF/SVG output.
- Deliberate lint, Sass, JavaScript minification, and image errors returned nonzero.
- Headless Chrome checks compared the original CSS with normal and minified
  regenerated CSS at 320, 767, 768, 991, 992, and 1280 pixels. The sampled layouts
  and styles matched; sidebar/account controls, form input, and charts worked
  without JavaScript errors.

The initial clean install reported 49 npm audit findings: 26 moderate, 22 high,
and 1 critical. The migration does not claim to resolve all legacy Gulp 4,
BrowserSync, source-map, or image-tool advisories. Audit counts can change as
advisories are published. Run `npm audit` for current details; avoid accepting
its proposed major upgrades/downgrades without reviewing task compatibility.

Docker Desktop's Linux engine was stopped during validation, so authenticated
PHP/database workflows were not exercised. The browser checks used static
fixtures with the actual application styles and scripts. PHP and Docker
configuration files were not modified.
