/**
 * External dependencies.
 */
import classnames from 'classnames';

/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Progress bar component.
 *
 * @param {object} props Component props.
 * @param {number} props.current Current value.
 * @param {number} props.total Current total.
 * @param {boolean} props.isComplete If operation is complete.
 * @param {boolean} props.isFailed If sync has failed.
 * @returns {WPElement} Component.
 */
export default ({ isComplete, isFailed, current, total }) => {
	const now = total ? Math.floor((current / total) * 100) : 100;

	return (
		<div
			aria-valuemax={100}
			aria-valuemin={0}
			aria-valuenow={now}
			className={classnames('ep-sync-progress-bar', {
				'ep-sync-progress-bar--complete': isComplete,
				'ep-sync-progress-bar--failed': isFailed,
			})}
			role="progressbar"
		>
			<div
				className="ep-sync-progress-bar__progress"
				style={{ minWidth: `${now}%` }}
			>{`${now}%`}</div>
		</div>
	);
};
