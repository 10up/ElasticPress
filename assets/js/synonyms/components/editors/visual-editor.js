/**
 * WordPress dependencies.
 */
import { CheckboxControl, Flex, FlexItem } from '@wordpress/components';
import { useCallback, useEffect, useMemo, useRef, useState, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useSettingsScreen } from '../../../settings-screen';
import { useSynonymsSettings } from '../../provider';
import EditPanel from '../common/edit-panel';
import ListTable from '../common/list-table';
import RowActions from '../common/row-actions';

/**
 * Visual editor component.
 *
 * @typedef Synonym
 * @property {string} value The synonym value.
 * @property {boolean} primary Whether the synonym is a primary term.
 *
 * @typedef Rule
 * @property {string} id Rule ID.
 * @property {Synonym[]} synonyms Rule synonyms.
 * @property {boolean} valid Whether the rule is valid.
 *
 * @param {object} props Component props.
 * @param {object} props.labels Labels.
 * @param {object} props.messages Messages.
 * @param {'hyponyms'|'synonyms'|'replacements'} props.mode Editor mode.
 * @param {Rule[]} props.rules Synonym rules.
 * @returns {WPElement}
 */
export default ({ labels, messages, mode, rules }) => {
	const { createNotice } = useSettingsScreen();
	const {
		addRule,
		deleteRules,
		isBusy,
		isHyponymsValid,
		isReplacementsValid,
		isSynonymsValid,
		select,
		selected,
		updateRule,
	} = useSynonymsSettings();

	/**
	 * Edit panel reference.
	 */
	const editPanelRef = useRef();

	/**
	 * Values for the primary terms and synonyms in the editor.
	 */
	const [primary, setPrimary] = useState([]);
	const [synonyms, setSynonyms] = useState([]);

	/**
	 * The set currently being edited. This is the currently selected set if it
	 * is a set of synonyms.
	 */
	const edited = useMemo(() => rules.find((s) => s.id === selected), [rules, selected]);

	/**
	 * Whether the form is valid.
	 *
	 * @type {boolean}
	 */
	const isValid = useMemo(() => {
		const rule = [...primary, ...synonyms];

		switch (mode) {
			case 'hyponyms':
				return isHyponymsValid(rule);
			case 'replacements':
				return isReplacementsValid(rule);
			case 'synonyms':
			default:
				return isSynonymsValid(rule);
		}
	}, [isHyponymsValid, isReplacementsValid, isSynonymsValid, mode, primary, synonyms]);

	/**
	 * Filter a list of synonyms to include only primary terms.
	 *
	 * @param {Synonym} synonym Synonym to filter.
	 */
	const isPrimary = useCallback((synonym) => {
		return synonym.primary;
	}, []);

	/**
	 * Filter a list of synonyms to exclude primary terms.
	 *
	 * When in hyponyms mode, also exclude the hypernym from the list of
	 * synonyms.
	 *
	 * @param {object} synonym Synonym to filter.
	 * @param {number} index Index of the synonym being filtered.
	 * @param {Array} synonyms Synonyms being filtered.
	 * @returns {boolean} True to keep the synonym, or false to filter it.
	 */
	const isNotPrimary = useCallback(
		(synonym, index, synonyms) => {
			if (
				mode === 'hyponyms' &&
				synonyms.some((s) => s.primary && s.value === synonym.value)
			) {
				return false;
			}

			return !synonym.primary;
		},
		[mode],
	);

	/**
	 * Handle changes to the primary terms.
	 *
	 * @param {Array|string} value Updated value.
	 * @returns {void}
	 */
	const onChangePrimary = (value) => {
		const values = typeof value === 'string' ? [value] : value;
		const updated = values.map((value) => ({ label: value, primary: true, value }));

		setPrimary(updated);
	};

	/**
	 * Handle changes to the hyponyms.
	 *
	 * @param {Array} values Updated values.
	 * @returns {void}
	 */
	const onChangeSynonyms = (values) => {
		const updated = values.map((value) => ({ label: value, primary: false, value }));

		setSynonyms(updated);
	};

	/**
	 * Handle deleting rules.
	 *
	 * @param {string} indices Indices of rules to delete.
	 */
	const onDelete = (indices) => {
		const ids = indices.filter((index) => rules[index]).map((index) => rules[index].id);

		deleteRules(ids);
		createNotice('success', messages.deleted);
	};

	/**
	 * Handle click event for the Cancel button.
	 *
	 * @returns {void}
	 */
	const onReset = () => {
		select(null);
	};

	/**
	 * Handle form submission.
	 *
	 * @param {Event} event Submit event.
	 */
	const onSubmit = (event) => {
		event.preventDefault();

		let updatedSynonyms = synonyms;

		if (mode === 'hyponyms') {
			const hypernym = primary.find((p) => p.value);

			updatedSynonyms = [
				{ ...hypernym, primary: false },
				...synonyms.filter((s) => s.value !== hypernym.value),
			];
		}

		const updated = [...primary, ...updatedSynonyms];

		if (edited) {
			updateRule(edited.id, updated);
			createNotice('success', messages.updated);
			select(null);
		} else {
			addRule(updated);
			createNotice('success', messages.added);
			setPrimary([]);
			setSynonyms([]);
		}
	};

	/**
	 * Handle changes to the edited set.
	 *
	 * @returns {void}
	 */
	const handleEdited = () => {
		if (edited) {
			const primary = edited.synonyms.filter(isPrimary);
			const synonyms = edited.synonyms.filter(isNotPrimary);

			setPrimary(primary);
			setSynonyms(synonyms);

			editPanelRef.current.scrollIntoView({ behavior: 'smooth', block: 'center' });
		} else {
			setPrimary([]);
			setSynonyms([]);
		}
	};

	/**
	 * List table colgroup.
	 *
	 * Outputs an appropriate contents for the list table colgroup.
	 *
	 * @type {WPElement}
	 */
	const Colgroup = useCallback(
		() => (
			<>
				<col className="ep-synonyms-list-table__checkbox-column" />
				{labels.primary ? <col className="ep-synonyms-list-table__primary-column" /> : null}
				<col className="ep-synonyms-list-table__synonyms-column" />
				<col className="ep-synonyms-list-table__actions-column" />
			</>
		),
		[labels],
	);

	/**
	 * List table head.
	 *
	 * Outputs appropriate contents for the list table head. Accepts
	 * components for interacting with the table as props.
	 *
	 * @param {object} props Component props.
	 * @param {WPElement} props.CheckAll Check all component.
	 * @param {WPElement} props.DeleteChecked Delete checked component.
	 * @type {WPElement}
	 */
	const Head = useCallback(
		({ CheckAllControl, DeleteCheckedButton }) => (
			<tr>
				<th>
					<CheckAllControl />
				</th>
				{labels.primary ? <th>{labels.primary}</th> : null}
				<th>{labels.synonyms}</th>
				<th>
					<Flex justify="end">
						<FlexItem>
							<DeleteCheckedButton />
						</FlexItem>
					</Flex>
				</th>
			</tr>
		),
		[labels],
	);

	/**
	 * Effects.
	 */
	useEffect(handleEdited, [edited, isPrimary, isNotPrimary, mode]);

	return (
		<>
			<EditPanel
				disabled={isBusy}
				errorMessage={edited && !edited.valid && !isValid ? messages.invalid : null}
				isNew={!edited}
				isValid={isValid}
				labels={labels}
				mode={mode}
				onChangePrimary={onChangePrimary}
				onChangeSynonyms={onChangeSynonyms}
				onReset={onReset}
				onSubmit={onSubmit}
				primaryValue={primary}
				ref={editPanelRef}
				synonymsValue={synonyms}
			/>
			<ListTable
				className={`ep-synonyms-list-table ep-synonyms-list-table--${mode}`}
				Colgroup={Colgroup}
				Head={Head}
				onDelete={onDelete}
			>
				{({ check, checked, remove }) =>
					rules.map((s, i) => (
						<tr key={s.id}>
							<td>
								<CheckboxControl
									checked={checked.includes(i)}
									onChange={(isChecked) => check(i, isChecked)}
								/>
							</td>
							{labels.primary ? (
								<td>
									{s.synonyms
										.filter(isPrimary)
										.map((s) => s.value)
										.join(', ')}
								</td>
							) : null}
							<td>
								{s.synonyms
									.filter(isNotPrimary)
									.map((s) => s.value)
									.join(', ')}
							</td>
							<td>
								<RowActions
									disabled={isBusy}
									errorMessage={!s.valid ? messages.invalid : null}
									isSelected={selected === s.id}
									onDelete={() => remove(i)}
									onSelect={() => select(s.id)}
								/>
							</td>
						</tr>
					))
				}
			</ListTable>
		</>
	);
};
