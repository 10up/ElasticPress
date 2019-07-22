import gulp from 'gulp';
import postcss from 'gulp-postcss';
import sourcemaps from 'gulp-sourcemaps';
import pump from 'pump';

gulp.task( 'css', ( cb ) => {
	const fileSrc = [
		'./assets/css/dashboard.css',
		'./assets/css/facets-admin.css',
		'./assets/css/facets.css',
		'./assets/css/autosuggest.css',
		'./assets/css/ordering.css'
	];
	const fileDest = './dist';

	const cssOpts = {
		stage: 0,
		autoprefixer: {
			grid: true
		}
	};

	const taskOpts = [
		require( 'postcss-import' ),
		require( 'postcss-preset-env' )( cssOpts ),
	];

	pump( [
		gulp.src( fileSrc ),
		sourcemaps.init( {
			loadMaps: true
		} ),
		postcss( taskOpts ),
		sourcemaps.write( './css', {
			mapFile: function( mapFilePath ) {
				return mapFilePath.replace( '.css.map', '.min.css.map' );
			}
		} ),
		gulp.dest( fileDest )
	], cb );
} );
