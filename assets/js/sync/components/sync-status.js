/**
 * WordPress dependencies.
 */
import { useContext, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { SyncContext } from '../context';
import Status from './common/status';

/**
 * Sync button component.
 *
 * @returns {WPElement} Component.
 */
export default () => {
	const { lastSyncDateTime, lastSyncFailed } = useContext(SyncContext);

	return (
		<Status
			datetime={lastSyncDateTime}
			isSuccess={!lastSyncFailed}
			label={
				lastSyncFailed
					? __('Sync unsuccessful on', 'elasticpress')
					: __('Sync success on', 'elasticpress')
			}
		/>
	);
};
