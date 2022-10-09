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
import UndoButton from '../common/undo-button';
import WeightControl from '../common/weight-control';

/**
 * Property settings component.
 *
 * @param {object} props Component props.
 * @param {string} props.label Property label.
 * @param {Function} props.onChange Change handler.
 * @param {object} props.originalValue Original value.
 * @param {object} props.value Values.
 * @returns {WPElement} Component element.
 */
export default ({ label, onChange, originalValue, value }) => {
	const { enabled = true, weight = 99 } = value;

	/**
	 * Is the current value different to the original.
	 */
	const isChanged = useMemo(() => !isEqual(originalValue, value), [originalValue, value]);

	/**
	 * Handle change of indexable.
	 *
	 * @param {boolean} enabled New indexable value.
	 * @returns {void}
	 */
	const onChangeEnabled = (enabled) => {
		onChange({ ...value, enabled });
	};

	/**
	 * Handle change of weighting.
	 *
	 * @param {number} weight New weight value.
	 * @returns {void}
	 */
	const onChangeWeight = (weight) => {
		onChange({ ...value, weight });
	};

	/**
	 * Handle clicking undo.
	 *
	 * @returns {void}
	 */
	const onReset = () => {
		onChange(originalValue);
	};

	return (
		<div className="ep-weighting-property">
			<fieldset>
				<legend className="ep-weighting-property__name">{label}</legend>
				<div className="ep-weighting-property__indexable">
					<CheckboxControl
						label={__('Index', 'elsaticpress')}
						onChange={onChangeEnabled}
						checked={enabled}
					/>
				</div>
				<div className="ep-weighting-property__searchable">
					<CheckboxControl label={__('Searchable', 'elsaticpress')} />
				</div>
				<div className="ep-weighting-property__weighting">
					<WeightControl disabled={!enabled} value={weight} onChange={onChangeWeight} />
				</div>
				<div className="ep-weighting-property__undo">
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
