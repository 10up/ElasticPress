/**
 * WordPress dependencies.
 */
import { dateI18n } from '@wordpress/date';
import { Fragment, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useSync } from '../../sync';

/**
 * Log messages component.
 *
 * @returns {WPElement} Component.
 */
export default () => {
	const { log } = useSync();

	return (
		<div className="ep-sync-messages">
			{log.map((m) => (
				<Fragment key={m.id}>
					<div className="ep-sync-messages__line-number" role="presentation">
						{dateI18n('Y-m-d H:i:s', m.dateTime)}
					</div>
					<div
						className={`ep-sync-messages__message ep-sync-messages__message--${m.status}`}
					>
						{m.message}
					</div>
				</Fragment>
			))}
		</div>
	);
};
