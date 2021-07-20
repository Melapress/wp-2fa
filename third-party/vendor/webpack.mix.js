let mix = require('laravel-mix')

require('laravel-mix-imagemin')

mix.setPublicPath('dist')

mix.options({
	processCssUrls: false
})

mix.copy([
	'./node_modules/micromodal/dist/micromodal.js',
	'./node_modules/micromodal/dist/micromodal.min.js'
], './dist/js')

mix.scripts([
	'./assets/js/admin/settings.js',
	'./assets/js/admin/common.js',
	'./assets/js/admin/select2control.js'
], 'dist/js/admin.js')

mix.scripts('./assets/js/admin/multi-site-select.js', 'dist/js/multi-site-select.js')
mix.scripts('./assets/js/admin/common.js', 'dist/js/wp-2fa.js')

mix.sass('./assets/css/admin-style.scss', 'dist/css/admin-style.css')
mix.sass('./assets/css/setup-wizard.scss', 'dist/css/setup-wizard.css')
mix.sass('./assets/css/common.scss', 'dist/css/styles.css')

mix.imagemin({
	from: 'images/**.*',
	publicPath: 'dist'
}, {
	context: 'assets'
})

mix.browserSync(
	{
		host: 'localhost',
		port: 3000,
		proxy: 'https://wp2fa.lndo.site',
		open: false,
		files: [
			'**/*.php',
			'dist/js/**/*.js',
			'dist/css/**/*.css',
			'dist/svg/**/*.svg',
			'dist/images/**/*.{jpg,jpeg,png,gif}',
			'dist/fonts/**/*.{eot,ttf,woff,woff2,svg}'
		]
	}
)
