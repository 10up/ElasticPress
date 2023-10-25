/**
 * WordPress Dependencies.
 */
import { Button, CheckboxControl, RangeControl } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { trash } from '@wordpress/icons';

/**
 * Field settings component.
 *
 * @param {object} props Component props.
 * @param {string} props.label Property label.
 * @param {Function} props.onChange Change handler.
 * @param {Function} props.onDelete Delete handler.
 * @param {object} props.value Values.
 * @returns {WPElement} Component element.
 */
export default ({ label, onChange, onDelete, value }) => {
	const { enabled = false, weight = 0 } = value;

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
				<div className="ep-weighting-field__actions">
					<Button
						className="ep-weighting-action ep-weighting-action--delete"
						disabled={!onDelete}
						icon={trash}
						label={__('Remove', 'elasticpress')}
						onClick={onDelete}
					/>
				</div>
			</fieldset>
		</div>
	);
};
