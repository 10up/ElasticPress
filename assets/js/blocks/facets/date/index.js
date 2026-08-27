/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import icon from '../common/icon';
import edit from './edit';
import block from './block.json';

/**
 * Register block.
 */
registerBlockType(block.name, {
	icon,
	edit,
	save: () => {},
});
