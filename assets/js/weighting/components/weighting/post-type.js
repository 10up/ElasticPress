/**
 * WordPress dependencies.
 */
import { Panel, PanelHeader } from '@wordpress/components';
import { useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isEqual } from 'lodash';

/**
 * Internal dependencies.
 */
import UndoButton from '../common/undo-button';
import Group from './post-type/group';

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
	 * Handle a change to the post type's fields.
	 *
	 * @param {Array} fields New field values.
	 * @returns {void}
	 */
	const onChangeFields = (fields) => {
		onChange({ ...values, fields });
	};

	/**
	 * Handle change in meta management.
	 *
	 * When disabling manual meta management remove weighting settings for
	 * metadata.
	 *
	 * @param {Array} manageMeta New manage meta value.
	 * @returns {void}}
	 */
	const onChangeManageMeta = (manageMeta) => {
		if (manageMeta === false) {
			const keys = Object.keys(groups.meta?.children || {});

			for (const k of keys) {
				delete values.fields[k];
			}
		}

		onChange({ ...values, manage_meta: manageMeta });
	};

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
		<Panel>
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
				return (
					<Group
						fields={Object.values(children)}
						key={key}
						label={label}
						manual={key === 'meta' ? values.manage_meta : null}
						onChange={onChangeFields}
						onChangeManual={key === 'meta' ? onChangeManageMeta : null}
						originalValues={originalValues.fields}
						values={values.fields}
					/>
				);
			})}
		</Panel>
	);
};
