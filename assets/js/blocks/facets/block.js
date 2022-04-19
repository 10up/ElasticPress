import Edit from './Edit';

const { registerBlockType } = wp.blocks;

registerBlockType('elasticpress/facet', {
	title: 'Facet (ElasticPress)',
	category: 'widgets',
	attributes: {},

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
