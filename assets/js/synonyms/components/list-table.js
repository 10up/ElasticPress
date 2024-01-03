/**
 * WordPress dependencies.
 */
import { Button, CheckboxControl, Flex, FlexItem } from '@wordpress/components';
import { useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { trash } from '@wordpress/icons';
import ListTableRow from './list-table-row';
import { useSynonymsSettings } from '../provider';

/**
 * Synonyms editor component.
 *
 * @param {object} props Component props.
 * @param {string} props.label Label.
 * @param {string} props.labelForPrimary Label for primary terms.
 * @param {Array} props.sets Synonym sets to list.
 * @returns {WPElement} Synonyms editor component.
 */
export default ({ label, labelForPrimary, sets }) => {
	const { deleteSets, select, selected } = useSynonymsSettings();

	const [checked, setChecked] = useState([]);

	/**
	 * Whether all rows are checked.
	 */
	const isAllChecked = checked.length > 0 && !sets.some((s) => !checked.includes(s.id));

	/**
	 * Handle checking all rows.
	 *
	 * @param {boolean} checked Whether all rows are checked.
	 * @returns {void}
	 */
	const onCheckAll = (checked) => {
		const ids = checked ? sets.map((s) => s.id) : [];

		setChecked(ids);
	};

	/**
	 * Handle checking a row.
	 *
	 * @param {string} id Set ID.
	 * @param {boolean} isChecked Whether the row is checked.
	 * @returns {void}
	 */
	const onCheck = (id, isChecked) => {
		const updated = checked.filter((c) => c !== id);

		if (isChecked) {
			updated.push(id);
		}

		setChecked(updated);
	};

	/**
	 * Handle deleting a row.
	 *
	 * @param {string} id ID of row to delete.
	 * @returns {void}
	 */
	const onDelete = (id) => {
		const updated = checked.filter((c) => c !== id);

		setChecked(updated);
		deleteSets([id]);
	};

	/**
	 * Handle deleting selected rows.
	 *
	 * @returns {void}
	 */
	const onDeleteChecked = () => {
		deleteSets(checked);
		setChecked([]);
	};

	/**
	 * Handle editing a row.
	 *
	 * @param {string} id Set ID.
	 * @returns {void}
	 */
	const onEdit = (id) => {
		select(id);
	};

	return sets.length > 0 ? (
		<table className={`ep-synonyms-list-table ${labelForPrimary ? 'has-primary' : ''}`}>
			<colgroup>
				<col className="ep-synonyms-list-table__checkbox-column" />
				{labelForPrimary ? (
					<col className="ep-synonyms-list-table__primary-column" />
				) : null}
				<col className="ep-synonyms-list-table__synonyms-column" />
				<col className="ep-synonyms-list-table__actions-column" />
			</colgroup>
			<thead>
				<tr>
					<th>
						<CheckboxControl checked={isAllChecked} onChange={onCheckAll} />
					</th>
					{labelForPrimary ? <th>{labelForPrimary}</th> : null}
					<th>{label}</th>
					<th>
						<Flex justify="end">
							<FlexItem>
								<Button
									disabled={checked.length < 1}
									icon={trash}
									label={__('Delete selected', 'elasticpress')}
									onClick={onDeleteChecked}
								/>
							</FlexItem>
						</Flex>
					</th>
				</tr>
			</thead>
			<tbody>
				{sets.map((s) => (
					<ListTableRow
						isChecked={checked.includes(s.id)}
						isEdited={selected === s.id}
						onCheck={onCheck}
						onDelete={onDelete}
						onEdit={onEdit}
						set={s}
						showPrimary={!!labelForPrimary}
					/>
				))}
			</tbody>
		</table>
	) : null;
};
