const mix = require('laravel-mix');

class MixCompressImages {
	name() {
		return ['CompressImage', 'images', 'img'];
	}

	dependencies() {
		return ['copy-webpack-plugin', 'compress-images'];
	}

	register(patterns, output, compressParameters = {}) {
		this.patterns = [].concat(patterns);
		this.output = output;
		this.compressParameters = compressParameters;
	}

	webpackPlugins() {
		const CompressImagesPlugin = require('./CompressImagesPlugin');
		const CopyWebpackPlugin = require('copy-webpack-plugin');

		return [
			new CopyWebpackPlugin(this.patterns, {}),
			new CompressImagesPlugin(this.patterns, this.output, {...this.compressParameters, ...{destination: mix.config.publicPath}}),
		];
	}
}

module.exports = MixCompressImages;
