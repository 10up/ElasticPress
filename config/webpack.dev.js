/* global module, require */

const merge = require( 'webpack-merge' );
const common = require( './webpack.common.js' );
const BrowserSyncPlugin = require( 'browser-sync-webpack-plugin' );

// Config files.
const settings = require( './webpack.settings.js' );

module.exports = merge( common, {
	mode: 'development',
	devtool: 'inline-cheap-module-source-map',
	plugins: [
		// Run BrowserSync.
		new BrowserSyncPlugin(
			{
				host: settings.BrowserSyncConfig.host,
				port: settings.BrowserSyncConfig.port,
				proxy: settings.BrowserSyncConfig.proxy,
				open: settings.BrowserSyncConfig.open,
				files: settings.BrowserSyncConfig.files,
			},
			{
				injectCss: true,
				reload: false,
			}
		),
	],
} );
