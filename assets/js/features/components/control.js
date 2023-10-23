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
	ToggleControl,
} from '@wordpress/components';
import { safeHTML } from '@wordpress/dom';
import { RawHTML, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

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
 * @param {false|string} props.requiresFeature Any feature required by this setting.
 * @param {boolean} props.requiresSync Whether setting changes require a sync.
 * @param {boolean|string} props.syncedValue Setting value at last sync.
 * @param {string} props.type Control type.
 * @param {boolean|string} props.value Setting value.
 * @returns {WPElement} Reports component.
 */
export default ({
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
}) => {
	const { getFeature, isBusy, settings, willSettingRequireSync } = useFeatureSettings();

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

	/**
	 * The feature required by this setting, if any.
	 */
	const requiredFeature =
		requiresFeature && settings[requiresFeature]?.active !== true
			? getFeature(requiresFeature)
			: false;

	/**
	 * The notice to display if a feature is required.
	 */
	const requiredFeatureNotice =
		name === 'active'
			? __('The %s feature must be enabled to use this feature.', 'elasticpress')
			: __('The %s feature must be enabled to use the following setting.', 'elasticpress');

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
	const isDisabled = isBusy || disabled || requiredFeature;

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

	return (
		<>
			{requiredFeature ? (
				<Notice isDismissible={false} status={name === 'active' ? 'error' : 'warning'}>
					{sprintf(requiredFeatureNotice, requiredFeature.shortTitle)}
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
								/>
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
								/>
							);
						}
					}
				})()}
			</div>
		</>
	);
};
