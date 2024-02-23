/**
 * WordPress dependencies.
 */
import { Button, PanelRow, TextControl } from '@wordpress/components';
import { useMemo, useState, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSettingsScreen } from '../../settings-screen';
import { useWeightingSettings } from '../provider';
import Field from './field';

/**
 * Post type properties component.
 *
 * @param {object} props Component props.
 * @param {string} props.group Group.
 * @param {string} props.postType Post type.
 * @returns {WPElement} Component element.
 */
export default ({ group, postType }) => {
	const { createNotice } = useSettingsScreen();
	const { currentWeightingConfiguration, setWeightingForPostType, weightableFields } =
		useWeightingSettings();

	/**
	 * State.
	 */
	const [toAdd, setToAdd] = useState('');

	/**
	 * Saved weighting values for this group's post type.
	 */
	const values = currentWeightingConfiguration[postType];
	const { fields } = weightableFields.find((w) => w.key === postType);

	/**
	 * Whether this is the Metadata group.
	 */
	const isMetadata = group === 'ep_metadata';

	/**
	 * Fields that belong to this group.
	 */
	const defaultFields = useMemo(() => fields.filter((f) => f.group === group), [fields, group]);

	/**
	 * Custom fields.
	 *
	 * These are meta fields that have a saved weighting value but are not
	 * included in the list of weightable fields. This will be fields that
	 * were added manually using the UI.
	 */
	const customFields = useMemo(() => {
		if (!isMetadata) {
			return [];
		}

		const fieldKeys = fields.map(({ key }) => key);

		const customFields = Object.keys(values).reduce((customFields, key) => {
			if (fieldKeys.includes(key)) {
				return customFields;
			}

			const matches = key.match(/meta\.(?<label>.*)\.value/);

			if (!matches) {
				return customFields;
			}

			const { label } = matches.groups;

			customFields.push({
				key,
				label,
			});

			return customFields;
		}, []);

		return customFields;
	}, [fields, isMetadata, values]);

	/**
	 * Handle changes to a property.
	 *
	 * @param {object} value New property data.
	 * @param {number} key Property key.
	 * @returns {void}
	 */
	const onChange = (value, key) => {
		setWeightingForPostType(postType, { ...values, [key]: value });
	};

	/**
	 * Handle clicking to add a new property.
	 *
	 * @returns {void}
	 */
	const onClick = () => {
		const key = `meta.${toAdd}.value`;

		const isDefaultField = defaultFields.some((f) => f.key === key);
		const isCustomField = customFields.some((f) => f.key === key);

		if (isDefaultField || isCustomField) {
			/* translators: Field name */
			createNotice('info', sprintf(__('%s is already being synced.', 'elasticpress'), toAdd));
			return;
		}

		const newValues = { ...values, [key]: { enabled: false, weight: 0 } };

		setWeightingForPostType(postType, newValues);
		setToAdd('');
	};

	/**
	 * Handle removing a field.
	 *
	 * @param {number} key field key.
	 * @returns {void}
	 */
	const onDelete = (key) => {
		const newValues = { ...values };

		delete newValues[key];

		setWeightingForPostType(postType, newValues);
	};

	/**
	 * Handle pressing Enter key when adding a field.
	 *
	 * @param {Event} event Keydown event.
	 */
	const onKeyDown = (event) => {
		if (event.key === 'Enter') {
			event.preventDefault();
			onClick();
		}
	};

	return (
		<>
			{defaultFields.map(({ key, label }) => (
				<PanelRow key={key}>
					<Field
						label={label}
						value={values?.[key] || {}}
						onChange={(value) => {
							onChange(value, key);
						}}
					/>
				</PanelRow>
			))}
			{customFields.map(({ key, label }) => (
				<PanelRow key={key}>
					<Field
						label={label}
						value={values?.[key] || {}}
						onChange={(value) => {
							onChange(value, key);
						}}
						onDelete={() => {
							onDelete(key);
						}}
					/>
				</PanelRow>
			))}
			{isMetadata ? (
				<PanelRow className="ep-weighting-add-new">
					<TextControl
						help={__(
							'Make sure to Sync after adding new fields to ensure that the fields are synced for any existing content that uses them.',
							'elasticpress',
						)}
						label={__('Add field', 'elasticpress')}
						onChange={(toAdd) => setToAdd(toAdd)}
						onKeyDown={onKeyDown}
						placeholder={__('Metadata key', 'elasticpress')}
						value={toAdd}
					/>
					<Button disabled={!toAdd} isSecondary onClick={onClick} variant="secondary">
						{__('Add', 'elasticpress')}
					</Button>
				</PanelRow>
			) : null}
		</>
	);
};
