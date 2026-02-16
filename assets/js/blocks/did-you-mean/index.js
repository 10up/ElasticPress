/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { search } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import Edit from './Edit';
import { name } from './block.json';

registerBlockType(name, {
	icon: search,
	edit: () => <Edit />,
	save: () => null,
});
