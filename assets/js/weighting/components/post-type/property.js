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
	const { indexable, searchable, weight } = value;

	/**
	 * Is the current value different to the original.
	 */
	const isChanged = useMemo(() => !isEqual(originalValue, value), [originalValue, value]);

	/**
	 * Handle change of indexable.
	 *
	 * @param {boolean} indexable New indexable value.
	 * @returns {void}
	 */
	const onChangeIndex = (indexable) => {
		onChange({ ...value, indexable, searchable: false });
	};

	/**
	 * Handle change of searchable.
	 *
	 * @param {boolean} searchable New searchable value.
	 * @returns {void}
	 */
	const onChangeSearchable = (searchable) => {
		onChange({ ...value, indexable: true, searchable });
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
	const onUndo = () => {
		onChange({ ...originalValue });
	};

	return (
		<div className="ep-weighting-property">
			<fieldset>
				<legend className="ep-weighting-property__name">{label}</legend>
				<div className="ep-weighting-property__indexable">
					<CheckboxControl
						label={__('Index', 'elsaticpress')}
						onChange={onChangeIndex}
						checked={indexable}
					/>
				</div>
				<div className="ep-weighting-property__searchable">
					<CheckboxControl
						label={__('Searchable', 'elsaticpress')}
						onChange={onChangeSearchable}
						checked={searchable}
					/>
				</div>
				<div className="ep-weighting-property__weighting">
					<WeightControl
						disabled={!searchable}
						value={weight}
						onChange={onChangeWeight}
					/>
				</div>
				<div className="ep-weighting-property__undo">
					<UndoButton
						disabled={!isChanged}
						label={__('Undo changes', 'elasticpress')}
						onClick={onUndo}
					/>
				</div>
			</fieldset>
		</div>
	);
};
