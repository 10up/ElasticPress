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
						'assets/js/elasticpress-admin.min.js' : [
							'assets/js/elasticpress-admin.js'
						],
						'assets/js/elasticpress-index-admin.min.js' : [
							'assets/js/elasticpress-index-admin.js'
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
					src     : ['assets/css/elasticpress.css'],
					dest    : 'assets/css'
				}

			},

			cssmin : {

				target : {

					files : [{
						expand : true,
						cwd    : 'assets/css',
						src    : ['elasticpress.css'],
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
						'assets/css/elasticpress.css'            : 'assets/css/elasticpress.scss',
					}

				}

			},

			pot : {

				options : {
					text_domain : 'elasticpress',
					dest        : 'lang/',
					keywords    : [ //WordPress localisation functions
						'__:1',
						'_e:1',
						'_x:1,2c',
						'esc_html__:1',
						'esc_html_e:1',
						'esc_html_x:1,2c',
						'esc_attr__:1',
						'esc_attr_e:1',
						'esc_attr_x:1,2c',
						'_ex:1,2c',
						'_n:1,2',
						'_nx:1,2,4c',
						'_n_noop:1,2',
						'_nx_noop:1,2,3c'
					]
				},

				files : {
					src    : ['**/*.php'], //Parse all php files
					expand : true
				}

			},

			watch : {

				options : {
					livereload : true
				},

				scripts : {

					files : [
						'assets/js/**/*'
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
	grunt.registerTask ( 'default', ['uglify:production', 'sass', 'autoprefixer', 'cssmin', 'pot'] );

};