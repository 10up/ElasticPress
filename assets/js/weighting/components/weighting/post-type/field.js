/**
 * WordPress Dependencies.
 */
import { CheckboxControl, RangeControl } from '@wordpress/components';
import { useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isEqual } from 'lodash';

/**
 * Internal dependencies.
 */
import DeleteButton from '../../common/delete-button';
import UndoButton from '../../common/undo-button';

/**
 * Field settings component.
 *
 * @param {object} props Component props.
 * @param {string} props.label Property label.
 * @param {Function} props.onChange Change handler.
 * @param {Function} props.onDelete Delete handler.
 * @param {object} props.originalValue Original value.
 * @param {object} props.value Values.
 * @returns {WPElement} Component element.
 */
export default ({ label, onChange, onDelete, originalValue, value }) => {
	const { enabled = false, weight = 0 } = value;

	/**
	 * Is the current value different to the original.
	 */
	const isChanged = useMemo(
		() => !originalValue || !isEqual(originalValue, value),
		[originalValue, value],
	);

	/**
	 * Handle change of searchable.
	 *
	 * @param {boolean} enabled New searchable value.
	 * @returns {void}
	 */
	const onChangeSearchable = (enabled) => {
		onChange({ weight, enabled });
	};

	/**
	 * Handle change of weighting.
	 *
	 * @param {number} weight New weight value.
	 * @returns {void}
	 */
	const onChangeWeight = (weight) => {
		onChange({ enabled: true, weight });
	};

	/**
	 * Handle clicking undo.
	 *
	 * @returns {void}
	 */
	const onReset = () => {
		if (originalValue) {
			onChange(originalValue);
		} else if (onDelete) {
			onDelete();
		}
	};

	/**
	 * Render.
	 */
	return (
		<div className="ep-weighting-field">
			<fieldset>
				<legend className="ep-weighting-field__name">{label}</legend>
				<div className="ep-weighting-field__searchable">
					<CheckboxControl
						checked={enabled}
						label={__('Searchable', 'elasticpress')}
						onChange={onChangeSearchable}
					/>
				</div>
				<div className="ep-weighting-field__weighting">
					<RangeControl
						disabled={!enabled}
						label={__('Weight', 'elasticpress')}
						max={100}
						min={1}
						onChange={onChangeWeight}
						value={weight}
					/>
				</div>
				<div className="ep-weighting-field__undo">
					<DeleteButton
						disabled={!onDelete}
						label={__('Remove', 'elasticpress')}
						onClick={onDelete}
					/>
					<UndoButton
						disabled={!isChanged}
						label={__('Undo changes', 'elasticpress')}
						onClick={onReset}
					/>
				</div>
			</fieldset>
		</div>
	);
};
