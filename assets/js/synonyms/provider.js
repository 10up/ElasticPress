/**
 * WordPress dependencies.
 */
import { createContext, useContext, useMemo, useState, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { reduceStateToSolr, mapEntry, reduceSolrToState } from './utils';

/**
 * Sync context.
 */
const Context = createContext();

/**
 * Synonyms settings context.
 *
 * @param {object} props Props.
 * @param {WPElement} props.children Component children.
 * @param {Array} props.defaultAlternatives Default replacements.
 * @param {boolean} props.defaultIsSolr Whether the Solr editor is being used.
 * @param {Array} props.defaultSets Default synonyms.
 * @returns {WPElement} AppContext component
 */
export const SynonymsSettingsProvider = ({
	children,
	defaultAlternatives,
	defaultIsSolr,
	defaultSets,
}) => {
	const defaultSolr = useMemo(
		() => reduceStateToSolr({ sets: defaultSets, alternatives: defaultAlternatives }),
		[defaultAlternatives, defaultSets],
	);

	const [selected, setSelected] = useState(null);
	const [isDirty, setIsDirty] = useState(false);
	const [isSolr, setIsSolr] = useState(defaultIsSolr);
	const [solr, setSolr] = useState(defaultSolr);
	const [sets, setSets] = useState([...defaultAlternatives, ...defaultSets]);

	/**
	 * Hyponym sets.
	 *
	 * Hyponyms sets are sets with a primary term where the primary term is
	 * also included as a replacement.
	 */
	const hyponyms = useMemo(
		() =>
			sets.filter((s) => {
				const primary = s.synonyms.filter((s) => s.primary);

				return (
					primary.length === 1 &&
					s.synonyms.some((s) => !s.primary && s.value === primary[0].value)
				);
			}),
		[sets],
	);

	/**
	 * Replacement sets.
	 *
	 * Replacement sets are sets with multiple primary terms, or a single
	 * primary term and replacements that do not include the the primary term
	 * as a replacement. The latter case is a hyponym.
	 */
	const replacements = useMemo(
		() =>
			sets.filter((s) => {
				const primary = s.synonyms.filter((s) => s.primary);

				return (
					primary.length > 1 ||
					(primary.length === 1 &&
						!s.synonyms.some((s) => !s.primary && s.value === primary[0].value))
				);
			}),
		[sets],
	);

	/**
	 * Synonym sets.
	 *
	 * Synonym sets are sets without a primary term.
	 */
	const synonyms = useMemo(
		() =>
			sets.filter((s) => {
				return !s.synonyms.some((s) => s.primary);
			}),
		[sets],
	);

	/**
	 * Add a set.
	 *
	 * @param {Array} synonyms New synonyms.
	 * @returns {void}
	 */
	const addSet = (synonyms) => {
		const updated = [...sets, mapEntry(synonyms)];

		setSets(updated);
		setIsDirty(true);
	};

	/**
	 * Delete sets.
	 *
	 * @param {Array} ids IDs of sets to remove.
	 * @returns {void}.
	 */
	const deleteSets = (ids) => {
		const updated = sets.filter((s) => !ids.includes(s.id));

		setSets(updated);
		setIsDirty(true);
	};

	/**
	 * Update a set.
	 *
	 * @param {string} id ID of set to update.
	 * @param {Array} synonyms New synonyms.
	 * @returns {void}
	 */
	const updateSet = (id, synonyms) => {
		const updated = sets.map((s) => (s.id === id ? mapEntry(synonyms, id) : s));

		setSets(updated);
		setIsDirty(true);
	};

	/**
	 * Update Solr data.
	 *
	 * @param {string} solr Solr data.
	 */
	const updateSolr = (solr) => {
		setSolr(solr);
		setIsDirty(true);
	};

	/**
	 * Update Solr data from groups.
	 *
	 * @returns {void}
	 */
	const updateSolrFromSets = () => {
		const updated = reduceStateToSolr({
			alternatives: [...hyponyms, ...replacements],
			sets: synonyms,
		});

		setSolr(updated);
	};

	/**
	 * Update synonym groups from Solr data.
	 *
	 * @returns {void}
	 */
	const updateSetsFromSolr = () => {
		const { alternatives, sets } = reduceSolrToState(solr);

		setSets([...alternatives, ...sets]);
		setIsDirty(true);
	};

	/**
	 * Validate a set of synonyms.
	 *
	 * @param {Object} hypernym Hypernym.
	 * @param {Array} hyponyms Hyponyms.
	 * @returns {boolean}
	 */
	const validateHyponyms = (hypernym, hyponyms) => {
		const isHypernymValid = hypernym.value.trim().length > 0;
		const isHyponymsValid =
			hyponyms.filter((s) => s.value.trim()).filter((s) => s.value !== hypernym.value)
				.length > 0;

		return isHypernymValid && isHyponymsValid;
	};

	/**
	 * Validate a set of replacements.
	 *
	 * @param {Array} terms Terms.
	 * @param {Array} replacements Replacements
	 * @returns {boolean}
	 */
	const validateReplacements = (terms, replacements) => {
		const isTermsValid = terms.filter((s) => s.value.trim()).length > 0;
		const isReplacementsValid = replacements.filter((s) => s.value.trim()).length > 0;

		return isTermsValid && isReplacementsValid;
	};

	/**
	 * Validate a set of synonyms.
	 *
	 * @param {Array} synonyms Synonyms.
	 * @returns {boolean}
	 */
	const validateSynonyms = (synonyms) => {
		return synonyms.length > 1;
	};

	/**
	 * Validate synonyms.
	 *
	 * @returns {void}
	 */
	const validate = () => {
		setSets((sets) =>
			sets.map((s) => {
				const primary = s.synonyms.filter((s) => s.primary);
				const synonyms = s.synonyms.filter((s) => !s.primary);

				if (primary.length > 0) {
					if (primary.length === 1) {
						const valid = validateHyponyms(primary[0], synonyms);

						return { ...s, valid };
					}

					const valid = validateReplacements(primary, synonyms);

					return { ...s, valid };
				}

				const valid = validateSynonyms(synonyms);

				return { ...s, valid };
			}),
		);

		setIsDirty(false);
	};

	/**
	 * Select a group for editing.
	 *
	 * @param {string|null} id ID of group to edit, or null for none.
	 * @returns {void}
	 */
	const select = (id) => {
		setSelected(id);
	};

	/**
	 * Switch between Solor and visual editing.
	 *
	 * @returns {void}
	 */
	const switchEditor = () => {
		if (isSolr) {
			updateSetsFromSolr();
		} else {
			updateSolrFromSets();
		}

		setIsSolr(!isSolr);
		validate();
	};

	// eslint-disable-next-line react/jsx-no-constructed-context-values
	const contextValue = {
		addSet,
		deleteSets,
		hyponyms,
		isDirty,
		isSolr,
		select,
		selected,
		solr,
		replacements,
		switchEditor,
		synonyms,
		updateSet,
		updateSolr,
		updateSetsFromSolr,
		updateSolrFromSets,
		validateHyponyms,
		validateReplacements,
		validateSynonyms,
	};

	return <Context.Provider value={contextValue}>{children}</Context.Provider>;
};

/**
 * Use the Synonyms context.
 *
 * @returns {object} API Search Context.
 */
export const useSynonymsSettings = () => {
	return useContext(Context);
};
