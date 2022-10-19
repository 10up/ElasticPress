/**
 * WordPress dependencies.
 */
import { CheckboxControl, PanelBody, PanelRow } from '@wordpress/components';
import { useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Fields from './fields';

/**
 * Field group component.
 *
 * @param {object} props Component props.
 * @param {object} props.fields Group fields.
 * @param {string} props.label Group label.
 * @param {boolean} props.manual Whether the fields are being managed manually.
 * @param {Function} props.onChange Field change handler.
 * @param {Function} props.onChangeManual Manual managment change handler.
 * @param {object} props.originalValues Saved weighting values.
 * @param {object} props.values Current weighting values.
 * @returns {WPElement} Component element.
 */
export default ({ fields, label, manual, onChange, onChangeManual, originalValues, values }) => {
	/**
	 * Whether to show the fields.
	 *
	 * Always show the fields unless the group has the option for manual
	 * management, in which case only show fields if manual management is
	 * enabled.
	 */
	const showFields = useMemo(() => (onChangeManual ? manual : true), [manual, onChangeManual]);

	/**
	 * Weightable fields.
	 */
	const weightableFields = useMemo(() => Object.values(fields), [fields]);

	/**
	 * Render.
	 */
	return (
		<PanelBody title={label} initialOpen={showFields}>
			{onChangeManual ? (
				<PanelRow>
					<CheckboxControl
						checked={manual}
						help={__(
							'Enable the manual management of metadata to take control over what meta fields are indexed and searchable. If manual management is not enabled ElasticPress will automatically index all meta fields with a _ prefix and any metadata required by the active Features, but meta fields will not be searchable.',
						)}
						label={__('Manually manage metadata', 'elasticpress')}
						onChange={onChangeManual}
					/>
				</PanelRow>
			) : null}
			{showFields ? (
				<Fields
					isEditable={onChangeManual ? manual : false}
					label={label}
					onChange={onChange}
					originalValues={originalValues}
					fields={weightableFields}
					values={values}
				/>
			) : null}
		</PanelBody>
	);
};
