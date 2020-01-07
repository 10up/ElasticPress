/* global process, module, require */

const path = require( 'path' );
const CleanWebpackPlugin = require( 'clean-webpack-plugin' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const FixStyleOnlyEntriesPlugin = require( 'webpack-fix-style-only-entries' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const WebpackBar = require( 'webpackbar' );

// Config files.
const settings = require( './webpack.settings.js' );

/**
 * Configure entries.
 */
const configureEntries = () => {
	const entries = {};

	for ( const [ key, value ] of Object.entries( settings.entries ) ) {
		entries[ key ] = path.resolve( process.cwd(), value );
	}

	return entries;
};

module.exports = {
	entry: configureEntries(),
	output: {
		path: path.resolve( process.cwd(), settings.paths.dist.base ),
		filename: settings.filename.js,
	},

	// Console stats output.
	// @link https://webpack.js.org/configuration/stats/#stats
	stats: settings.stats,

	// External objects.
	externals: {
		jquery: 'jQuery',
		underscores: '_',
		window: 'window',
		lodash: {
			commonjs: 'lodash',
			amd: 'lodash',
			root: '_'
		}
	},

	// Performance settings.
	performance: {
		maxAssetSize: settings.performance.maxAssetSize,
	},

	// Build rules to handle asset files.
	module: {
		rules: [
			// Lint JS.
			{
				test: /\.js$/,
				enforce: 'pre',
				loader: 'eslint-loader',
				options: {
					fix: true
				}
			},

			// Scripts.
			{
				test: /\.js$/,
				exclude: /node_modules/,
				use: [
					{
						loader: 'babel-loader',
						options: {
							presets: [
								[ '@babel/preset-env', {
									'useBuiltIns': 'usage',
									'corejs': 3,
								} ]
							],
							cacheDirectory: true,
							sourceMap: true,
						},
					},
				],
			},

			// Styles.
			{
				test: /\.css$/,
				include: path.resolve( process.cwd(), settings.paths.src.css ),
				use: [
					{
						loader: MiniCssExtractPlugin.loader,
					},
					{
						loader: 'css-loader',
						options: {
							sourceMap: true,
							// We copy fonts etc. using CopyWebpackPlugin.
							url: false,
						},
					},
					{
						loader: 'postcss-loader',
						options: {
							sourceMap: true,
						},
					},
				],
			},
		],
	},

	plugins: [

		// Remove the extra JS files Webpack creates for CSS entries.
		// This should be fixed in Webpack 5.
		new FixStyleOnlyEntriesPlugin( {
			silent: true,
		} ),

		// Clean the `dist` folder on build.
		new CleanWebpackPlugin(),

		// Extract CSS into individual files.
		new MiniCssExtractPlugin( {
			filename: settings.filename.css,
			chunkFilename: '[id].css',
		} ),

		// Copy static assets to the `dist` folder.
		new CopyWebpackPlugin( [
			{
				from: settings.copyWebpackConfig.from,
				to: settings.copyWebpackConfig.to,
				context: path.resolve( process.cwd(), settings.paths.src.base ),
			},
		] ),

		// Fancy WebpackBar.
		new WebpackBar(),
	],
};
