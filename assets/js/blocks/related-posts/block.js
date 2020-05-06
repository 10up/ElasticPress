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
	 * @param {object} props - object of props
	 * @returns {object} block - edit block
	 */
	edit(props) {
		return <Edit {...props} />;
	},

	/**
	 * Handle save
	 *
	 * @returns {null} - null return so as not to save markup to db
	 */
	save() {
		return null;
	},
});
