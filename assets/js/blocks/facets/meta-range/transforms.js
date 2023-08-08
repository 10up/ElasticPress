/**
 * WordPress dependencies.
 */
import { createBlock } from '@wordpress/blocks';

/**
 * Filter by Metadata Range block transforms.
 */
export default {
	from: [
		{
			type: 'block',
			blocks: ['elasticpress/facet-meta'],
			transform: (props) => {
				return createBlock('elasticpress/facet-meta-range', props);
			},
		},
	],
};
