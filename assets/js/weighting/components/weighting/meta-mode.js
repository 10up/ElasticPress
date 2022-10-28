/**
 * WordPress dependencies.
 */
import { CheckboxControl, Panel, PanelBody, PanelRow } from '@wordpress/components';
import { WPElement, createInterpolateElement } from '@wordpress/element';
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
						help={createInterpolateElement(
							__(
								'Checking this box will enable full control over what metadata ElasticPress syncs with {ElasticPress.io/Elasticsearch}. Only metadata listed below will be synced and available for searches or queries.<br />If you leave this box unchecked, ElasticPress will index all public meta (i.e. meta that does not begin with <code>_</code>).',
							),
							// eslint-disable-next-line jsx-a11y/anchor-has-content, jsx-a11y/control-has-associated-label
							{ br: <br />, code: <code /> },
						)}
						label={__(
							'Customize meta sync and search (may require re-sync)',
							'elasticpress',
						)}
						onChange={onChange}
					/>
				</PanelRow>
			</PanelBody>
		</Panel>
	);
};
