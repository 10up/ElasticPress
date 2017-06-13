module.exports = function ( grunt ) {

	// Start out by loading the grunt modules we'll need
	require ( 'load-grunt-tasks' ) ( grunt );

	grunt.initConfig (
		{

			uglify : {

				production : {
					options : {
						beautify         : false,
						preserveComments : false,
						mangle           : {
							except : ['jQuery']
						}
					},

					files : {
						'assets/js/dashboard.min.js' : [
							'assets/js/src/dashboard.js'
						],
						'assets/js/admin.min.js' : [
							'assets/js/src/admin.js'
						]
					}
				}

			},

			autoprefixer : {

				options : {
					browsers : ['last 5 versions'],
					map      : true
				},

				files : {
					expand  : true,
					flatten : true,
					src     : ['assets/css/admin.css'],
					dest    : 'assets/css'
				}

			},

			cssmin : {

				target : {

					files : [{
						expand : true,
						cwd    : 'assets/css',
						src    : ['admin.css'],
						dest   : 'assets/css',
						ext    : '.min.css'
					}]

				}

			},

			sass : {

				dist : {

					options : {
						style     : 'expanded',
						sourceMap : true,
						noCache   : true
					},

					files : {
						'assets/css/admin.css'            : 'assets/css/admin.scss',
					}

				}

			},

			makepot: {
				main: {
					options: {
						domainPath: 'lang',
						mainFile: 'elasticpress.php',
						potFilename: 'elasticpress.pot',
						type: 'wp-plugin',
						potHeaders: true
					}
				}
			},

			watch : {

				options : {
					livereload : true
				},

				scripts : {
					files : [
						'assets/js/src/*.js'
					],
					tasks : ['uglify:production']

				},

				styles : {
					files : [
						'assets/css/*.scss'
					],
					tasks : ['sass', 'autoprefixer', 'cssmin']
				}

			}

		}
	);

	// A very basic default task.
	grunt.registerTask ( 'default', ['uglify:production', 'sass', 'autoprefixer', 'cssmin', 'makepot'] );

};
