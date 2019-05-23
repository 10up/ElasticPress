import gulp from 'gulp';
import rename from 'gulp-rename';
import postcss from 'gulp-postcss';
import cssnano from 'cssnano';
import atImport from 'postcss-import';
import presetEnv from 'postcss-preset-env';
import sourcemaps from 'gulp-sourcemaps';
import pump from 'pump';

gulp.task( 'css', ( cb ) => {
	const fileSrc = [
		'./assets/css/dashboard.pcss',
		'./assets/css/facets-admin.pcss',
		'./assets/css/facets.pcss',
		'./assets/css/autosuggest.pcss'
	];
	const fileDest = './dist/css';

	const cssOpts = {
		stage: 0,
		autoprefixer: {
			grid: true
		}
	};

	const cssNanoOpts = {
		autoprefixer: false,
		calc: {
			precision: 8
		},
		zindex: false,
		convertValues: true,
		mergeLonghand: false,
	};

	const taskOpts = [
		atImport,
		presetEnv( cssOpts ),
		cssnano( cssNanoOpts ),
	];

	pump( [
		gulp.src( fileSrc ),
		rename( {
			extname: '.min.css'
		} ),
		sourcemaps.init( {
			loadMaps: true
		} ),
		postcss( taskOpts ),
		sourcemaps.write( '.' ),
		gulp.dest( fileDest )
	], cb );
} );
