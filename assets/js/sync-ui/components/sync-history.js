/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useSync } from '../../sync';
import PreviousSync from './previous-sync';

/**
 * Delete checkbox component.
 *
 * @returns {WPElement} Sync page component.
 */
export default () => {
	const { syncHistory } = useSync();

	const previousSyncs = syncHistory.slice(0, 5);

	return (
		<ol className="ep-sync-history">
			{previousSyncs.map((s) => {
				return (
					<li key={s.start_date_time}>
						<PreviousSync
							failures={s.failed}
							method={s.method}
							stateDatetime={s.start_date_time}
							status={s.final_status}
							trigger={s.trigger}
						/>
					</li>
				);
			})}
		</ol>
	);
};
