/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Search field component.
 *
 * @param {object} props Props.
 * @param {WPElement} props.children Children.
 * @param {boolean} props.isOpen Is sidebar open?
 * @returns {WPElement} Element.
 */
export default ({ children, isOpen }) => {
	return <aside className={`ep-search-sidebar ${isOpen ? 'is-open' : ''}`}>{children}</aside>;
};
