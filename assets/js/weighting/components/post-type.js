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
import Fields from './post-type/fields';
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
	const { fields, indexable = true, ...rest } = values;
	const {
		fields: originalFields,
		indexable: originalIndexable = true,
		...originalRest
	} = originalValues;

	/**
	 * The fields' values.
	 *
	 * `fields` and `indexable` are available on >4.4.0, while earlier versions
	 * will contain the fields data in `rest`.
	 */
	const fieldsValues = useMemo(() => fields || rest, [fields, rest]);

	/**
	 * The original fields' values.
	 *
	 * `fields` and `indexable` are available on >4.4.0, while earlier versions
	 * will contain the fields data in `rest`.
	 */
	const originalFieldsValues = useMemo(
		() => originalFields || originalRest,
		[originalFields, originalRest],
	);

	/**
	 * Have any values changed?
	 */
	const isChanged = useMemo(
		() => !(indexable === originalIndexable && isEqual(fieldsValues, originalFieldsValues)),
		[fieldsValues, indexable, originalIndexable, originalFieldsValues],
	);

	/**
	 * Handle change of indexable.
	 *
	 * @param {Array} indexable New indexable value.
	 * @returns {void}}
	 */
	const onChangeIndexable = (indexable) => {
		onChange({ fields: fieldsValues, indexable });
	};

	/**
	 * Handle a change to the post type's fields.
	 *
	 * @param {Array} fields New field values.
	 * @returns {void}
	 */
	const onChangeGroup = (fields) => {
		onChange({ fields, indexable });
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
					<div className="ep-weighting-field__indexable">
						<CheckboxControl
							checked={indexable}
							label={__('Index', 'elasticpress')}
							onChange={onChangeIndexable}
						/>
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
			{indexable
				? Object.entries(groups)
						.filter(([, g]) => g.children.length !== 0)
						.map(([key, { label, children }]) => (
							<Fields
								isEditable={key === 'meta'}
								key={key}
								label={label}
								onChange={onChangeGroup}
								originalValues={originalFieldsValues}
								fields={children}
								values={fieldsValues}
							/>
						))
				: null}
		</Panel>
	);
};
