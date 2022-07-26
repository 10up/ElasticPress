/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import Edit from './Edit';
import block from './block.json';

registerBlockType(block, {
	edit: (props) => <Edit {...props} />,
	save: () => {},
});
