const gulp = require('gulp');
const git = require('gulp-git');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');
const jshint = require('gulp-jshint');
const jshintStylish = require('jshint-stylish');

const config = {
  scripts: [
    './assets/js/basic_config_mercadopago.js',
    './assets/js/basic-cho.js',
    './assets/js/credit-card.js',
    './assets/js/custom_config_mercadopago.js',
    './assets/js/ticket_config_mercadopago.js',
    './assets/js/ticket.js',
  ],
};

gulp.task('jshint', function() {
  return gulp.src(config.scripts)
    .pipe(jshint('.jshintrc'))
    .pipe(jshint.reporter(jshintStylish))
    .pipe(jshint.reporter('fail'));
});

gulp.task('scripts', function() {
  return gulp.src(config.scripts)
    .pipe(uglify())
    .pipe(rename({ extname: '.min.js' }))
    .pipe(gulp.dest('./assets/js/'));
});

gulp.task('git-add', function() {
  return gulp.src('.')
    .pipe(git.add());
});

gulp.task('pre-commit', gulp.series('jshint', 'scripts', 'git-add'));

gulp.task('default', gulp.series('scripts'));
