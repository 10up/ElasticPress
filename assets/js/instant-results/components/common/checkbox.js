/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Checkbox component.
 *
 * @param {Option} props Component props.
 * @param {string} props.id Checkbox ID.
 * @param {string} props.label Checkbox label.
 *
 * @return {WPElement} Component element.
 */
export default ({ id, label, ...props }) => {
	return (
		<div className="ep-search-checkbox">
			<input className="ep-search-checkbox__input" id={id} type="checkbox" {...props} />
			<label className="ep-search-checkbox__label" htmlFor={id}>
				{label}
			</label>
		</div>
	);
};
