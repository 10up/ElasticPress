import { WPElement } from '@wordpress/element';

/**
 * Small button component.
 *
 * @param {object} props Props.
 * @param {WPElement} props.children Children.
 * @param {string} props.className Class attribute.
 * @returns {WPElement} Element.
 */
export default ({ children, className, ...props }) => {
	return (
		<button className={`ep-search-small-button ${className}`} type="button" {...props}>
			{children}
		</button>
	);
};
