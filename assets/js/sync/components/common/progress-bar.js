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
 * @returns {WPElement} Component.
 */
export default ({ isComplete, current, total }) => {
	const now = Math.floor((current / total) * 100);

	return (
		<div
			aria-valuemax={100}
			aria-valuemin={0}
			aria-valuenow={now}
			className={`ep-sync-progress-bar ${isComplete ? `ep-sync-progress-bar--complete` : ``}`}
			role="progressbar"
		>
			<div
				className="ep-sync-progress-bar__progress"
				style={{ minWidth: `${now}%` }}
			>{`${now}%`}</div>
		</div>
	);
};
