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

In Windows PowerShell, use `npm.cmd` in place of `npm` if the execution policy
blocks `npm.ps1`. This uses npm's batch launcher without changing system policy.

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

## Versioned Jenkins Pipeline

The root `Jenkinsfile` automates the same Windows build and then creates a Docker
image. Keeping the pipeline in Git makes changes to the build reviewable with
the application changes they support.

The stages run in order:

1. Check out the commit selected by Jenkins.
2. Check that the agent is Windows and can reach Docker's Linux engine.
3. Run `npm.cmd ci --include=dev --engine-strict` to install the lockfile and
   enforce the declared Node version requirements.
4. Run `npm.cmd run test:build` to check outputs and failure handling in fixtures.
5. Run `npm.cmd run build` to lint and generate the application's assets.
6. Build `srms-ci:build-<Jenkins build number>-<12-character commit>` from this
   same workspace, so the PHP image contains the freshly generated assets.
7. Start disposable containers to check PHP and its PDO MySQL extension, then
   archive `test-artifacts/ci-image.txt` with the resulting image tag.

Each failed command fails its stage and prevents later stages from running.
The image stays in the build agent's local Docker engine. Registry publishing
and deployment are separate steps; this pipeline does not need registry
credentials or a school database. The PHP checks do not establish authenticated
application/database behavior. Use the existing production smoke test below
for that additional installation check.

### Configure the job

The Jenkins agent needs Git, Node 24.15.0 or newer within 24.x, npm, and Docker
on its PATH. Start Docker Desktop with Linux containers. Verify access from the
**Jenkins agent account**: Docker working in your personal terminal does not
prove that a Windows service account can use the same engine.

1. Create a **Pipeline** job, for example `srms-ci`. An existing Freestyle job
   can keep using the earlier instructions while this job is introduced.
2. Under **Pipeline**, choose **Pipeline script from SCM**, then **Git**.
3. Enter this repository's Git URL and credentials if the repository is private.
4. Set the branch to `*/master` and the script path to `Jenkinsfile`.
5. Save and select **Build Now** after the pipeline files are committed and
   available in that repository.

This pipeline uses `agent any` for the current Windows setup and rejects Unix
agents. If Jenkins has several agents, replace it with a label for a configured
Windows agent, such as `agent { label 'srms-windows' }`, and assign that label to
the matching node. All stages use the same agent and workspace.

Jenkins must have the Pipeline (including Declarative Pipeline) and Git plugins.
See the official [Pipeline job setup guide](https://www.jenkins.io/doc/book/pipeline/getting-started/)
and [Pipeline syntax reference](https://www.jenkins.io/doc/book/pipeline/syntax/).

The job keeps the latest 20 build records, allows one run of this job at a time,
and has a 30-minute timeout. Docker images have their own lifecycle: deleting a
Jenkins build record does not remove its image. Remove obsolete CI image tags
explicitly when they are no longer needed.

### Local verification

These commands exercise the frontend stages from PowerShell:

```powershell
npm.cmd ci --include=dev --engine-strict
npm.cmd run test:build
npm.cmd run build
```

With Docker running, the existing release test builds the image, initializes a
new MySQL 8.4 database, installs the school schema, and checks HTTP access:

```powershell
python -u tests/production-smoke.py
```

It uses a unique Compose project, temporary credentials, a random loopback port,
and a disposable test volume. It cleans up its own test resources afterward.
It needs the local `.env` file referenced by production Compose; its generated
test settings override the database and delivery settings. This check is
separate from the Jenkins job and requires Python 3 and Docker Compose.

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

## Jenkins continuation validation (16 September 2026)

- `npm.cmd ci --include=dev --engine-strict` passed on Node 24.21.0.
- `npm.cmd run lint` and `npm.cmd run test:build` passed. The fixture suite
  checked both successful outputs and nonzero exits for invalid inputs.
- `npm.cmd run build` passed, generating the application styles, scripts and
  optimized images. The existing Sass deprecation warnings remain.
- The first fixture run timed out during its invalid-image check while Docker
  Desktop was starting and Windows reported about 68 MB free physical memory
  on a 4 GB machine. After Docker was stopped and dependencies were reinstalled,
  the complete suite passed without changes to its tests or timeout.
- Docker's Linux API remained unavailable during startup. Docker Desktop was
  returned to its stopped state; the image build, PHP container checks and
  production smoke test have not been verified in this continuation.
- The local Jenkins service was running, but its API returned HTTP 403 without
  authentication. The Jenkinsfile has not yet been executed by Jenkins. The
  job setup described above remains necessary after the files are committed
  and made available to Jenkins through Git.

The Jenkinsfile is excluded from release images and denied by Apache when the
development Compose stack mounts the repository into the web root.
