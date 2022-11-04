/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Checkbox component.
 *
 * @param {Option} props       Component props.
 * @param {string} props.count Checkbox count.
 * @param {string} props.id    Checkbox ID.
 * @param {string} props.label Checkbox label.
 *
 * @returns {WPElement} Component element.
 */
export default ({ count, id, label, ...props }) => {
	let labelHTML = label;
	if (count) {
		labelHTML += ` <span class="ep-search-checkbox__count">${count}</span>`;
	}
	return (
		<div className="ep-search-checkbox">
			<input className="ep-search-checkbox__input" id={id} type="checkbox" {...props} />
			{/* eslint-disable-next-line jsx-a11y/label-has-associated-control */}
			<label
				className="ep-search-checkbox__label"
				htmlFor={id}
				// eslint-disable-next-line react/no-danger
				dangerouslySetInnerHTML={{ __html: labelHTML }}
			/>
		</div>
	);
};
