/**
 * WordPress dependencies.
 */
import { useContext, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Context from '../../context';

/**
 * Search field component.
 *
 * @param {object}    props          Props.
 * @param {WPElement} props.children Children.
 * @returns {WPElement} Element.
 */
export default ({ children }) => {
	const {
		state: { isSidebarOpen },
	} = useContext(Context);

	return (
		<aside className={`ep-search-sidebar ${isSidebarOpen ? 'is-open' : ''}`}>{children}</aside>
	);
};
