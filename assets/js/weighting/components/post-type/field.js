/**
 * Wordpress Dependencies.
 */
import { CheckboxControl } from '@wordpress/components';
import { useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isEqual } from 'lodash';

/**
 * Internal dependencies.
 */
import DeleteButton from '../common/delete-button';
import UndoButton from '../common/undo-button';
import WeightControl from '../common/weight-control';

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
	const { enabled, indexable, searchable, weight } = value;
	/**
	 * Is the current value different to the original.
	 */
	const isChanged = useMemo(
		() => !originalValue || !isEqual(originalValue, value),
		[originalValue, value],
	);

	/**
	 * Handle change of indexable.
	 *
	 * @param {boolean} indexable New indexable value.
	 * @returns {void}
	 */
	const onChangeIndexable = (indexable) => {
		onChange({ weight, searchable: false, indexable });
	};

	/**
	 * Handle change of indexable.
	 *
	 * @param {boolean} searchable New searchable value.
	 * @returns {void}
	 */
	const onChangeSearchable = (searchable) => {
		onChange({ weight, indexable: true, searchable });
	};

	/**
	 * Handle change of weighting.
	 *
	 * @param {number} weight New weight value.
	 * @returns {void}
	 */
	const onChangeWeight = (weight) => {
		onChange({ indexable: true, searchable: true, weight });
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
				<div className="ep-weighting-field__indexable">
					<CheckboxControl
						checked={enabled || indexable}
						label={__('Index', 'elsaticpress')}
						onChange={onChangeIndexable}
					/>
				</div>
				<div className="ep-weighting-field__searchable">
					<CheckboxControl
						checked={enabled || searchable}
						label={__('Searchable', 'elasticpress')}
						onChange={onChangeSearchable}
					/>
				</div>
				<div className="ep-weighting-field__weighting">
					<WeightControl
						disabled={!(enabled || searchable)}
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
