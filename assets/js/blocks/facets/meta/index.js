/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import icon from '../common/icon';
import edit from '../common/edit';
import block from './block.json';
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
