import gulp from 'gulp';
import requireDir from 'require-dir';

requireDir( './gulp-tasks' );

gulp.task( 'js', gulp.series( 'webpack' ) );

gulp.task( 'watch', () => {
	process.env.NODE_ENV = 'development';

	gulp.watch( ['./assets/css/**/*.pcss'], gulp.series( 'css' ) );
	gulp.watch( './assets/js/**/*.js', gulp.series( 'js' ) );
} );

gulp.task( 'default', gulp.parallel( 'css', gulp.series( 'set-prod-node-env', 'webpack' ) ) );
