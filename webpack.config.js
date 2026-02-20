const defaultConfig = require('10up-toolkit/config/webpack.config');
const TerserPlugin = require('terser-webpack-plugin');

const isDev = process.env.NODE_ENV === 'development';

module.exports = {
	...defaultConfig,
	output: {
		...defaultConfig.output,
		filename: 'js/[name].js',
	},

	devtool: isDev ? 'source-map' : false,

	optimization: {
		...defaultConfig.optimization,
		minimizer: [
			new TerserPlugin({
				parallel: true,
				extractComments: false,
				terserOptions: {
					parse: {
						ecma: 8,
					},
					compress: {
						ecma: 5,
						warnings: false,
						comparisons: false,
						inline: 2,
					},
					output: {
						comments: false,
					},
				},
			}),
		],
	},
};
