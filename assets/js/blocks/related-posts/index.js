/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import icon from './icon';
import Edit from './Edit';
import block from './block.json';
import transforms from './transforms';

registerBlockType(block, {
	icon,
	edit: (props) => <Edit {...props} />,
	save: () => {},
	transforms,
});
