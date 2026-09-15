import { pipeline } from 'node:stream/promises';
import { Transform } from 'node:stream';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import gulp from 'gulp';
import browserSync from 'browser-sync';
import * as dartSass from 'sass';
import gulpSass from 'gulp-sass';
import sourcemaps from 'gulp-sourcemaps';
import postcss from 'gulp-postcss';
import autoprefixer from 'autoprefixer';
import cssnano from 'cssnano';
import rename from 'gulp-rename';
import terser from 'gulp-terser';
import { ESLint } from 'eslint';

const sass = gulpSass(dartSass);
const browser = browserSync.create();
const scriptSources = ['js/*.js', '!js/*.min.js'];

export async function styles() {
  const cssFiles = [];
  await pipeline(
    gulp.src('sass/*.scss'),
    sourcemaps.init(),
    sass.sync({
      style: 'expanded',
      loadPaths: ['.', fileURLToPath(new URL('./node_modules', import.meta.url))]
    }),
    postcss([autoprefixer()]),
    new Transform({
      objectMode: true,
      transform(file, encoding, done) {
        cssFiles.push(path.resolve('css', file.relative));
        done(null, file);
      }
    }),
    sourcemaps.write('.'),
    gulp.dest('css')
  );

  if (!cssFiles.length) return;
  // Read only this task's completed CSS outputs, preserving plain application CSS.
  await pipeline(
    gulp.src(cssFiles, { base: 'css' }),
    sourcemaps.init({ loadMaps: true }),
    postcss([cssnano({ preset: 'default' })]),
    rename({ suffix: '.min' }),
    // Maps embed the Sass sources without inflating the stylesheet payloads.
    sourcemaps.write('.'),
    gulp.dest('css')
  );
}

export async function scripts() {
  await pipeline(
    gulp.src(scriptSources),
    terser({
      module: false,
      toplevel: false,
      mangle: { properties: false },
      format: { comments: /^!|@preserve|@license|@cc_on/i }
    }),
    rename({ suffix: '.min' }),
    gulp.dest('js')
  );
}

export async function lint() {
  const eslint = new ESLint({
    overrideConfigFile: fileURLToPath(new URL('./eslint.config.mjs', import.meta.url))
  });
  const results = await eslint.lintFiles(['js/*.js']);
  const formatter = await eslint.loadFormatter('stylish');
  const report = formatter.format(results);
  if (report) process.stdout.write(report + '\n');
  if (results.some(result => result.errorCount > 0)) {
    throw new Error('JavaScript lint failed. See the errors above.');
  }
}

export async function images() {
  // Load the image binaries only for image work, not lint or local serving.
  const [imageminModule, jpegtranModule, gifsicleModule, optipngModule, svgoModule] = await Promise.all([
    import('gulp-imagemin'),
    import('imagemin-jpegtran'),
    import('imagemin-gifsicle'),
    import('imagemin-optipng'),
    import('imagemin-svgo')
  ]);
  await pipeline(
    gulp.src('images/**/*', { nodir: true }),
    imageminModule.default([
      jpegtranModule.default({ progressive: true }),
      gifsicleModule.default({ interlaced: true }),
      optipngModule.default(),
      svgoModule.default({
        plugins: [{
          name: 'preset-default',
          params: { overrides: { cleanupIds: false } }
        }]
      })
    ]),
    gulp.dest('images-min')
  );
}

// Only watch tasks recover from errors. CLI/CI tasks always reject on failure.
function watchTask(task) {
  return async function rebuild() {
    try {
      await task();
      browser.reload();
    } catch (error) {
      console.error(error.message);
    }
  };
}

function startDevelopmentServer(done) {
  browser.init({
    notify: false,
    port: 9000,
    server: { baseDir: './' }
  }, error => {
    if (error) return done(error);
    gulp.watch(['*.html', 'images/**/*', 'fonts/**/*']).on('change', browser.reload);
    gulp.watch('sass/**/*.scss', watchTask(styles));
    gulp.watch(['js/**/*.js', '!js/**/*.min.js'], watchTask(scripts));
    done();
  });
}

export const build = gulp.series(lint, gulp.parallel(styles, scripts, images));
export const serve = gulp.series(gulp.parallel(styles, scripts), startDevelopmentServer);
export default serve;
