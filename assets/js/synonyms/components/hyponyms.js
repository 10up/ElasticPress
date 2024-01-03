/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem, FormTokenField, Notice, TextControl } from '@wordpress/components';
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
const defaultHypernym = { label: '', primary: true, value: '' };
const defaultHyponyms = [];

/**
 * Synonyms editor component.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const { createNotice } = useSettingsScreen();
	const {
		addSet,
		hyponyms: sets,
		select,
		selected,
		updateSet,
		validateHyponyms,
	} = useSynonymsSettings();

	/**
	 * Values for the Hypernym and Hyponyms in the editor.
	 */
	const [hypernym, setHypernym] = useState(defaultHypernym);
	const [hyponyms, setHyponyms] = useState(defaultHyponyms);

	/**
	 * The set currently being edited. This is the currently selected set if it
	 * is a hyponym.
	 */
	const edited = useMemo(() => sets.find((s) => s.id === selected), [selected, sets]);

	/**
	 * Whether the form is disabled.
	 *
	 * Hyponyms must have a hypernym and at least one hyponym that is not the
	 * hypernym.
	 */
	const isValid = useMemo(() => {
		return validateHyponyms(hypernym, hyponyms);
	}, [hypernym, hyponyms, validateHyponyms]);

	/**
	 * Handle changes to the edited set.
	 *
	 * @returns {void}
	 */
	const handleEdited = () => {
		if (edited) {
			const updatedHypernym = edited.synonyms.find((s) => s.primary);
			const updatedHyponyms = edited.synonyms.filter(
				(s) => !s.primary && s.value !== updatedHypernym.value,
			);

			setHypernym(updatedHypernym);
			setHyponyms(updatedHyponyms);
		} else {
			setHypernym(defaultHypernym);
			setHyponyms(defaultHyponyms);
		}
	};

	/**
	 * Effects.
	 */
	useEffect(handleEdited, [edited]);

	/**
	 * Handle changes to the hypernym.
	 *
	 * @param {string} value Text control value.
	 * @returns {void}
	 */
	const onChangeHypernym = (value) => {
		const updated = { label: value, primary: true, value };

		setHypernym(updated);
	};

	/**
	 * Handle changes to the hyponyms.
	 *
	 * @param {Array} values Form token field values.
	 * @returns {void}
	 */
	const onChangeHyponyms = (values) => {
		const updated = values.map((value) => ({ label: value, primary: false, value }));

		setHyponyms(updated);
	};

	/**
	 * Handle form submission.
	 *
	 * @param {Event} event Submit event.
	 */
	const onSubmit = (event) => {
		event.preventDefault();

		const synonyms = [
			hypernym,
			{ ...hypernym, primary: false },
			...hyponyms.filter((s) => s.value !== hypernym.value),
		];

		if (edited) {
			updateSet(edited.id, synonyms);
			createNotice('success', __('Updated hyponyms.', 'elasticpress'));
			select(null);
		} else {
			addSet(synonyms);
			createNotice('success', __('Added hyponyms.', 'elasticpress'));
			setHypernym(defaultHypernym);
			setHyponyms(defaultHyponyms);
		}
	};

	/**
	 * Handle click event for the Cancel button.
	 *
	 * @returns {void}
	 */
	const onCancel = () => {
		select(null);
	};

	return (
		<>
			<EditPanel
				title={
					edited
						? __('Editing Hyponyms', 'elasticpress')
						: __('Add Hyponyms', 'elasticpress')
				}
			>
				<form onSubmit={onSubmit}>
					{edited && !edited.valid ? (
						<Notice isDismissible={false} status="error">
							{__(
								'Hyponyms must have a hypernym and at least one hyponym that is not the hypernym.',
								'elasticpress',
							)}
						</Notice>
					) : null}
					<TextControl
						label={__('Hypernym', 'elasticpress')}
						onChange={onChangeHypernym}
						value={hypernym.value}
					/>
					<FormTokenField
						label={__('Hyponyms', 'elasticpress')}
						onChange={onChangeHyponyms}
						value={hyponyms.map((h) => h.value)}
					/>
					<Flex justify="start">
						<FlexItem>
							<Button disabled={!isValid} type="submit" variant="secondary">
								{edited
									? __('Save changes', 'elasticpress')
									: __('Add hyponyms', 'elasticpress')}
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
				label={__('Hyponyms', 'elasticpress')}
				labelForPrimary={__('Hypernym', 'elasticpress')}
				sets={sets}
			/>
		</>
	);
};
