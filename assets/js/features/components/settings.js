/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useFeatureSettings } from '../provider';
import Control from './control';

/**
 * Feature settings component.
 *
 * @param {object} props Component props.
 * @param {string} props.feature Feature slug.
 * @param {Array} props.settingsSchema Feature settings schema.
 * @returns {WPElement} Feature settings component.
 */
export default ({ feature, settingsSchema }) => {
	const { getFeature, settings, setSettings, syncedSettings } = useFeatureSettings();

	const { isAvailable, defaultSettings } = getFeature(feature);

	/**
	 * Change event handler.
	 *
	 * @param {string} key Setting key.
	 * @param {string|boolean} value Setting value.
	 */
	const onChange = (key, value) => {
		setSettings({
			...settings,
			[feature]: {
				...settings[feature],
				[key]: value,
			},
		});
	};

	/**
	 * Determines whether a control should be rendered based on its requirements.
	 *
	 * @param {object} requires_fields An object representing the required field values for rendering.
	 * Can contain 'conditions' object with field requirements and 'relationship' key ('AND' or 'OR').
	 * @returns {boolean} Returns `true` if the control should be rendered, otherwise `false`.
	 */
	const shouldRenderControl = (requires_fields) => {
		if (!requires_fields || Object.keys(requires_fields).length === 0) {
			return true;
		}

		// Get field requirements from 'conditions' key
		let fieldRequirements;

		if (requires_fields.conditions) {
			fieldRequirements = Object.entries(requires_fields.conditions);
		}

		// If no actual field requirements, return true
		if (fieldRequirements.length === 0) {
			return true;
		}

		// Define the condition check function
		const checkCondition = ([fieldKey, requiredValue]) => {
			const actualValue = settings[feature]?.[fieldKey];
			const defaultValue = defaultSettings[fieldKey] ?? false;
			return actualValue === requiredValue ?? actualValue === defaultValue;
		};

		// Extract relationship type, default to 'AND'
		const relationship = (requires_fields.relationship || 'AND').toUpperCase();

		// Apply the appropriate logic based on relationship type
		switch (relationship) {
			case 'OR':
				return fieldRequirements.some(checkCondition);
			case 'AND':
			default:
				// Default to AND for any unexpected values
				return fieldRequirements.every(checkCondition);
		}
	};

	return settingsSchema.map((s) => {
		const {
			default: defaultValue,
			disabled,
			help,
			key,
			label,
			options,
			requires_feature,
			requires_sync,
			requires_fields,
			type,
		} = s;

		/**
		 * Skip rendering if the control should not be rendered based on requires_fields.
		 */
		if (!shouldRenderControl(requires_fields)) {
			return null;
		}

		let value =
			typeof settings[feature]?.[key] !== 'undefined' ? settings[feature][key] : defaultValue;

		/**
		 * If the feature is unavailable, the active toggle should be off.
		 */
		if (key === 'active' && !isAvailable) {
			value = false;
		}

		return (
			<Control
				disabled={disabled || !isAvailable}
				key={key}
				help={help}
				label={label}
				name={key}
				onChange={(value) => onChange(key, value)}
				options={options}
				syncedValue={syncedSettings?.[feature]?.[key]}
				requiresFeature={requires_feature}
				requiresSync={requires_sync}
				type={type}
				value={value}
			/>
		);
	});
};
