/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Search field component.
 *
 * @param {Object} props Props.
 * @param {WPElement} props.children Children.
 * @return {WPElement} Element.
 */
export default ({ children }) => {
	return <div className="ep-search-toolbar">{children}</div>;
};
