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
	 * @param {object} requiresFields An object representing the required field values for rendering.
	 * The keys are field names, and the values are the required values.
	 * @returns {boolean} Returns `true` if the control should be rendered, otherwise `false`.
	 */
	const shouldRenderControl = (requiresFields) => {
		if (!requiresFields || Object.keys(requiresFields).length === 0) {
			return true;
		}

		return Object.entries(requiresFields).every(([fieldKey, requiredValue]) => {
			const actualValue = settings[feature]?.[fieldKey];
			const defaultValue = defaultSettings[fieldKey] ?? false;
			return actualValue === requiredValue ?? actualValue === defaultValue;
		});
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
			requires_features,
			requires_sync,
			requiresFields,
			type,
			fields,
		} = s;

		/**
		 * Skip rendering if the control should not be rendered based on requiresFields.
		 */
		if (!shouldRenderControl(requiresFields)) {
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
				requiresFeatures={requires_features}
				requiresSync={requires_sync}
				type={type}
				value={value}
				fields={fields}
			/>
		);
	});
};
