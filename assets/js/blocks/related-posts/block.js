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
	 * @param {Object} props Component properties
	 * @return {Object} <Edit {...props} />
	 */
	edit(props) {
		return <Edit {...props} />;
	},

	/**
	 * Handle save
	 *
	 * @return {void}
	 */
	save() {
		return null;
	},
});
