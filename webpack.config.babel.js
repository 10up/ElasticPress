import path from 'path';
import webpack from 'webpack';

const DIST_PATH = path.resolve( './dist/js' );

const config = {
	cache: true,
	entry: {
		admin: './assets/js/admin.js',
		autosuggest: './assets/js/autosuggest.js',
		dashboard: './assets/js/dashboard.js',
		facets: './assets/js/facets.js',
		sites_admin: './assets/js/sites-admin.js',
		ordering: './assets/js/ordering/index.js'
		stats: './assets/js/stats.js',
		related_posts_block: './assets/js/blocks/related-posts/block.js'
	},
	output: {
		path: DIST_PATH,
		filename: '[name].min.js',
	},
	resolve: {
		modules: ['node_modules'],
	},
	devtool: 'source-map',
	module: {
		rules: [
			{
				test: /\.js$/,
				enforce: 'pre',
				loader: 'eslint-loader',
				query: {
					configFile: './.eslintrc'
				}
			},
			{
				test: /\.js$/,
				use: [{
					loader: 'babel-loader',
					options: {
						babelrc: true,
					}

				}]
			}
		]
	},
	mode: process.env.NODE_ENV,
	plugins: [
		new webpack.NoEmitOnErrorsPlugin(),
	],
	stats: {
		colors: true
	},
	externals: {
		jquery: 'jQuery',
		underscores: '_',
		window: 'window'
	}
};

module.exports = config;
