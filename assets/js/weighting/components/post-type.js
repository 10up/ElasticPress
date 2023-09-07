/**
 * WordPress dependencies.
 */
import { Panel, PanelBody, PanelHeader } from '@wordpress/components';
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useWeighting } from '../provider';
import Group from './group';

/**
 * Post type weighting settings component.
 *
 * @param {object} props Components props.
 * @param {object[]} props.postType Post type.
 * @returns {WPElement} Component element.
 */
export default ({ postType }) => {
	const { isManual, weightableFields } = useWeighting();

	const { label, groups } = weightableFields.find((f) => f.key === postType);

	/**
	 * Render.
	 */
	return (
		<Panel className="ep-weighting-post-type">
			<PanelHeader>
				<div className="ep-weighting-field ep-weighting-field--header">
					<div className="ep-weighting-field__name">
						<h2>{label}</h2>
					</div>
					<div className="ep-weighting-field__undo" />
				</div>
			</PanelHeader>
			{groups.map(({ key, label }) => {
				const isMetadata = key === 'ep_metadata';

				return !isMetadata || isManual ? (
					<PanelBody initialOpen={!isMetadata} key={key} title={label}>
						<Group group={key} label={label} postType={postType} />
					</PanelBody>
				) : null;
			})}
		</Panel>
	);
};
