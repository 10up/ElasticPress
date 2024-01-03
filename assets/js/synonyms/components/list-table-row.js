/**
 * WordPress dependencies.
 */
import { Button, CheckboxControl, Flex, FlexItem, Icon } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { edit, trash, warning } from '@wordpress/icons';

/**
 * Synonyms editor component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.isChecked Whether the row is checked.
 * @param {boolean} props.isEdited Whether the row is being edited.
 * @param {Function} props.onCheck Row checking handler.
 * @param {Function} props.onEdit Row editing handler.
 * @param {Function} props.onDelete Row deleting handler.
 * @param {object} props.set Row set.
 * @param {boolean} props.showPrimary Whether to show a primary term.
 * @returns {WPElement} Synonyms editor component.
 */
export default ({ isChecked, isEdited, onCheck, onEdit, onDelete, set, showPrimary }) => {
	/**
	 * Primary term.
	 */
	const primary = set.synonyms.filter((s) => s.primary).map((s) => s.value);

	/**
	 * Synonyms.
	 *
	 * Hyponyms will include a copy of the hypernym as a replacement, but we
	 * don't need to display that.
	 */
	const synonyms = set.synonyms
		.filter((s) => !s.primary)
		.filter((s) => (primary ? s.value !== primary.value : true))
		.map((s) => s.value);

	return (
		<tr>
			<td>
				<CheckboxControl checked={isChecked} onChange={(value) => onCheck(set.id, value)} />
			</td>
			{showPrimary ? <th>{primary.join(', ')}</th> : null}
			<td>{synonyms.join(', ')}</td>
			<td>
				<Flex justify="end">
					<FlexItem>{!set.valid ? <Icon icon={warning} /> : null}</FlexItem>
					<FlexItem>
						<Button
							icon={edit}
							isPressed={isEdited}
							label={__('Edit', 'elasticpress')}
							onClick={() => (isEdited ? onEdit(null) : onEdit(set.id))}
						/>
					</FlexItem>
					<FlexItem>
						<Button
							icon={trash}
							label={__('Delete', 'elasticpress')}
							onClick={() => onDelete(set.id)}
						/>
					</FlexItem>
				</Flex>
			</td>
		</tr>
	);
};
