/**
 * WordPress dependencies.
 */
import { PanelBody } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Value from './value';

/**
 * Field value component.
 *
 * @param {object} props Component props.
 * @param {string} props.title Title.
 *
 * @returns {WPElement} Value component.
 */
export default () => {
	return (
		<PanelBody title={__('Pending generation', 'elasticpress')}>
			<table cellPadding="0" cellSpacing="0" className="wp-list-table widefat striped">
				<tbody>
					<tr>
						<td>
							<Value
								value={__(
									'To see this report, please generate a full report first by clicking the "Generate Full Status Report" button.',
									'elasticpress',
								)}
							/>
						</td>
					</tr>
				</tbody>
			</table>
		</PanelBody>
	);
};
