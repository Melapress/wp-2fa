var pkg = require('./package.json')
var gulp = require('gulp')
var del = require('del')
var replace = require('gulp-replace')
var removeLines = require('gulp-remove-lines')
var sort = require('gulp-sort')
var stripCode = require('gulp-strip-code')
var zip = require('gulp-zip')
var wpPot = require('gulp-wp-pot')

var pluginSlug = 'wp-2fa'
var pluginName = 'WP 2FA - Two-factor authentication for WordPress'
var teamEmail = 'info@wpwhitesecurity.com'
var teamName = 'WP White Security'
var teamWebsite = 'https://www.wpwhitesecurity.com'

/**
 * Generate translations.
 */
gulp.task('translate', function () {
	return gulp.src('./**/*.php')
		.pipe(sort())
		.pipe(wpPot({
			domain: pluginSlug,
			destFile: pluginSlug + '.pot',
			package: pluginSlug,
			bugReport: teamWebsite,
			lastTranslator: teamName + ' <' + teamEmail + '>',
			team: teamName + ' <' + teamEmail + '>'
		}))
		.pipe(gulp.dest('./languages/' + pluginSlug + '.pot'))
})

/**
 * Build release ZIP file
 */
gulp.task('zip', function () {
	return gulp.src([

		// Include
		'./**/*',

		// Exclude
		'!./assets',
		'!./assets/**/*',
		'!./tests',
		'!./tests/**/*',
		'!./bin',
		'!./bin/**/*',
		'!./build*.sh',
		'!./composer.*',
		'!./gulpfile.js',
		'!./js/src',
		'!./js/src/**/*.*',
		'!./node_modules',
		'!./node_modules/**/*',
		'!./package*.json',
		'!./php-scoper',
		'!./php-scoper/**/*',
		'!./prepros.cfg',
		'!./README.md',
		'!./readme.txt',
		'!./scoper.inc.php',
		'!./babel.config.js',
		'!./phpunit.xml',
		'!./postcss.config.js',
		'!./css/src',
		'!./css/src/**/*.*',
		'!./webpack*.js',

	])
		.pipe(zip(pkg.name + '.zip'))
		.pipe(gulp.dest('../'))
})

/**
 * Removes comment annotations
 */
gulp.task('remove-annotations', function () {
	return gulp.src('./**/*.php')
		.pipe(removeLines({
			'filters': [
				/@(free|premium):(start|end)/i,
			]
		}))
		.pipe(gulp.dest('.'))
})

/**
 * Removes code blocks that should only be present in the premium version of the plugin.
 */
gulp.task('remove-premium-only-code', function () {
	return gulp.src('./**/*.php')
		.pipe(stripCode({
			start_comment: '@premium:start',
			end_comment: '@premium:end'
		})).pipe(gulp.dest('.'))
})

/**
 * Removes files and folders that should only be present in the premium version of the plugin.
 */
gulp.task('remove-premium-only-files', function () {
	//	we cannot delete node_modules, package.json and gulpfile.js here because the build would fail, it is handled by rsync in the
	//	GitHub workflow
	return del([
		'./.*',
		'./assets',
		'./babel.config.js',
		'./bin',
		'./composer.*',
		'./css/src',
		'./js/src',
		'./php-scoper',
		'./phpunit.xml',
		'./postcss.config.js',
		'./prepros.cfg',
		'./README.md',
		'./scoper.inc.php',
		'./tests',
		'./webpack*.js',
	], {
		allowEmpty: true
	})
})

/**
 * Removes code blocks that should only be present in the free version of the plugin.
 */
gulp.task('remove-free-only-code', function () {
	return gulp.src('./**/*.php')
		.pipe(stripCode({
			start_comment: '@free:start',
			end_comment: '@free:end'
		})).pipe(gulp.dest('.'))
})

/**
 * Replaces the plugin name (removes the "Premium" part) in the main plugin file and in the readme.
 */
gulp.task('replace-plugin-name', function () {
	return gulp.src([
		'./' + pluginSlug + '.php',
		'./readme.txt'
	])
		.pipe(replace('* Plugin Name: ' + pluginName + ' (Premium)', '* Plugin Name: ' + pluginName))
		.pipe(replace('=== ' + pluginName + ' (Premium) ===', '=== ' + pluginName + ' ==='))
		.pipe(gulp.dest('.'))
})

/**
 * Converts the plugin to a free edition.
 */
gulp.task('convert-to-free-edition', gulp.series(['remove-premium-only-code', 'remove-premium-only-files', 'replace-plugin-name']))

/**
 * Replaces version number placeholder in case with the actual version number. The version number is read from package.json.
 */
gulp.task('replace-latest-version-numbers', function () {
	return gulp.src('./**/*.php')
		.pipe(replace(/@since\s+latest/g, '@since ' + pkg.version))
		.pipe(gulp.dest('.'))
})
