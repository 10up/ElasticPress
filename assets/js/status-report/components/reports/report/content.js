/**
 * WordPress dependencies.
 */
import { PanelBody } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import Value from './value';

/**
 * Field value component.
 *
 * @param {object} props Component props.
 * @param {object} props.fields Fields to render.
 * @param {string} props.title Title.
 *
 * @returns {WPElement} Value component.
 */
export default ({ fields, title }) => {
	return (
		<PanelBody key={title} title={decodeEntities(title)} initialOpen={false}>
			<table cellPadding="0" cellSpacing="0" className="wp-list-table widefat striped">
				<colgroup>
					<col />
					<col />
				</colgroup>
				<tbody>
					{Object.entries(fields).map(([key, { description = '', label, value }]) => (
						<tr key={key}>
							<td>
								{label}
								{description ? <small>{description}</small> : null}
							</td>
							<td>
								<Value value={value} />
							</td>
						</tr>
					))}
				</tbody>
			</table>
		</PanelBody>
	);
};
