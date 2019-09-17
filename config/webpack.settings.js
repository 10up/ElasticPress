/* global module */

// Webpack settings exports.
module.exports = {
	entries: {
		// JS files.
		admin: './assets/js/admin.js',
		autosuggest: './assets/js/autosuggest.js',
		dashboard: './assets/js/dashboard.js',
		facets: './assets/js/facets.js',
		sites_admin: './assets/js/sites-admin.js',
		ordering: './assets/js/ordering/index.js',
		stats: './assets/js/stats.js',
		related_posts_block: './assets/js/blocks/related-posts/block.js',

		// CSS files.
		dashboard: './assets/css/dashboard.css',
		'facets-admin': './assets/css/facets-admin.css',
		facets: './assets/css/facets.css',
		autosuggest: './assets/css/autosuggest.css',
		'sites-admin': './assets/css/sites-admin.css',
		ordering: './assets/css/ordering.css',
		'related-posts-block': './assets/css/related-posts-block.css'
	},
	filename: {
		js: 'js/[name].js',
		css: 'css/[name].css'
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
