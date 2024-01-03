/**
 * WordPress dependencies.
 */
import { Panel, PanelBody, PanelHeader } from '@wordpress/components';
import { WPElement } from '@wordpress/element';

/**
 * Synonyms edit panel component.
 *
 * @param {object} props Component props.
 * @param {WPElement} props.children Component children.
 * @param {string} props.title Panel title.
 * @returns {WPElement} Component element.
 */
export default ({ children, title }) => {
	return (
		<Panel className="ep-synonyms-edit-panel">
			<PanelHeader>
				<h2>{title}</h2>
			</PanelHeader>
			<PanelBody>{children}</PanelBody>
		</Panel>
	);
};
