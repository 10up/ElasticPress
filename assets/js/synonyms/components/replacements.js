/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem, FormTokenField, Notice } from '@wordpress/components';
import { useEffect, useMemo, useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSettingsScreen } from '../../settings-screen';
import { useSynonymsSettings } from '../provider';
import EditPanel from './shared/edit-panel';
import ListTable from './list-table';

/**
 * Useful constants.
 */
const defaultReplacements = [];
const defaultTerms = [];

/**
 * Synonyms editor component.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const { createNotice } = useSettingsScreen();
	const {
		addSet,
		replacements: sets,
		select,
		selected,
		updateSet,
		validateHyponyms,
		validateReplacements,
	} = useSynonymsSettings();

	/**
	 * Values for the terms and replacements in the editor.
	 */
	const [replacements, setReplacements] = useState(defaultReplacements);
	const [terms, setTerms] = useState(defaultTerms);

	/**
	 * The set currently being edited.
	 *
	 * This is the currently selected set if it is a replacements set.
	 */
	const edited = useMemo(() => sets.find((s) => s.id === selected), [selected, sets]);

	/**
	 * Whether the replacement set is a hyponym.
	 *
	 * A replacement set is a hyponym when there is only one primary term, and
	 * the primary term is also included as a replacement.
	 */
	const isHyponym = useMemo(() => {
		return terms.length === 1 && replacements.some((r) => r.value === terms[0].value);
	}, [replacements, terms]);

	/**
	 * Whether the form is valid.
	 *
	 * Replacements must have at least one term and one replacement.
	 */
	const isValid = useMemo(() => {
		return isHyponym
			? validateHyponyms(terms[0], replacements)
			: validateReplacements(terms, replacements);
	}, [isHyponym, replacements, terms, validateHyponyms, validateReplacements]);

	/**
	 * Handle changes to the edited set.
	 *
	 * @returns {void}
	 */
	const handleEdited = () => {
		if (edited) {
			const updatedReplacements = edited.synonyms.filter((s) => !s.primary);
			const updatedTerms = edited.synonyms.filter((s) => s.primary);

			setReplacements(updatedReplacements);
			setTerms(updatedTerms);
		} else {
			setReplacements(defaultReplacements);
			setTerms(defaultTerms);
		}
	};

	/**
	 * Effects.
	 */
	useEffect(handleEdited, [edited]);

	/**
	 * Handle changes to the terms.
	 *
	 * @param {Array} values Form token field values.
	 * @returns {void}
	 */
	const onChangeReplacements = (values) => {
		const updated = values.map((value) => ({ label: value, primary: false, value }));

		setReplacements(updated);
	};

	/**
	 * Handle changes to the replacements.
	 *
	 * @param {Array} values Form token field values.
	 * @returns {void}
	 */
	const onChangeTerms = (values) => {
		const updated = values.map((value) => ({ label: value, primary: true, value }));

		setTerms(updated);
	};

	/**
	 * Handle form submission.
	 *
	 * @param {Event} event Submit event.
	 */
	const onSubmit = (event) => {
		event.preventDefault();

		const synonyms = [...terms, ...replacements];

		if (edited) {
			updateSet(edited.id, synonyms);

			createNotice(
				'success',
				isHyponym
					? __('Updated hyponyms.', 'elasticpress')
					: __('Updated replacements.', 'elasticpress'),
			);

			select(null);
		} else {
			addSet(synonyms);

			createNotice(
				'success',
				isHyponym
					? __('Added hyponyms.', 'elasticpress')
					: __('Added replacements.', 'elasticpress'),
			);

			setReplacements(defaultReplacements);
			setTerms(defaultTerms);
		}
	};

	/**
	 * Handle click event for the Cancel button.
	 *
	 * @returns {void}
	 */
	const onCancel = () => {
		if (edited) {
			select(null);
		}
	};

	return (
		<>
			<p>
				{__(
					'Alternatives are terms that will also be matched when you search for the primary term. For instance, a search for shoes can also include results for sneaker, sandals, boots, and high heels.',
					'elasticpress',
				)}
			</p>
			<EditPanel
				title={
					edited
						? __('Editing Replacements', 'elasticpress')
						: __('Add Replacements', 'elasticpress')
				}
			>
				<form onSubmit={onSubmit}>
					{isHyponym ? (
						<Notice isDismissible={false} status="warning">
							{edited
								? __(
										'Replacements for a single term where there replacements include the term itself are Hyponyms. Saving this replacement will move it to the Hyponyms list.',
										'elasticpress',
								  )
								: __(
										'Replacements for a single term where there replacements include the term itself are Hyponyms. Adding this replacement will add it to the Hyponyms list.',
										'elasticpress',
								  )}
						</Notice>
					) : null}
					<FormTokenField
						label={__('Terms', 'elasticpress')}
						onChange={onChangeTerms}
						value={terms.map((h) => h.value)}
					/>
					<FormTokenField
						label={__('Replacements', 'elasticpress')}
						onChange={onChangeReplacements}
						value={replacements.map((h) => h.value)}
					/>
					<Flex justify="start">
						<FlexItem>
							<Button disabled={!isValid} type="submit" variant="secondary">
								{(() => {
									if (edited) {
										return __('Save changes', 'elasticpress');
									}
									return isHyponym
										? __('Add hyponyms', 'elasticpress')
										: __('Add replacements', 'elasticpress');
								})()}
							</Button>
						</FlexItem>
						{edited ? (
							<FlexItem>
								<Button onClick={onCancel} type="button" variant="tertiary">
									{__('Cancel', 'elasticpress')}
								</Button>
							</FlexItem>
						) : null}
					</Flex>
				</form>
			</EditPanel>
			<ListTable
				label={__('Replacements', 'elasticpress')}
				labelForPrimary={__('Terms', 'elasticpress')}
				sets={sets}
			/>
		</>
	);
};
