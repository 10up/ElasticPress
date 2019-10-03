/* global module */

// Webpack settings exports.
module.exports = {
	entries: {
		// JS files.
		'admin-script': './assets/js/admin.js',
		'autosuggest-script': './assets/js/autosuggest.js',
		'dashboard-script': './assets/js/dashboard.js',
		'facets-script': './assets/js/facets.js',
		'sites-admin-script': './assets/js/sites-admin.js',
		'ordering-script': './assets/js/ordering/index.js',
		'stats-script': './assets/js/stats.js',
		'related-posts-block-script': './assets/js/blocks/related-posts/block.js',

		// CSS files.
		'dashboard-styles': './assets/css/dashboard.css',
		'facets-admin-styles': './assets/css/facets-admin.css',
		'facets-styles': './assets/css/facets.css',
		'autosuggest-styles': './assets/css/autosuggest.css',
		'sites-admin-styles': './assets/css/sites-admin.css',
		'ordering-styles': './assets/css/ordering.css',
		'related-posts-block-styles': './assets/css/related-posts-block.css'
	},
	filename: {
		js: 'js/[name].min.js',
		css: 'css/[name].min.css'
	},
	paths: {
		src: {
			base: './assets/',
			css: './assets/css/',
			js: './assets/js/'
		},
		dist: {
			base: './dist/',
			clean: ['./images', './css', './js']
		},
	},
	stats: {
		// Copied from `'minimal'`.
		all: false,
		errors: true,
		maxModules: 0,
		modules: true,
		warnings: true,
		// Our additional options.
		assets: true,
		errorDetails: true,
		excludeAssets: /\.(jpe?g|png|gif|svg|woff|woff2)$/i,
		moduleTrace: true,
		performance: true
	},
	copyWebpackConfig: {
		from: '**/*.{jpg,jpeg,png,gif,svg,eot,ttf,woff,woff2}',
		to: '[path][name].[ext]'
	},
	BrowserSyncConfig: {
		host: 'localhost',
		port: 3000,
		proxy: 'http://elasticpress.test',
		open: false,
		files: [
			'**/*.php',
			'dist/js/**/*.js',
			'dist/css/**/*.css',
			'dist/svg/**/*.svg',
			'dist/images/**/*.{jpg,jpeg,png,gif}',
			'dist/fonts/**/*.{eot,ttf,woff,woff2,svg}'
		]
	},
	performance: {
		maxAssetSize: 100000
	},
	manifestConfig: {
		basePath: ''
	},
};
