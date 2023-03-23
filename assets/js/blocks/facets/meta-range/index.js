/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import block from './block.json';
import edit from './edit';
import transforms from './transforms';

/**
 * Register block.
 */
registerBlockType(block, {
	edit,
	save: () => {},
	transforms,
});
