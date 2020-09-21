/**
 * Gulp automation tools.
 */

// ---------------
// General plugins
// ---------------
const gulp = require("gulp");
const noop = require("gulp-noop");
const rename = require("gulp-rename");
const clean = require("del");

// ------------
// Sass plugins
// ------------
const sass = require("gulp-sass");
const sassGlob = require("gulp-sass-glob");
const autoprefix = require("gulp-autoprefixer");
const cleanCss = require("gulp-clean-css");

// ----------------------------
// Javascript plugins
// ----------------------------
const uglify = require("gulp-uglify-es").default;

// ------
// Config
// ------
const basePath = {
  src: "./src/",
  dist: "./assets/",
  templates: "./templates/"
};

const path = {
  fonts: {
    src: `${basePath.src}font/`,
    dist: `${basePath.dist}font/`
  },
  styles: {
    src: `${basePath.src}scss/`,
    dist: `${basePath.dist}css/`
  },
  scripts: {
    src: `${basePath.src}js/`,
    dist: `${basePath.dist}js/`
  },
  images: {
    src: `${basePath.src}image/`,
    dist: `${basePath.dist}image/`
  },
  templates: {
    dist: basePath.templates
  },
  node_modules: "node_modules/",
};

const sassConfig = {
  outputStyle: "expanded",
  includePaths: [
    "node_modules/node-normalize-scss/",
    "node_modules/breakpoint-sass/stylesheets/",
    "font-awesome/scss/"
  ]
};

// -------------
// Compile SASS.
// -------------
function compileSASS() {
  return gulp
    .src(`${path.styles.src}**/*.scss`)
    .pipe(sassGlob())
    .pipe(
      path.env === "development"
        ? sass(sassConfig).on("error", function(err) {
          const chalk = require("chalk");
          const log = require("fancy-log");
          log.error(
            chalk.black.bgRed(
              " SASS ERROR",
              chalk.white.bgBlack(` ${err.message.split("  ")[2]} `)
            )
          );
          log.error(
            chalk.black.bgRed(
              " FILE:",
              chalk.white.bgBlack(` ${err.message.split("\n")[0]} `)
            )
          );
          log.error(
            chalk.black.bgRed(" LINE:", chalk.white.bgBlack(` ${err.line} `))
          );
          log.error(
            chalk.black.bgRed(
              " COLUMN:",
              chalk.white.bgBlack(` ${err.column} `)
            )
          );
          log.error(
            chalk.black.bgRed(
              " ERROR:",
              chalk.white.bgBlack(` ${err.formatted.split("\n")[0]} `)
            )
          );
          return this.emit("end");
        })
        : sass(sassConfig)
    )
    .pipe(autoprefix())
    .pipe(path.env === "production" ? cleanCss() : noop())
    .pipe(gulp.dest(path.styles.dist));
}


// ---------------------------------------------------
// Copy fonts from src to dist if necessary.
// ---------------------------------------------------
function copyFonts() {
  return gulp.src(`${path.fonts.src}**/*`).pipe(gulp.dest(path.fonts.dist));
}

// ---------------------------------------------------
// Copy js from src to dist and uglify if necessary.
// ---------------------------------------------------
function copyScripts() {
  return gulp
    .src(`${path.scripts.src}**/*.js`)
    .pipe(path.env === "production" ? uglify() : noop())
    .pipe(
      rename({
        suffix: ".min"
      })
    )
    .pipe(gulp.dest(path.scripts.dist));
}

// ---------------------------------------------------
// Copy images from src to dist if necessary.
// ---------------------------------------------------
function copyImages() {
  return gulp.src(`${path.images.src}**/*`).pipe(gulp.dest(path.images.dist));
}

// ----------------------
// Function to run watch.
// ----------------------
function runWatch() {
  gulp.watch(`${path.styles.src}**/*.scss`, compileSASS);
  gulp.watch(`${path.scripts.src}**/*.js`, copyScripts);
}

// -----------
// Watch task.
// -----------
gulp.task("watch", gulp.series(runWatch));

// -----------------------
// Function to clean dist.
// -----------------------
function cleandist() {
  console.log("Clean all files in dist folder");
  return clean(["./dist/"]);
}

// ------------------------------------------
// Helper function for selecting environment.
// ------------------------------------------
function environment(env) {
  console.log(`Running tasks in ${env} mode.`);
  return (path.env = env);
}

// --------------------------------------
// Set environment variable via dev task.
// --------------------------------------
gulp.task("dev", done => {
  environment("development");
  done();
});

// ---------------------------------------
// Set environment variable via prod task.
// ---------------------------------------
gulp.task("prod", done => {
  environment("production");
  done();
});

// -------------------------------------
// Build development css & scripts task.
// -------------------------------------
gulp.task(
  "development",
  gulp.series("dev", cleandist, compileSASS, copyFonts, copyScripts),
  done => {
    done();
  }
);

// -------------
// Default task.
// -------------
gulp.task("default", gulp.series("dev", gulp.parallel("watch")), done => {
  done();
});

// ----------------
// Production task.
// ----------------
gulp.task(
  "production",
  gulp.series(
    "prod",
    cleandist,
    compileSASS,
    gulp.parallel(copyFonts, copyScripts)
  ),
  done => {
    done();
  }
);
