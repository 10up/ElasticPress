/**
 * WordPress dependencies.
 */
import { Panel, PanelBody, PanelHeader } from '@wordpress/components';
import { useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isEqual } from 'lodash';

/**
 * Internal dependencies.
 */
import Fields from './post-type/fields';
import UndoButton from '../common/undo-button';

/**
 * Post type weighting settings component.
 *
 * @param {object} props Components props.
 * @param {object[]} props.groups Field groups.
 * @param {string} props.label Post type label.
 * @param {Function} props.onChange Data change handler.
 * @param {object} props.originalValues Saved post type settings.
 * @param {boolean} props.showMeta Whether to show the meta group.
 * @param {object} props.values Current post type settings.
 * @returns {WPElement} Component element.
 */
export default ({ groups, label, onChange, originalValues, showMeta, values }) => {
	/**
	 * Have any values changed?
	 */
	const isChanged = useMemo(() => !isEqual(originalValues, values), [originalValues, values]);

	/**
	 * The field groups to display.
	 *
	 * Filters out any groups without fields.
	 */
	const fieldGroups = useMemo(
		() =>
			Object.entries(groups).reduce((previousValue, currentValue) => {
				const [key, group] = currentValue;

				if (Object.keys(group.children).length > 0) {
					return [...previousValue, { key, ...group }];
				}

				return previousValue;
			}, []),
		[groups],
	);

	/**
	 * Handle resetting all data for the post type.
	 *
	 * @returns {void}
	 */
	const onReset = () => {
		onChange(originalValues);
	};

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
					<div className="ep-weighting-field__undo">
						{isChanged ? (
							<UndoButton
								disabled={!isChanged}
								label={__('Undo all changes', 'elasticpress')}
								onClick={onReset}
							/>
						) : null}
					</div>
				</div>
			</PanelHeader>
			{fieldGroups.map(({ children, key, label }) => {
				const isMeta = key === 'ep_metadata';
				const fields = Object.values(children);

				return !isMeta || showMeta ? (
					<PanelBody key={key} title={label}>
						<Fields
							isEditable={isMeta}
							label={label}
							onChange={onChange}
							originalValues={originalValues}
							fields={fields}
							values={values}
						/>
					</PanelBody>
				) : null;
			})}
		</Panel>
	);
};
