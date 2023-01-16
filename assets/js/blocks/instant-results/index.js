/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import facet from './facet/block.json';
import results from './results/block.json';
import search from './search/block.json';

registerBlockType(facet, {
	edit: () => {
		const blockProps = useBlockProps();

		return <div {...blockProps}>Facet</div>;
	},
	save: () => {},
});

registerBlockType(results, {
	edit: () => {
		const blockProps = useBlockProps();

		return <div {...blockProps}>Results</div>;
	},
	save: () => {},
});

registerBlockType(search, {
	edit: () => {
		const blockProps = useBlockProps();

		return <div {...blockProps}>Search</div>;
	},
	save: () => {},
});
