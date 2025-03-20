/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Checkbox component.
 *
 * @param {Option} props Component props.
 * @param {string} props.count Checkbox count.
 * @param {string} props.id Checkbox ID.
 * @param {string} props.label Checkbox label.
 * @param {Function} props.onChange Change event handler.
 *
 * @returns {WPElement} Component element.
 */
export default ({ count, disabled, id, label, onChange, ...props }) => {
	/**
	 * Handle change event if checkbox is not disabled.
	 *
	 * @param {Event} e Change event.
	 */
	const maybeOnChange = (e) => {
		if (!disabled) {
			onChange(e);
		}
	};

	return (
		<div className="ep-search-checkbox">
			<input
				aria-disabled={disabled}
				className="ep-search-checkbox__input"
				id={id}
				onChange={maybeOnChange}
				type="checkbox"
				{...props}
			/>{' '}
			<label className="ep-search-checkbox__label" htmlFor={id}>
				{label} {count && <span className="ep-search-checkbox__count">{count}</span>}
			</label>
		</div>
	);
};
