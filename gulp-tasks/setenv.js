import gulp from 'gulp';

gulp.task( 'set-prod-node-env', ( cb ) => {
	process.env.NODE_ENV = 'production';
	cb();
} );
