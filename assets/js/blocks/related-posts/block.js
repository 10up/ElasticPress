/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Edit from './Edit';

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
	usesContext: ['postId'],

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
