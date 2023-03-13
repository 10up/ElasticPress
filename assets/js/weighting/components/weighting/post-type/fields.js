/**
 * WordPress dependencies.
 */
import { Button, PanelRow, SelectControl, ToggleControl } from '@wordpress/components';
import { useMemo, useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Field from './field';

/**
 * Post type propertes component.
 *
 * @param {object} props Component props.
 * @param {object[]} props.fields Post type fields.
 * @param {boolean} props.isEditable Whether to display as an editable list.
 * @param {Function} props.onChange Change handler.
 * @param {object[]} props.originalValues Saved property values.
 * @param {object[]} props.values Current property values.
 * @returns {WPElement} Component element.
 */
export default ({ fields, isEditable, onChange, originalValues, values }) => {
	const [toAdd, setToAdd] = useState('');
	const [showFeatureFields, setShowFeatureFields] = useState(false);

	/**
	 * Weightable fields that can be added to the group, if it is editable.
	 *
	 * Fields that are automatically synced by features are excluded, while
	 * fields that have already been added are disabled.
	 */
	const availableFields = useMemo(() => {
		return fields
			.filter((f) => !f.used_by_feature)
			.map((f) => ({
				label: f.label,
				value: f.key,
				disabled: Object.prototype.hasOwnProperty.call(values, f.key),
			}));
	}, [fields, values]);

	/**
	 * Fields that are automatically synced by features.
	 */
	const featureFields = useMemo(() => {
		return fields.filter((f) => f.used_by_feature);
	}, [fields]);

	/**
	 * Fields that can be weighted.
	 *
	 * For editable groups fields are sorted to match the order of the saved
	 * configuration, to preserve the order in which the fields were added.
	 */
	const weightedFields = useMemo(() => {
		return fields
			.filter((f) => !f.used_by_feature)
			.filter((f) => Object.prototype.hasOwnProperty.call(values, f.key))
			.sort((a, b) => {
				const { key: aKey } = a;
				const { key: bKey } = b;

				const keys = Object.keys(values);

				return keys.indexOf(aKey) - keys.indexOf(bKey);
			});
	}, [fields, values]);

	/**
	 * Handle changes to a property.
	 *
	 * @param {object} value New property data.
	 * @param {number} key Property key.
	 * @returns {void}
	 */
	const onChangeProperty = (value, key) => {
		onChange({ ...values, [key]: value });
	};

	/**
	 * Handle selecting a new property to enable.
	 *
	 * @param {string} key Key of property to enable.
	 * @returns {void}
	 */
	const onChangeToAdd = (key) => {
		setToAdd(key);
	};

	/**
	 * Handle a change to whether fields automatically synced by features are
	 * shown.
	 *
	 * @param {boolean} showFeatureFields Whether to show fields synced by features.
	 * @returns {void}
	 */
	const onChangeShowFeatureFields = (showFeatureFields) => {
		setShowFeatureFields(showFeatureFields);
	};

	/**
	 * Handle clicking to add a new property.
	 *
	 * @returns {void}
	 */
	const onClickAdd = () => {
		const newValues = { ...values, [toAdd]: { enabled: false, weight: 0 } };

		onChange(newValues);
		setToAdd('');
	};

	/**
	 * Handle removing a field.
	 *
	 * @param {number} key field key.
	 * @returns {void}
	 */
	const onDeleteField = (key) => {
		const newValues = { ...values };

		delete newValues[key];

		onChange(newValues);
	};

	/**
	 * Render.
	 */
	return (
		<>
			{featureFields.length > 0 ? (
				<PanelRow>
					<ToggleControl
						checked={showFeatureFields}
						label={__('Show all fields that are synced automatically', 'elasticpress')}
						onChange={onChangeShowFeatureFields}
					/>
				</PanelRow>
			) : null}
			{featureFields
				.filter((f) => showFeatureFields || values?.[f.key]?.enabled === true)
				.map(({ key, label }) => (
					<PanelRow key={key}>
						<Field
							label={label}
							originalValue={originalValues[key] || {}}
							value={values[key] || {}}
							onChange={(value) => {
								onChangeProperty(value, key);
							}}
						/>
					</PanelRow>
				))}
			{(isEditable ? weightedFields : fields).map(({ key, label }) => (
				<PanelRow key={key}>
					<Field
						label={label}
						originalValue={originalValues[key] || {}}
						value={values[key] || {}}
						onChange={(value) => {
							onChangeProperty(value, key);
						}}
						onDelete={
							isEditable
								? () => {
										onDeleteField(key);
								  }
								: null
						}
					/>
				</PanelRow>
			))}
			{isEditable && availableFields ? (
				<PanelRow className="ep-weighting-add-new">
					<SelectControl
						isSmall
						labelPosition="side"
						onChange={onChangeToAdd}
						options={[
							{ value: '', label: 'Select field to sync', disabled: true },
							...availableFields,
						]}
						value={toAdd}
					/>
					&nbsp;
					<Button disabled={!toAdd} isSecondary onClick={onClickAdd}>
						{__('Add field', 'elasticpress')}
					</Button>
				</PanelRow>
			) : null}
		</>
	);
};
