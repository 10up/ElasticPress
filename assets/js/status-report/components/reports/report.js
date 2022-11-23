/**
 * WordPress dependencies.
 */
import { Button, Panel, PanelBody, PanelHeader } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies.
 */
import Value from './report/value';

/**
 * Report components.
 *
 * @param {object} props Component props.
 * @param {Array} props.actions Report actions.
 * @param {object} props.groups Report groups.
 * @param {string} props.title Report title.
 * @returns {WPElement} Report component.
 */
export default ({ actions, groups, title }) => {
	if (groups.length < 1) {
		return null;
	}

	return (
		<Panel>
			<PanelHeader>
				<h2>{title}</h2>
				{actions.map(({ href, label }) => (
					<Button
						href={decodeEntities(href)}
						isDestructive
						isSecondary
						isSmall
						key={href}
					>
						{label}
					</Button>
				))}
			</PanelHeader>
			{groups.map(({ fields, title }) => (
				<PanelBody key={title} title={decodeEntities(title)} initialOpen={false}>
					<table
						cellPadding="0"
						cellSpacing="0"
						className="wp-list-table widefat striped"
					>
						<colgroup>
							<col />
							<col />
						</colgroup>
						<tbody>
							{Object.entries(fields).map(
								([key, { description = '', label, value }]) => (
									<tr key={key}>
										<td>
											{label}
											{description ? <small>{description}</small> : null}
										</td>
										<td>
											<Value value={value} />
										</td>
									</tr>
								),
							)}
						</tbody>
					</table>
				</PanelBody>
			))}
		</Panel>
	);
};
