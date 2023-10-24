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

	const { isAvailable } = getFeature(feature);

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
			type,
		} = s;

		/**
		 * Current control value. If no setting value is set, use the
		 * setting's default value.
		 */
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
