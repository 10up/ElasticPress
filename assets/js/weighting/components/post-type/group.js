/**
 * WordPress dependencies.
 */
import { Button, PanelBody, PanelRow, SelectControl } from '@wordpress/components';
import { useMemo, useState, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Property from './property';

/**
 * Post type propertes component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.isEditable Whether to display as an editable list.
 * @param {string} props.label Properties label.
 * @param {Function} props.onChange Change handler.
 * @param {object[]} props.originalValues Saved property values.
 * @param {object[]} props.properties Post type properties.
 * @param {object[]} props.values Current property values.
 * @returns {WPElement} Component element.
 */
export default ({ isEditable, label, onChange, originalValues, properties, values }) => {
	const [toAdd, setToAdd] = useState('');

	/**
	 * Handle changes to a property.
	 *
	 * @param {object} value New property data.
	 * @param {number} key Property key.
	 * @returns {void}
	 */
	const onChangeProperty = (value, key) => {
		const newValues = { ...values, [key]: value };

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
		const value = { ...values[toAdd], enabled: true };
		const newValues = { ...values, [toAdd]: value };

		onChange(newValues);
		setToAdd('');
	};

	/**
	 * Options for properties that can be added to the list of weightable
	 * properties.
	 */
	const availableProperties = useMemo(() => {
		return isEditable
			? Object.values(properties).map((p) => ({
					label: p.label,
					value: p.key,
					disabled: values[p.key].enabled,
			  }))
			: null;
	}, [isEditable, properties, values]);

	/**
	 * Properties that can be weighted.
	 *
	 * If the component is set to be editable this will only be properties that
	 * are indexed, otherwise it will be all properties.
	 */
	const weightableProperties = useMemo(() => {
		return Object.values(properties).filter((p) => (isEditable ? values[p.key].enabled : true));
	}, [isEditable, properties, values]);

	return (
		<PanelBody title={label}>
			{weightableProperties.map(({ key, label }) => (
				<PanelRow key={key}>
					<Property
						label={label}
						originalValue={originalValues[key]}
						value={values[key]}
						onChange={(value) => {
							onChangeProperty(value, key);
						}}
					/>
				</PanelRow>
			))}
			{availableProperties ? (
				<PanelRow className="ep-weighting-add-meta">
					<SelectControl
						hideLabelFromVision
						labelPosition="side"
						onChange={onChangeToAdd}
						options={[
							{ value: '', label: 'Select metadata to index', disabled: true },
							...availableProperties,
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
