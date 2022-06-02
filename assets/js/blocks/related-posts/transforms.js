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
			isMatch: ({ idBase }) => idBase === 'ep-related-posts',
			transform: ({ instance }) => {
				const { title, num_posts: number } = instance.raw;

				return [
					createBlock('core/heading', { content: title }),
					createBlock('elasticpress/related-posts', { number }),
				];
			},
		},
	],
};
