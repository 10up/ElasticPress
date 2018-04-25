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
						'assets/js/autosuggest.min.js': [
							'assets/js/src/autosuggest.js'
						],
						'assets/js/facets.min.js': [
							'assets/js/src/facets.js'
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
					'assets/css/autosuggest.min.css': ['assets/css/autosuggest.css'],
					'assets/css/facets.min.css': ['assets/css/facets.css'],
					'assets/css/facets-admin.min.css': ['assets/css/facets-admin.css']
				}

			},

			cssmin : {

				target : {

					files: {
						'assets/css/admin.min.css': ['assets/css/admin.css'],
						'assets/css/autosuggest.min.css': ['assets/css/autosuggest.css'],
						'assets/css/facets-admin.min.css': ['assets/css/facetsadmin.css'],
						'assets/css/facets.min.css': ['assets/css/facets.css']
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
						'assets/css/autosuggest.css': 'assets/css/autosuggest.scss',
						'assets/css/facets-admin.css': 'assets/css/facets-admin.scss',
						'assets/css/facets.css': 'assets/css/facets.scss'
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
