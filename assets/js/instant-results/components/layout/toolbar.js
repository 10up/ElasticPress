/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Search field component.
 *
 * @param {object} props Props.
 * @param {WPElement} props.children Children.
 * @returns {WPElement} Element.
 */
export default ({ children }) => {
	return <div className="ep-search-toolbar">{children}</div>;
};
