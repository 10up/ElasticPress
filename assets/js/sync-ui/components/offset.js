/**
 * WordPress dependencies.
 */
import { TextControl } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSync } from '../../sync';
import { useSyncSettings } from '../provider';

/**
 * Delete checkbox component.
 *
 * @returns {WPElement} Sync page component.
 */
export default () => {
	const { isSyncing } = useSync();
	const { args, setArgs } = useSyncSettings();

	return (
		<TextControl
			className="ep-sync-advanced-control"
			disabled={isSyncing}
			help={__('Skip this many objects when syncing.', 'elasticpress')}
			label={__('Skip objects', 'elasticpress')}
			onChange={(offset) => setArgs({ ...args, offset })}
			type="number"
			value={args.offset}
		/>
	);
};
