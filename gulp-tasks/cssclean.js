import gulp from 'gulp';
import del from 'del';

gulp.task( 'cssclean', () => {
	del( ['./dist/*.css'] );
} );