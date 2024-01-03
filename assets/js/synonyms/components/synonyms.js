/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem, FormTokenField } from '@wordpress/components';
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
const defaultSynonyms = [];

/**
 * Synonyms editor component.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const { createNotice } = useSettingsScreen();
	const {
		addSet,
		select,
		selected,
		synonyms: sets,
		updateSet,
		validateSynonyms,
	} = useSynonymsSettings();

	/**
	 * Values for the synonyms being edited.
	 */
	const [synonyms, setSynonyms] = useState(defaultSynonyms);

	/**
	 * The set currently being edited. This is the currently selected set if it
	 * is a set of synonyms.
	 */
	const edited = useMemo(() => sets.find((s) => s.id === selected), [selected, sets]);

	/**
	 * Whether the form is disabled.
	 *
	 * Synonym sets must have at least 2 synonyms.
	 */
	const isValid = useMemo(() => {
		return validateSynonyms(synonyms);
	}, [synonyms, validateSynonyms]);

	/**
	 * Handle changes to the edited set.
	 *
	 * @returns {void}
	 */
	const handleEdited = () => {
		if (edited) {
			setSynonyms(edited.synonyms);
		} else {
			setSynonyms(defaultSynonyms);
		}
	};

	/**
	 * Effects.
	 */
	useEffect(handleEdited, [edited]);

	/**
	 * Handle changes to the synonyms.
	 *
	 * @param {Array} values Form token field values.
	 * @returns {void}
	 */
	const onChange = (values) => {
		const updated = values.map((value) => ({ label: value, primary: false, value }));

		setSynonyms(updated);
	};

	/**
	 * Handle form submission.
	 *
	 * @param {Event} event Submit event.
	 */
	const onSubmit = (event) => {
		event.preventDefault();

		if (edited) {
			updateSet(edited.id, synonyms);
			createNotice('success', __('Updated synonyms.', 'elasticpress'));
			select(null);
		} else {
			addSet(synonyms);
			createNotice('success', __('Added synonyms.', 'elasticpress'));
			setSynonyms(defaultSynonyms);
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
			<p>
				{__(
					'Sets are terms that will all match each other for search results. This is useful where all words are considered equivalent, such as product renaming or regional variations like sneakers, tennis shoes, trainers, and runners.',
					'elasticpress',
				)}
			</p>
			<EditPanel
				title={
					edited
						? __('Editing Synonyms', 'elasticpress')
						: __('Add Synonyms', 'elasticpress')
				}
			>
				<form onSubmit={onSubmit}>
					<FormTokenField
						label={__('Synonyms', 'elasticpress')}
						onChange={onChange}
						value={synonyms.map((h) => h.value)}
					/>
					<Flex justify="start">
						<FlexItem>
							<Button disabled={!isValid} type="submit" variant="secondary">
								{edited
									? __('Save changes', 'elasticpress')
									: __('Add synonyms', 'elasticpress')}
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
			<ListTable label={__('Synonyms', 'elasticpress')} sets={sets} />
		</>
	);
};
