/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import { name } from './block.json';
import Edit from './edit';

registerBlockType(name, {
	edit: (props) => <Edit {...props} />,
	save: () => {},
});
