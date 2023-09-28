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

	return (
		<ol className="ep-sync-history">
			{syncHistory.map((s) => {
				return (
					<li>
						<PreviousSync
							failed={s.failed}
							method={s.method}
							stateDatetime={s.start_date_time}
							trigger={s.trigger}
						/>
					</li>
				);
			})}
		</ol>
	);
};
