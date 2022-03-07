const path = require('path');

module.exports = ( { file, options, env } ) => { /* eslint-disable-line */
	const config = {
		plugins: {
			'postcss-import': {},
			'postcss-mixins': {},
			'postcss-nesting': {},
			'postcss-preset-env': {
				stage: 0,
				autoprefixer: {
					grid: true,
				},
			},
		},
	};

	// Only load postcss-editor-styles plugin when we're processing the editor-style.css file.
	if (path.basename(file) === 'editor-style.css') {
		config.plugins['postcss-editor-styles'] = {
			scopeTo: '.editor-styles-wrapper',
			ignore: [':root', '.edit-post-visual-editor.editor-styles-wrapper', '.wp-toolbar'],
			remove: ['html', ':disabled', '[readonly]', '[disabled]'],
			tags: ['button', 'input', 'label', 'select', 'textarea', 'form'],
		};
	}

	config.plugins.cssnano =
		env === 'production'
			? {
					preset: [
						'default',
						{
							autoprefixer: false,
							calc: {
								precision: 8,
							},
							convertValues: true,
							discardComments: {
								removeAll: true,
							},
							mergeLonghand: false,
							// Added `mergeRules` here so sync.css can be properly created with `npm run build`
							mergeRules: false,
							zindex: false,
						},
					],
			  }
			: false;

	return config;
};
