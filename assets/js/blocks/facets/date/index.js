/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import icon from '../common/icon';
import edit from './edit';
import { name } from './block.json';

/**
 * Register block.
 */
registerBlockType(name, {
	icon,
	edit,
	save: () => {},
});
