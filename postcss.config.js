/**
 * Exports the PostCSS configuration.
 *
 * @return {string} PostCSS options.
 */
module.exports = ( { file, options, env } ) => ( { /* eslint-disable-line */
	plugins: {
		'postcss-import': {},
		'postcss-preset-env': {
			stage: 0,
			autoprefixer: {
				grid: true
			}
		},
		// Prefix editor styles with class `editor-styles-wrapper`.
		'postcss-editor-styles': 'editor-style.css' === file.basename ?
			{
				scopeTo: '.editor-styles-wrapper',
				ignore: [
					':root',
					'.edit-post-visual-editor.editor-styles-wrapper',
					'.wp-toolbar',
				],
				remove: [
					'html',
					':disabled',
					'[readonly]',
					'[disabled]',
				],
				tags: [
					'button',
					'input',
					'label',
					'select',
					'textarea',
					'form',
				],
			} : false,
		// Minify style on production using cssano.
		cssnano: {
			preset: [
				'default', {
					autoprefixer: false,
					calc: {
						precision: 8
					},
					convertValues: true,
					discardComments: {
						removeAll: true
					},
					mergeLonghand: false,
					zindex: false,
				},
			],
		}
	},
} );
