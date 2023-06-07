/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import icon from './icon';
import block from './block.json';
import edit from './edit';
import transforms from './transforms';

/**
 * Register block.
 */
registerBlockType(block, {
	icon,
	edit,
	save: () => {},
	transforms,
});
