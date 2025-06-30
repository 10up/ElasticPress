/**
 * WordPress dependencies.
 */
import {
	CheckboxControl,
	FormTokenField,
	Notice,
	RadioControl,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	Card,
	CardHeader,
	CardBody,
} from '@wordpress/components';
import { safeHTML } from '@wordpress/dom';
import { RawHTML, WPElement } from '@wordpress/element';
import { _n, __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useFeatureSettings } from '../provider';

/**
 * Control component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @param {string} props.help Control help text.
 * @param {string} props.label Control label.
 * @param {string} props.name Setting name.
 * @param {Function} props.onChange Change event handler.
 * @param {Array|null} props.options (optional) Control options.
 * @param {false|string} props.requiresFeatures Any features required by this setting.
 * @param {boolean} props.requiresSync Whether setting changes require a sync.
 * @param {boolean|string} props.syncedValue Setting value at last sync.
 * @param {string} props.type Control type.
 * @param {boolean|string} props.value Setting value.
 * @param {object} props.fields (optional) Fields for field group control.
 * @returns {WPElement} Reports component.
 */
const Control = ({
	disabled,
	help,
	label,
	name,
	onChange,
	options,
	requiresFeature,
	requiresSync,
	syncedValue,
	type,
	value,
	fields,
}) => {
	const { getFeature, isBusy, settings, willSettingRequireSync } = useFeatureSettings();

	// Convert single feature requirement to array for compatibility
	let requiredFeaturesList = [];
	if (requiresFeature) {
		requiredFeaturesList = Array.isArray(requiresFeature) ? requiresFeature : [requiresFeature];
	}

	/**
	 * Get missing required features.
	 */
	const missingRequiredFeatures = requiredFeaturesList
		.filter((featureSlug) => settings[featureSlug]?.active !== true)
		.map((featureSlug) => getFeature(featureSlug))
		.filter(Boolean);

	/**
	 * Help text formatted to allow safe HTML.
	 */
	const helpHtml = help ? (
		<span dangerouslySetInnerHTML={{ __html: safeHTML(help) }} /> // eslint-disable-line react/no-danger
	) : null;

	/**
	 * Options formatted for radio controls to allow safe HTML in labels.
	 */
	const radioOptions = options
		? options.map((o) => {
				return {
					value: o.value,
					label: <span dangerouslySetInnerHTML={{ __html: safeHTML(o.label) }} />, // eslint-disable-line react/no-danger
				};
			})
		: [];

	const titles = missingRequiredFeatures.map((f) => f.shortTitle);

	/**
	 * The notice to display if a feature is required.
	 */
	const requiredFeatureNotice =
		name === 'active'
			? /* translators: %s: feature list */
				_n(
					'The %s feature must be enabled to use this feature.',
					'The %s features must be enabled to use this feature.',
					titles.length,
					'elasticpress',
				)
			: /* translators: %s: feature list */
				_n(
					'The %s feature must be enabled to use the following setting.',
					'The %s features must be enabled to use the following setting.',
					titles.length,
					'elasticpress',
				);

	/**
	 * The notice to display if a sync is required.
	 */
	const syncNotice =
		name === 'active'
			? __('Enabling this feature requires re-syncing your content.', 'elasticpress')
			: __('A change to following setting requires re-syncing your content.', 'elasticpress');

	/**
	 * Whether the control is disabled.
	 */
	const isDisabled = isBusy || disabled || missingRequiredFeatures.length > 0;

	/**
	 * Whether the selected value for this setting will require a sync.
	 */
	const willRequireSync = willSettingRequireSync(value, syncedValue, requiresSync);

	/**
	 * Handle change to checkbox values.
	 *
	 * @param {boolean} checked Whether checkbox is checked.
	 */
	const onChangeCheckbox = (checked) => {
		const value = checked ? '1' : '0';

		onChange(value);
	};

	/**
	 * Handle change to token field values.
	 *
	 * The FormTokenField control does not support separate values and labels,
	 * so whenever a change is made we need to set the field value based on the
	 * selected label.
	 *
	 * @param {string[]} values Selected values.
	 */
	const onChangeFormTokenField = (values) => {
		const value = values
			.map((v) => options.find((o) => o.label === v)?.value)
			.filter(Boolean)
			.join(',');

		onChange(value);
	};

	const list =
		titles.length > 1
			? sprintf(
					__('%1$s, and %2$s', 'elasticpress'),
					titles.slice(0, -1).join(__(', ', 'elasticpress')),
					titles[titles.length - 1],
				)
			: titles[0] || '';

	return (
		<>
			{missingRequiredFeatures.length > 0 ? (
				<Notice isDismissible={false} status={name === 'active' ? 'error' : 'warning'}>
					{sprintf(requiredFeatureNotice, list)}
				</Notice>
			) : null}
			{willRequireSync ? (
				<Notice isDismissible={false} status="warning">
					{syncNotice}
				</Notice>
			) : null}
			<div className="ep-dashboard-control">
				{(() => {
					switch (type) {
						case 'checkbox': {
							return (
								<CheckboxControl
									checked={value === '1'}
									help={helpHtml}
									label={label}
									onChange={onChangeCheckbox}
									disabled={isDisabled}
									__nextHasNoMarginBottom
								/>
							);
						}
						case 'hidden': {
							return null;
						}
						case 'markup': {
							return <RawHTML>{safeHTML(label)}</RawHTML>;
						}
						case 'multiple': {
							const suggestions = options.map((o) => o.label);
							const values = value
								.split(',')
								.map((v) => options.find((o) => o.value === v)?.label)
								.filter(Boolean);

							return (
								<FormTokenField
									__experimentalExpandOnFocus
									__experimentalShowHowTo={false}
									label={label}
									onChange={onChangeFormTokenField}
									disabled={isDisabled}
									suggestions={suggestions}
									value={values}
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							);
						}
						case 'radio': {
							return (
								<RadioControl
									help={helpHtml}
									label={label}
									onChange={onChange}
									options={radioOptions}
									disabled={isDisabled}
									selected={value}
								/>
							);
						}
						case 'select': {
							return (
								<SelectControl
									help={helpHtml}
									label={label}
									onChange={onChange}
									options={options}
									disabled={isDisabled}
									value={value}
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							);
						}
						case 'toggle': {
							return (
								<ToggleControl
									checked={value}
									help={helpHtml}
									label={label}
									onChange={onChange}
									disabled={isDisabled}
									__nextHasNoMarginBottom
								/>
							);
						}
						case 'textarea': {
							return (
								<TextareaControl
									help={helpHtml}
									label={label}
									onChange={onChange}
									disabled={isDisabled}
									value={value}
									__nextHasNoMarginBottom
								/>
							);
						}
						case 'field_group': {
							const shouldRenderField = (requiresFields) => {
								if (!requiresFields || Object.keys(requiresFields).length === 0) {
									return true;
								}

								const conditions = requiresFields.conditions ?? false;
								let relationship = requiresFields.relationship ?? 'AND';

								if (relationship !== 'OR' && relationship !== 'AND') {
									relationship = 'AND';
								}

								if (!conditions) {
									return true;
								}

								// Helper function to check individual condition
								const checkCondition = ([fieldKey, requiredValue]) => {
									let actualValue;

									// For fields that are part of the field group, check there first
									if (fields.some((f) => f.key === fieldKey)) {
										actualValue = value?.[fieldKey];
									} else {
										// For fields not in the group, check parent settings
										actualValue = settings['instant-results']?.[fieldKey];
									}

									return actualValue === requiredValue;
								};

								// Apply the appropriate logic based on relationship
								if (relationship === 'OR') {
									return Object.entries(conditions).some(checkCondition);
								}
								return Object.entries(conditions).every(checkCondition);
							};

							return (
								<div className="ep-field-group">
									<Card>
										{label && (
											<CardHeader>
												<strong>{label}</strong>
											</CardHeader>
										)}
										<CardBody>
											{fields.map((field) => {
												const shouldRender = shouldRenderField(
													field.requires_fields,
												);

												// Skip rendering if the field's requirements aren't met
												if (!shouldRender) {
													return null;
												}

												// Get the current value for this field
												const fieldValue = value?.[field.key];

												return (
													<Control
														key={field.key}
														{...field}
														value={fieldValue}
														onChange={(newValue) => {
															// Create a new object with the updated field value
															const updatedValue = {
																...value,
																[field.key]: newValue,
															};

															// Call the parent onChange with the updated nested value
															onChange(updatedValue);
														}}
													/>
												);
											})}
										</CardBody>
									</Card>
								</div>
							);
						}
						default: {
							return (
								<TextControl
									help={helpHtml}
									label={label}
									onChange={onChange}
									disabled={isDisabled}
									value={value}
									type={type}
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							);
						}
					}
				})()}
			</div>
		</>
	);
};

export default Control;
