/**
 * WordPress dependencies.
 */
import { PanelBody, PanelRow } from '@wordpress/components';
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Property from './property';

/**
 * Post type propertes component.
 *
 * @param {object} props Component props.
 * @param {string} props.label Properties label.
 * @param {Function} props.onChange Change handler.
 * @param {object[]} props.originalValue Saved property values.
 * @param {object[]} props.value Current property values.
 * @returns {WPElement} Component element.
 */
export default ({ label, onChange, originalValue, value }) => {
	/**
	 * Handle changes to a property.
	 *
	 * @param {object} property New property data.
	 * @param {number} index Index of the changed property.
	 * @returns {void}
	 */
	const onChangeProperty = (property, index) => {
		const newValue = [...value];

		newValue[index] = { ...value[index], ...property };

		onChange(newValue);
	};

	return (
		<PanelBody title={label}>
			{value.map((value, index) => (
				<PanelRow key={value.name}>
					<Property
						label={value.label}
						originalValue={originalValue[index]}
						value={value}
						onChange={(value) => {
							onChangeProperty(value, index);
						}}
					/>
				</PanelRow>
			))}
		</PanelBody>
	);
};
