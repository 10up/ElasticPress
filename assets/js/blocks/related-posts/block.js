import Edit from './Edit';

const { __ } = wp.i18n;

const { registerBlockType } = wp.blocks;

registerBlockType('elasticpress/related-posts', {
	title: __('Related Posts (ElasticPress)', 'elasticpress'),
	supports: {
		align: true,
	},
	category: 'widgets',
	attributes: {
		alignment: {
			type: 'string',
			default: 'none',
		},
		number: {
			type: 'number',
			default: 5,
		},
	},

	/**
	 * Handle edit
	 *
	 * @param {object} props Component properties
	 * @returns {object} <Edit {...props} />
	 */
	edit(props) {
		return <Edit {...props} />;
	},

	/**
	 * Handle save
	 *
	 * @returns {void}
	 */
	save() {
		return null;
	},
});
