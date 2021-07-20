# laravel-mix-compress-image
Add a compress-images process for mix.

## how to

This modules adds a compressImages function to mix that call the compress_images tool
to compress images.

The function take 3 parameters : 
- pattern : the input patterns of the files you want to proceed (string or array)
- output : the output directory into the mix public path
- compressParameters: the compress parameters according to https://www.npmjs.com/package/compress-images

Ex.
```
let mix = require('laravel-mix');
require('laravel-mix-compress-images');


/**
 * The following code will create new optimized images files into the ../dist directory.
 *
 * ---------
 * Before :
 * - src/
 *	    - img/
 *	        - test.jpg
 *	        - png/
 *	            - test.png
 *
 * ---------
 * After :
 * - dist/
 *      - destination/
 *          - test.jpg
 * 	        - png/
 *          	- test.png
 * - src/
 *	    - img/
 *	        - test.jpg
 *	        - png/
 *	            test.png
 *
 *
 * As you can see, even png files will be processed because we did not specify
 * a rescrtive input pattern and compressImages has a default processor for png, jpg, svg, and gif.
*/


mix.setPublicPath('../dist');
mix
	.compressImages(
		['img\/**\/*'],
		'destination',
		{
			jpg:{
				engine: 'mozjpeg',
				command:['-quality', '20']
			}
		}
	);
```
