/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';
import { Card, CardHeader, CardBody } from '@wordpress/components';

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

	const { isAvailable, defaultSettings, fieldGroups } = getFeature(feature);

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
			const defaultValue = defaultSettings[fieldKey] ?? false;
			const actualValue = settings[feature]?.[fieldKey] || defaultValue;
			return actualValue === requiredValue;
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

	// Helper to render a Control from a schema entry
	const renderControl = (s) => {
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
			fields,
		} = s;

		if (!shouldRenderControl(requires_fields)) {
			return null;
		}

		let value =
			typeof settings[feature]?.[key] !== 'undefined' ? settings[feature][key] : defaultValue;

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
				fields={fields}
			/>
		);
	};

	const rendered = [];
	let currentGroup = null;
	let groupEntries = [];
	let groupCounter = 0;

	const pushGroup = () => {
		if (groupEntries.length === 0) {
			return;
		}
		const group = fieldGroups[groupEntries[0].field_group_slug];
		if (!shouldRenderControl(group.requires_fields)) {
			return;
		}
		rendered.push(
			<div className="ep-field-group" key={`${currentGroup}-${groupCounter}`}>
				<Card>
					{group && (
						<CardHeader>
							<strong>{group.label}</strong>
						</CardHeader>
					)}
					<CardBody>{groupEntries.map(renderControl)}</CardBody>
				</Card>
			</div>,
		);
		groupEntries = [];
		groupCounter++;
	};

	settingsSchema.forEach((entry) => {
		const groupSlug = entry.field_group_slug;

		if (groupSlug && currentGroup !== groupSlug) {
			pushGroup();
			currentGroup = groupSlug;
		}

		if (groupSlug) {
			groupEntries.push(entry);
			return;
		}

		pushGroup();
		currentGroup = null;
		rendered.push(renderControl(entry));
	});

	pushGroup();

	if (rendered.length > 1) {
		return rendered;
	}
	if (rendered.length === 1) {
		return rendered[0];
	}
	return null;
};
