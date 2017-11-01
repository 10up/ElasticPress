module.exports = function ( grunt ) {

	// Start out by loading the grunt modules we'll need
	require ( 'load-grunt-tasks' ) ( grunt );

	grunt.initConfig (
		{

			uglify : {

				production : {
					files : {
						'assets/js/dashboard.min.js' : [
							'assets/js/src/dashboard.js'
						],
						'assets/js/admin.min.js' : [
							'assets/js/src/admin.js'
						],
						'features/autosuggest/assets/js/autosuggest.min.js': [
							'features/autosuggest/assets/js/src/autosuggest.js'
						]
					}
				}

			},

			autoprefixer : {

				options : {
					browsers : ['last 5 versions'],
					map      : true
				},

				files: {
					'assets/css/admin.css': ['assets/css/admin.css'],
					'features/autosuggest/assets/css/autosuggest.min.css': ['features/autosuggest/assets/css/autosuggest.css']
				}

			},

			cssmin : {

				target : {

					files: {
						'assets/css/admin.min.css': ['assets/css/admin.css'],
						'features/autosuggest/assets/css/autosuggest.min.css': ['features/autosuggest/assets/css/autosuggest.css']
					}

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
						'assets/css/admin.css': 'assets/css/admin.scss',
						'features/autosuggest/assets/css/autosuggest.css': 'features/autosuggest/assets/css/autosuggest.scss'
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
						potHeaders: true,
						exclude: [ 'vendor', 'node_modules', 'tests' ]
					}
				}
			},

			watch : {

				options : {
					livereload : true
				},

				scripts : {
					files : [
						'assets/js/src/*.js',
						'features/autosuggest/assets/js/src/*.js'
					],
					tasks : ['uglify:production']

				},

				styles : {
					files : [
						'assets/css/*.scss',
						'features/autosuggest/assets/css/*.scss'
					],
					tasks : ['sass', 'autoprefixer', 'cssmin']
				}

			}

		}
	);

	// A very basic default task.
	grunt.registerTask ( 'default', ['uglify:production', 'sass', 'autoprefixer', 'cssmin', 'makepot'] );

};
