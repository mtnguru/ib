var gulp = require('gulp');
var gutil = require('gulp-util');
var sass = require('gulp-sass')(require('sass'))
// var watch = require('gulp-watch');
var shell = require('gulp-shell');
var sourcemaps = require('gulp-sourcemaps');
var uglify = require('gulp-uglify');
var fs = require("fs");
var runSequence = require('run-sequence');
var config = require("./example.config");
var sassGlob = require('gulp-sass-glob');
var path = require('path');

/**
 * If config.js exists, load that config for overriding certain values below.
 */
function loadConfig() {
  if (fs.existsSync(__dirname + "/./config.js")) {
    config = {};
    config = require("./config");
  }

  return config;
}

loadConfig();

/**
 * This task generates CSS from all SCSS files and compresses them down.
 */
gulp.task('sass', function () {
  return gulp.src('./scss/**/*.scss')
    .pipe(sassGlob())
    .pipe(sourcemaps.init())
    .pipe(sass({
      noCache: true,
      outputStyle: "compressed",
      sourceMap: true,
      includePaths: [
        path.resolve(__dirname, 'node_modules/include-media/dist'),
        path.resolve(__dirname, 'node_modules'),
        './scss'
      ],
    })
    .on('error', function (err) {
      gutil.log(gutil.colors.red('[Sass Error]') + ' ' + err.messageFormatted);
      this.emit('end');                 // prevents gulp from crashing
    }))
    .pipe(sourcemaps.write('./maps'))
    .pipe(gulp.dest('./css'));
});

/**
 * Defines a task that triggers a Drush cache clear (css-js).
 */
gulp.task('drush:cc', function () {
  if (!config.drush.enabled) {
    return;
  }

  return gulp.src('', {read: false})
    .pipe(shell([
      config.drush.alias.css_js
    ]))
//  .pipe(notify({
//    title: "Caches cleared",
//    message: "Drupal CSS/JS caches cleared.",
//    onLast: true
//  }));
});

/**
 * Defines a task that triggers a Drush cache rebuild.
 */
gulp.task('drush:cr', function () {
  if (!config.drush.enabled) {
    return;
  }

  return gulp.src('', {read: false})
    .pipe(shell([
      config.drush.alias.cr
    ]))
//  .pipe(notify({
//    title: "Cache rebuilt",
//    message: "Drupal cache rebuilt.",
//    onLast: true
//  }));
});

/**
 * Combined tasks that are run synchronously specifically for twig template changes.
 */
gulp.task('flush', function() {
  runSequence('drush:cr', 'reload');
});

/**
 * Defines the watcher task.
 */
gulp.task('watch', function() {

  // watch scss for changes and clear drupal theme cache on change
//gulp.watch(['scss/**/*.scss'], gulp.parallel(['sass', 'drush:cc']));
  gulp.watch(['scss/**/*.scss'], gulp.parallel('sass'));
});

/**
 * Watch task that compiles Sass once before starting to watch
 */
gulp.task('watch', gulp.series('sass', function() {
  gulp.watch('scss/**/*.scss', gulp.parallel('sass'));
  // gulp.watch('scss/**/*.scss', gulp.parallel('sass', 'drush:cc')); // uncomment if needed
}));

