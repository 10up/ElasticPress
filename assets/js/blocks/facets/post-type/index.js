/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import icon from '../common/icon';

/**
 * Internal dependencies.
 */
import edit from './edit';
import { name } from './block.json';

registerBlockType(name, {
	edit,
	save: () => {},
	icon,
});
