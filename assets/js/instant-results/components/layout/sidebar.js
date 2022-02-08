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
 * @param {Object}    props          Props.
 * @param {WPElement} props.children Children.
 * @return {WPElement} Element.
 */
export default ({ children }) => {
	const {
		state: { isSidebarOpen },
	} = useContext(Context);

	return (
		<aside className={`ep-search-sidebar ${isSidebarOpen ? 'is-open' : ''}`}>{children}</aside>
	);
};
