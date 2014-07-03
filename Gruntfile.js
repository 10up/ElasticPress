module.exports = function ( grunt ) {
	grunt.initConfig( {
		pkg : grunt.file.readJSON( 'package.json' ),
		uglify : {
			js : {
				files : {
					'build/js/network-settings.min.js' : ['js/network-settings.js']
				}
			}
		},
		jshint : {
			options : {
				smarttabs : true
			}
		},
		watch : {
			files : [
				'js/*'
			],
			tasks : ['uglify']
		}
	} );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.registerTask( 'default', ['uglify:js'] );
};