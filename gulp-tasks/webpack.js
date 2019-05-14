import gulp from 'gulp';
import pump from 'pump';
import webpack from 'webpack';
import webpackStream from 'webpack-stream';

function processWebpack( src, conf, dest, cb ) {
	pump( [
		gulp.src( src ),
		webpackStream( require( conf ), webpack ),
		gulp.dest( dest )
	], cb );
}

gulp.task( 'webpack', ( cb ) => {
	const src = '../assets/js/**/*.js';
	const conf = '../webpack.config.babel.js';
	const dest = './dist/js';
	processWebpack( src, conf, dest );
	cb();
} );
