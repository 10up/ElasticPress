import Edit from './Edit';

const { registerBlockType } = wp.blocks;

registerBlockType('elasticpress/related-posts', {
	title: 'Related Posts (ElasticPress)',
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
	 * @returns {*}
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
