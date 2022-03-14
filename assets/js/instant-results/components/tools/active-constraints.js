/**
 * WordPress dependencies.
 */
import { createSlotFill } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { closeSmall, Icon } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import SmallButton from '../common/small-button';

/**
 * Create SlotFill.
 */
const { Fill, Slot } = createSlotFill('ActiveContraints');

/**
 * Active filter component.
 *
 * @param {object}   props         Props.
 * @param {string}   props.label   Constraint label.
 * @param {Function} props.onClick Click handler.
 * @returns {WPElement} Element.
 */
export const ActiveContraint = ({ label, onClick }) => {
	return (
		<Fill>
			<SmallButton
				aria-label={sprintf(
					/* translators: %s: Constraint label. */
					__('Remove filter: %s', 'elasticpress'),
					label,
				)}
				className="ep-search-icon-button"
				onClick={onClick}
			>
				<Icon icon={closeSmall} />
				{label}
			</SmallButton>
		</Fill>
	);
};

/**
 * Active constraints component.
 *
 * @returns {WPElement} Element.
 */
export default () => {
	return <Slot>{(fills) => fills}</Slot>;
};
