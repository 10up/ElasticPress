/**
 * WordPress dependencies.
 */
import { CheckboxControl, Panel, PanelHeader } from '@wordpress/components';
import { useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isEqual } from 'lodash';

/**
 * Internal dependencies.
 */
import Group from './post-type/group';
import UndoButton from './common/undo-button';

/**
 * Post type weighting settings component.
 *
 * @param {object} props Components props.
 * @param {object[]} props.groups Field groups.
 * @param {string} props.label Post type label.
 * @param {Function} props.onChange Data change handler.
 * @param {object} props.originalValues Saved post type settings.
 * @param {object} props.values Current post type settings.
 * @returns {WPElement} Component element.
 */
export default ({ groups, label, onChange, originalValues, values }) => {
	const isChanged = useMemo(() => !isEqual(originalValues, values), [originalValues, values]);

	/**
	 * Handle a change to the post type's setttings.
	 *
	 * @param {Array} values Post type settings.
	 * @returns {void}
	 */
	const onChangeGroup = (values) => {
		onChange(values);
	};

	/**
	 * Handle resetting settings for the post type.
	 *
	 * @returns {void}
	 */
	const onReset = () => {
		onChange(originalValues);
	};

	return (
		<Panel>
			<PanelHeader>
				<div className="ep-weighting-property ep-weighting-property--header">
					<div className="ep-weighting-property__name">
						<h2>{label}</h2>
					</div>
					<div className="ep-weighting-property__checkbox">
						<CheckboxControl label={__('Index', 'elasticpress')} />
					</div>
					<div className="ep-weighting-property__undo">
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
			{Object.entries(groups)
				.filter(([, g]) => g.children.length !== 0)
				.map(([key, { label, children }]) => (
					<Group
						isEditable={key === 'meta'}
						key={key}
						label={label}
						onChange={onChangeGroup}
						originalValues={originalValues}
						properties={children}
						values={values}
					/>
				))}
		</Panel>
	);
};
