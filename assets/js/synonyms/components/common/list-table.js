/**
 * WordPress dependencies.
 */
import { Button, CheckboxControl, Panel } from '@wordpress/components';
import { useCallback, useMemo, useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { trash } from '@wordpress/icons';

/**
 * List table component.
 *
 * @param {object} props Component props.
 * @param {Function} props.children Component children function.
 * @param {WPElement} props.Colgroup Table colgroup component.
 * @param {WPElement} props.Head Table head component.
 * @param {Function} props.onDelete Delete callback.
 * @returns {WPElement}
 */
export default ({ children, Colgroup, Head, onDelete, ...props }) => {
	const [checked, setChecked] = useState([]);

	/**
	 * Handle checking a row.
	 *
	 * @param {string} index Index of the row to check.
	 * @param {boolean} isChecked Whether the row will be checked.
	 * @returns {void}
	 */
	const check = useCallback(
		(index, isChecked) => {
			const updated = checked.filter((c) => c !== index);

			if (isChecked) {
				updated.push(index);
			}

			updated.sort((a, b) => a - b);

			setChecked(updated);
		},
		[checked],
	);

	/**
	 * Handle deleting a row.
	 *
	 * Updates the checked indices to account for the removed indices.
	 *
	 * @param {number} index Row index.
	 */
	const remove = useCallback(
		(index) => {
			const updated = checked
				.filter((c) => c !== index)
				.reduce((updated, checked) => {
					updated.push(checked < index ? checked : checked - 1);

					return updated;
				}, []);

			onDelete([index]);
			setChecked(updated);
		},
		[checked, onDelete],
	);

	/**
	 * Row components.
	 *
	 * @type {WPElement}
	 */
	const rows = useMemo(
		() =>
			children({
				check,
				checked,
				remove,
			}),
		[check, checked, children, remove],
	);

	/**
	 * Handle checking all rows.
	 *
	 * @param {boolean} checked Whether all rows are checked.
	 * @returns {void}
	 */
	const onCheckAll = useCallback(
		(checked) => {
			const updated = checked ? rows.map((child, i) => i) : [];

			updated.sort((a, b) => a - b);

			setChecked(updated);
		},
		[rows],
	);

	/**
	 * Handle deleting selected rows.
	 *
	 * @returns {void}
	 */
	const onDeleteChecked = useCallback(() => {
		setChecked([]);
		onDelete(checked);
	}, [checked, onDelete]);

	/**
	 * Whether all rows are checked.
	 *
	 * @type {boolean}
	 */
	const isAllChecked = useMemo(() => {
		return checked.length > 0 && !rows.some((child, i) => !checked.includes(i));
	}, [checked, rows]);

	/**
	 * Checkbox component.
	 *
	 * @type {WPElement}
	 */
	const CheckAllControl = useCallback(
		() => (
			<CheckboxControl
				checked={isAllChecked}
				indeterminate={checked.length && !isAllChecked}
				onChange={onCheckAll}
			/>
		),
		[checked, isAllChecked, onCheckAll],
	);

	/**
	 * Actions component.
	 *
	 * @type {WPElement}
	 */
	const DeleteCheckedButton = useCallback(
		() => (
			<Button
				disabled={checked.length < 1}
				icon={trash}
				label={__('Delete selected', 'elasticpress')}
				onClick={onDeleteChecked}
			/>
		),
		[checked, onDeleteChecked],
	);

	return (
		<Panel>
			<table {...props}>
				<colgroup>
					<Colgroup />
				</colgroup>
				<thead>
					<Head
						CheckAllControl={CheckAllControl}
						DeleteCheckedButton={DeleteCheckedButton}
					/>
				</thead>
				<tbody>{rows}</tbody>
			</table>
		</Panel>
	);
};
