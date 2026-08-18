/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { chevronDown, chevronUp, Icon } from '../../../utils/icons';

/**
 * Open sidebar component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.isOpen Is the sidebar open?
 * @param {Function} props.onClick Click handler.
 * @returns {WPElement} Element.
 */
export default ({ isOpen, onClick }) => {
	return (
		<button
			aria-expanded={isOpen}
			className="ep-search-sidebar-toggle ep-search-icon-button"
			onClick={onClick}
			type="button"
		>
			{isOpen ? __('Close filters', 'elasticpress') : __('All filters', 'elasticpress')}
			<Icon icon={isOpen ? chevronUp : chevronDown} />
		</button>
	);
};
