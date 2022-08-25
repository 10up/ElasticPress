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
				const { title = null, num_posts: number } = instance.raw;

				if (!title) {
					return createBlock('elasticpress/related-posts', { number });
				}

				return [
					createBlock('core/heading', { content: title }),
					createBlock('elasticpress/related-posts', { number }),
				];
			},
		},
	],
};
