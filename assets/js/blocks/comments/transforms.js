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
			isMatch: ({ idBase }) => idBase === 'ep-comments',
			transform: ({ instance }) => {
				const { title = null, post_type: postTypes } = instance.raw;

				if (!title) {
					return createBlock('elasticpress/comments', { postTypes });
				}

				return [
					createBlock('core/heading', { content: title }),
					createBlock('elasticpress/comments', { postTypes }),
				];
			},
		},
	],
};
