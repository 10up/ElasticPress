/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import icon from './icon';
import { name } from './block.json';
import edit from './edit';
import transforms from './transforms';

/**
 * Register block.
 */
registerBlockType(name, {
	icon,
	edit,
	save: () => {},
	transforms,
});
