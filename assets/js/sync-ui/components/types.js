/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useSyncSettings } from '../provider';
import Indexables from './indexables';
import PostTypes from './post-types';

/**
 * Delete checkbox component.
 *
 * @returns {WPElement} Sync page component.
 */
export default () => {
	const { args } = useSyncSettings();

	return (
		<>
			<Indexables />
			{!args.indexables.length || args.indexables.includes('post') ? <PostTypes /> : null}
		</>
	);
};
