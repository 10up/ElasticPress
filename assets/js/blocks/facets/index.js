/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import edit from './edit';
import transforms from './transforms';
import block from './block.json';

registerBlockType(block, {
	edit,
	save: () => {},
	transforms,
});
