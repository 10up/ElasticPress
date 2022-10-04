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
import Properties from './post-type/properties';
import UndoButton from './common/undo-button';

/**
 * Post type weighting settings component.
 *
 * @param {object} props Components props.
 * @param {string} props.label Post type label.
 * @param {Function} props.onChange Data change handler.
 * @param {object} props.originalValue Saved post type settings.
 * @param {object} props.value Current post type settings.
 * @returns {WPElement} Component element.
 */
export default ({ label, onChange, originalValue, value }) => {
	const isChanged = useMemo(() => !isEqual(originalValue, value), [originalValue, value]);

	/**
	 * Handle a change to post type indexing.
	 *
	 * @param {boolean} indexable Is post type indexable.
	 * @returns {void}
	 */
	const onChangeIndexable = (indexable) => {
		onChange({ ...value, indexable });
	};

	/**
	 * Handle a change to the post type's attribute setttings.
	 *
	 * @param {Array} attributes Attribute settings.
	 * @returns {void}
	 */
	const onChangeAttributes = (attributes) => {
		onChange({ ...value, attributes });
	};

	/**
	 * Handle a change to the post type's taxonomy setttings.
	 *
	 * @param {Array} taxonomies Taxonomy settings.
	 * @returns {void}
	 */
	const onChangeTaxonomies = (taxonomies) => {
		onChange({ ...value, taxonomies });
	};

	/**
	 * Handle resetting settings for the post type.
	 *
	 * @returns {void}
	 */
	const onReset = () => {
		onChange({ ...originalValue });
	};

	return (
		<Panel>
			<PanelHeader>
				<div className="ep-weighting-property ep-weighting-property--header">
					<div className="ep-weighting-property__name">
						<h2>{label}</h2>
					</div>
					<div className="ep-weighting-property__checkbox">
						<CheckboxControl
							checked={value.indexable}
							label={__('Index', 'elasticpress')}
							onChange={onChangeIndexable}
						/>
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
			{value.indexable ? (
				<>
					<Properties
						label={__('Attributes', 'elasticpress')}
						onChange={onChangeAttributes}
						originalValue={originalValue.attributes}
						value={value.attributes}
					/>
					<Properties
						label={__('Taxonomies', 'elasticpress')}
						onChange={onChangeTaxonomies}
						originalValue={originalValue.taxonomies}
						value={value.taxonomies}
					/>
				</>
			) : null}
		</Panel>
	);
};
