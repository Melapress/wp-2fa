const mix = require('laravel-mix');
const CompressImages = require('./class/CompressImages');

mix.extend('compressImages', new CompressImages());