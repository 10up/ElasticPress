import gulp from 'gulp';
import pump from 'pump';
import webpack from 'webpack';
import webpackStream from 'webpack-stream';
import livereload from 'gulp-livereload';

function processWebpack( src, conf, dest, cb ) {
	pump( [
		gulp.src( src ),
		webpackStream( require( conf ), webpack ),
		gulp.dest( dest ),
		livereload()
	], cb );
}

gulp.task( 'webpack', () => {
	const src = '../assets/js/**/*.js';
	const conf = '../webpack.config.babel.js';
	const dest = './dist/js';
	processWebpack( src, conf, dest );
} );
