/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	createContext,
	useContext,
	useEffect,
	useMemo,
	useState,
	WPElement,
} from '@wordpress/element';

/**
 * Internal dependencies.
 */
import {
	isHyponyms,
	isHyponymsValid,
	isReplacements,
	isReplacementsValid,
	isSynonyms,
	isSynonymsValid,
	getRule,
	getRulesFromSolr,
	getSolrFromRules,
} from './utils';

/**
 * Sync context.
 */
const Context = createContext();

/**
 * Synonyms settings context.
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
 * @param {object} props Props.
 * @param {string} props.apiUrl API Url.
 * @param {WPElement} props.children Component children.
 * @param {boolean} props.defaultIsSolr Whether the Solr editor is being used.
 * @param {Array} props.defaultSolr Default Solr.
 * @returns {WPElement} AppContext component
 */
export const SynonymsSettingsProvider = ({ apiUrl, children, defaultIsSolr, defaultSolr }) => {
	const defaultRules = useMemo(() => getRulesFromSolr(defaultSolr), [defaultSolr]);

	/**
	 * State.
	 */
	const [selected, setSelected] = useState(null);
	const [isBusy, setIsBusy] = useState(false);
	const [isDirty, setIsDirty] = useState(false);
	const [isSolr, setIsSolr] = useState(defaultIsSolr);
	const [rules, setRules] = useState(defaultRules);
	const [solr, setSolr] = useState(defaultSolr);

	/**
	 * Hyponym rules.
	 *
	 * @type {Rule[]}
	 */
	const hyponyms = useMemo(() => rules.filter(isHyponyms), [rules]);

	/**
	 * Replacement rules.
	 *
	 * @type {Rule[]}
	 */
	const replacements = useMemo(() => rules.filter(isReplacements), [rules]);

	/**
	 * Synonym rules.
	 *
	 * @type {Rule[]}
	 */
	const synonyms = useMemo(() => rules.filter(isSynonyms), [rules]);

	/**
	 * Add a rule.
	 *
	 * @param {Synonym[]} synonyms New synonyms.
	 * @returns {void}
	 */
	const addRule = (synonyms) => {
		const updatedRules = [...rules, getRule(synonyms)];

		setRules(updatedRules);
		setIsDirty(true);
	};

	/**
	 * Delete rules.
	 *
	 * @param {string[]} ids IDs of rules to remove.
	 * @returns {void}
	 */
	const deleteRules = (ids) => {
		const updatedRules = rules.filter((s) => !ids.includes(s.id));

		setRules(updatedRules);
		setIsDirty(true);
	};

	/**
	 * Update a rule.
	 *
	 * @param {string} id ID of rule to update.
	 * @param {Synonym[]} synonyms New synonyms.
	 * @returns {void}
	 */
	const updateRule = (id, synonyms) => {
		const updatedRules = rules.map((s) => (s.id === id ? getRule(synonyms, id) : s));

		setRules(updatedRules);
		setIsDirty(true);
	};

	/**
	 * Update rules.
	 *
	 * @param {Rule[]} rules New rules.
	 * @returns {void}
	 */
	const updateRules = (rules) => {
		setRules(rules);
		setIsDirty(true);
	};

	/**
	 * Update Solr data.
	 *
	 * @param {string} solr Solr data.
	 */
	const updateSolr = (solr) => {
		setSolr(solr);
		setIsDirty(false);
	};

	/**
	 * Update Solr data from groups.
	 *
	 * @returns {void}
	 */
	const updateSolrFromRules = () => {
		const updatedSolr = getSolrFromRules(rules);

		updateSolr(updatedSolr);
	};

	/**
	 * Update synonym groups from Solr data.
	 *
	 * @returns {void}
	 */
	const updateRulesFromSolr = () => {
		const updatedRules = getRulesFromSolr(solr);

		updateRules(updatedRules);
	};

	/**
	 * Validate synonyms.
	 *
	 * @returns {void}
	 */
	const validate = () => {
		setRules((rules) =>
			rules.map((r) => {
				const rule = { ...r };

				if (isHyponyms(rule)) {
					rule.valid = isHyponymsValid(rule.synonyms);
				} else if (isReplacements(rule)) {
					rule.valid = isReplacementsValid(rule.synonyms);
				} else {
					rule.valid = isSynonymsValid(rule.synonyms);
				}

				return rule;
			}),
		);
	};

	/**
	 * Select a rule for editing.
	 *
	 * @param {string|null} id ID of the rule to select.
	 * @returns {void}
	 */
	const select = (id) => {
		setSelected(id);
	};

	/**
	 * Switch between Solr and visual editing.
	 *
	 * @returns {void}
	 */
	const switchEditor = () => {
		if (isSolr) {
			updateRulesFromSolr();
		} else {
			updateSolrFromRules();
		}

		setIsSolr((isSolr) => !isSolr);
		validate();
	};

	/**
	 * Save settings.
	 *
	 * @returns {void}
	 */
	const save = async () => {
		setIsBusy(true);

		const updated = isDirty ? getSolrFromRules(rules) : solr;

		try {
			const response = await apiFetch({
				body: JSON.stringify({
					mode: isSolr ? 'advanced' : 'simple',
					solr: updated,
				}),
				headers: {
					'Content-Type': 'application/json',
				},
				method: 'PUT',
				url: apiUrl,
			});

			updateSolr(response.data);
			updateRulesFromSolr();
		} catch (e) {
			console.error(e); // eslint-disable-line no-console
			throw e;
		} finally {
			setIsBusy(false);
		}
	};

	/**
	 * Effects.
	 */
	useEffect(validate, []);

	// eslint-disable-next-line react/jsx-no-constructed-context-values
	const contextValue = {
		addRule,
		deleteRules,
		hyponyms,
		isBusy,
		isHyponymsValid,
		isReplacementsValid,
		isSolr,
		isSynonymsValid,
		select,
		selected,
		solr,
		replacements,
		save,
		switchEditor,
		synonyms,
		updateRule,
		updateSolr,
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
