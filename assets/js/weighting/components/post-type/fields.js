/**
 * WordPress dependencies.
 */
import { Button, PanelBody, PanelRow, SelectControl } from '@wordpress/components';
import { useMemo, useState, WPElement } from '@wordpress/element';

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
 * @param {string} props.label Properties label.
 * @param {Function} props.onChange Change handler.
 * @param {object[]} props.originalValues Saved property values.
 * @param {object[]} props.values Current property values.
 * @returns {WPElement} Component element.
 */
export default ({ fields, isEditable, label, onChange, originalValues, values }) => {
	const [toAdd, setToAdd] = useState('');

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
	 * Handle removing a property.
	 *
	 * @param {number} key Property key.
	 * @returns {void}
	 */
	const onDeleteProperty = (key) => {
		const newValues = { ...values };

		delete newValues[key];

		onChange(newValues);
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
	 * Handle clicking to add a new property.
	 *
	 * @returns {void}
	 */
	const onClickAdd = () => {
		const value = { indexable: true, searchable: false, weight: 1 };
		const newValues = { ...values, [toAdd]: value };

		onChange(newValues);
		setToAdd('');
	};

	/**
	 * Weightable fields that can be added to the group, if it is editable.
	 */
	const availableFields = useMemo(() => {
		return isEditable
			? Object.values(fields).map((p) => ({
					label: p.label,
					value: p.key,
					disabled: values?.[p.key]?.enable || values?.[p.key]?.indexable,
			  }))
			: null;
	}, [isEditable, fields, values]);

	/**
	 * Fields that can be weighted.
	 *
	 * For editable groups fields are sorted to match the order of the saved
	 * configuration, to preserve the order in which the fields were added.
	 */
	const weightableFields = useMemo(() => {
		const weightableFields = Object.values(fields);

		return isEditable
			? weightableFields.sort((a, b) => {
					const { key: aKey } = a;
					const { key: bKey } = b;

					const keys = Object.keys(values);

					return keys.indexOf(aKey) - keys.indexOf(bKey);
			  })
			: weightableFields;
	}, [isEditable, fields, values]);

	/**
	 * Render.
	 */
	return (
		<PanelBody title={label}>
			{weightableFields
				.filter((p) => values[p.key])
				.map(({ key, label }) => (
					<PanelRow key={key}>
						<Field
							label={label}
							originalValue={originalValues[key]}
							value={values[key]}
							onChange={(value) => {
								onChangeProperty(value, key);
							}}
							onDelete={
								isEditable
									? () => {
											onDeleteProperty(key);
									  }
									: null
							}
						/>
					</PanelRow>
				))}
			{availableFields ? (
				<PanelRow className="ep-weighting-add-meta">
					<SelectControl
						hideLabelFromVision
						labelPosition="side"
						onChange={onChangeToAdd}
						options={[
							{ value: '', label: 'Select field to index', disabled: true },
							...availableFields,
						]}
						value={toAdd}
					/>
					&nbsp;
					<Button disabled={!toAdd} isSecondary onClick={onClickAdd}>
						Add
					</Button>
				</PanelRow>
			) : null}
		</PanelBody>
	);
};
