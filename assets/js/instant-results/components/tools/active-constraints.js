/**
 * WordPress dependencies.
 */
import { createPortal, createRef, WPElement } from '@wordpress/element';
import { closeSmall, Icon } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import SmallButton from '../common/small-button';

/**
 * Create ref for portal.
 */
const ref = createRef();

/**
 * Active filter component.
 *
 * @param {object} props Props.
 * @param {string} props.label Constraint label.
 * @param {Function} props.onClick Click handler.
 * @returns {WPElement} Element.
 */
export const ActiveConstraint = ({ label, onClick }) => {
	if (!ref.current) {
		return null;
	}

	return createPortal(
		<SmallButton
			aria-label={sprintf(
				/* translators: %s: Filter term name. */
				__('Remove filter: %s', 'elasticpress'),
				label,
			)}
			className="ep-search-icon-button"
			onClick={onClick}
		>
			<Icon icon={closeSmall} />
			{label}
		</SmallButton>,
		ref.current,
	);
};

/**
 * Active constraints component.
 *
 * @returns {WPElement} Element.
 */
export default () => {
	return <div className="ep-search-tokens" ref={ref} />;
};
