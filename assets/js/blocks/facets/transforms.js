/**
 * WordPress dependencies.
 */
import { createBlock } from '@wordpress/blocks';

/**
 * Facet widget block transforms.
 */
export default {
	from: [
		{
			type: 'block',
			blocks: ['core/legacy-widget'],
			isMatch: ({ idBase }) => idBase === 'ep-facet',
			transform: ({ instance }) => {
				const { title, ...attributes } = instance.raw;

				return [
					createBlock('core/heading', { content: title }),
					createBlock('elasticpress/facet', attributes),
				];
			},
		},
	],
};
