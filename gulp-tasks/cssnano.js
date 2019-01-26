import gulp from 'gulp';
import cssnano from 'gulp-cssnano';
import rename from 'gulp-rename';
import sourcemaps from 'gulp-sourcemaps';
import pump from 'pump';
import filter from 'gulp-filter';

gulp.task( 'cssnano', ( cb ) => {
	const fileDest = './dist/css',
		fileSrc = [
			'./dist/*.css'
		],
		taskOpts = {
			autoprefixer: false,
			calc: {
				precision: 8
			},
			zindex: false,
			convertValues: true,
			mergeLonghand: false,
		};

	pump( [
		gulp.src( fileSrc ),
		sourcemaps.init( {
			loadMaps: true
		} ),
		cssnano( taskOpts ),
		rename( function( path ) {
			path.extname = '.min.css';
		} ),
		sourcemaps.write( './' ),
		gulp.dest( fileDest ),
		filter( '**/*.css' )
	], cb );
} );
