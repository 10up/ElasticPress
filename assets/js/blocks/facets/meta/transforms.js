/**
 * WordPress dependencies.
 */
import { createBlock } from '@wordpress/blocks';

/**
 * Filter by Metadata block transforms.
 */
export default {
	from: [
		{
			type: 'block',
			blocks: ['elasticpress/facet-meta-range'],
			transform: (props) => {
				return createBlock('elasticpress/facet-meta', props);
			},
		},
	],
};
