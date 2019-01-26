import gulp from 'gulp';
import del from 'del';

gulp.task( 'cssclean', ( cb ) => {
	del( ['./dist/*.css'] );
	cb();
} );
