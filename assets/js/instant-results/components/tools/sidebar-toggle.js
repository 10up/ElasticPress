/**
 * WordPress deendencies.
 */
import { useContext, WPElement } from '@wordpress/element';
import { chevronDown, chevronUp, Icon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Internal deendencies.
 */
import Context from '../../context';

/**
 * Open sidebar component.
 *
 * @returns {WPElement} Element.
 */
export default () => {
	const {
		state: { isSidebarOpen },
		dispatch,
	} = useContext(Context);

	/**
	 * Handle click.
	 */
	const onClick = () => {
		dispatch({ type: 'TOGGLE_SIDEBAR' });
	};

	return (
		<button
			aria-expanded={isSidebarOpen}
			className="ep-search-sidebar-toggle ep-search-icon-button"
			onClick={onClick}
			type="button"
		>
			{isSidebarOpen
				? __('Close filters', 'elasticpress')
				: __('All filters', 'elasticoress')}

			<Icon icon={isSidebarOpen ? chevronUp : chevronDown} />
		</button>
	);
};
