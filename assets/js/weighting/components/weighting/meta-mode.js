/**
 * WordPress dependencies.
 */
import { CheckboxControl, Panel, PanelBody, PanelRow } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Metadata mode component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.checked Is manual checked.
 * @param {Function} props.onChange Change handler.
 * @returns {WPElement} Component element.
 */
export default ({ checked, onChange }) => {
	/**
	 * Render.
	 */
	return (
		<Panel>
			<PanelBody>
				<PanelRow>
					<CheckboxControl
						checked={checked}
						help={__(
							'Enable the manual management of metadata to take control over what meta fields are indexed and searchable. If manual management is not enabled ElasticPress will automatically index all meta fields with a _ prefix and any metadata required by the active Features, but meta fields will not be searchable.',
						)}
						label={__('Manually manage metadata', 'elasticpress')}
						onChange={onChange}
					/>
				</PanelRow>
			</PanelBody>
		</Panel>
	);
};
