/**
 * External dependencies.
 */
import { v4 as uuidv4 } from 'uuid';

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * @typedef Synonym
 * @property {string} value The synonym value.
 * @property {boolean} primary Whether the synonym is a primary term.
 *
 * @typedef Rule
 * @property {string} id Rule ID.
 * @property {Synonym[]} synonyms Rule synonyms.
 * @property {boolean} valid Whether the rule is valid.
 */

/**
 * Determine whether a synonym is a primary term.
 *
 * @param {Synonym} synonym Synonym.
 * @returns {boolean}
 */
const isPrimary = (synonym) => {
	return synonym.primary;
};

/**
 * Determine whether a synonym is not a primary term.
 *
 * @param {Synonym} synonym Synonym.
 * @returns {boolean}
 */
const isNotPrimary = (synonym) => {
	return !synonym.primary;
};

/**
 * Get a rule object for a list of synonyms,
 *
 * @param {Synonym[]} synonyms Array of synonyms.
 * @param {string} id Rule ID.
 * @returns {Rule} Map entry
 */
const getRule = (synonyms = [], id = '') => {
	return {
		id: id.length ? id : uuidv4(),
		synonyms,
		valid: true,
	};
};

/**
 * Get a rule in Solr format.
 *
 * @param {Rule} rule Rule set.
 * @param {Synonym[]} rule.synonyms Rule synonyms.
 * @returns {string}
 */
const getSolr = ({ synonyms }) => {
	const terms = synonyms.filter(isPrimary);
	const replacements = synonyms.filter(isNotPrimary);

	const sides = [terms, replacements]
		.map((side) =>
			side
				.map((synonym) => synonym.value.trim())
				.filter((synonym) => !!synonym)
				.join(', '),
		)
		.filter((side) => side);

	return sides.join(' => ');
};

/**
 * Get synonyms from a Solr line.
 *
 * @param {string} line Solr line.
 * @returns {Synonym[]}
 */
const getSynonyms = (line) => {
	const parts = line.split('=>').map((p, i, a) => {
		const part = p
			.split(',')
			.map((v) => v.trim())
			.filter((v) => v);

		return part
			.filter((v, i) => part.indexOf(v) === i)
			.filter((v) => v)
			.map((v) => ({
				label: v,
				value: v,
				primary: a.length === 2 && i === 0,
			}));
	});

	return parts.flat();
};

/**
 * Determine whether a rule describes hyponyms.
 *
 * Hyponyms are rules where there is a single primary term and where the
 * primary term is also included as a replacement.
 *
 * @param {Rule} rule Rule set.
 * @param {Synonym[]} rule.synonyms Rule synonyms.
 * @returns {boolean}
 */
const isHyponyms = (rule) => {
	const hypernyms = rule.synonyms.filter(isPrimary);

	return (
		hypernyms.length === 1 &&
		rule.synonyms.filter(isNotPrimary).some((s) => hypernyms.some((h) => h.value === s.value))
	);
};

/**
 * Validate a new set of hyponyms.
 *
 * Hyponyms are valid if there is only one primary term and at least one
 * replacement that is not also the primary term.
 *
 * This function is used before the hypernym is automatically injected as a
 * hypernym, so make sure to use `isHyponyms` first to verify that the hypernym
 * is included as a hyponym.
 *
 * @param {Array} synonyms Synonyms.
 * @returns {boolean}
 */
const isHyponymsValid = (synonyms) => {
	const hypernyms = synonyms.filter(isPrimary);
	const hyponyms = synonyms
		.filter(isNotPrimary)
		.filter((s) => !hypernyms.some((h) => h.value === s.value));

	return hypernyms.length === 1 && hyponyms.length > 0;
};

/**
 * Determine whether a rule describes replacements.
 *
 * Replacements are rules where there are terms and replacements that do not
 * otherwise describe hyponyms.
 *
 * @param {Rule} rule Rule set.
 * @param {Synonym[]} rule.synonyms Rule synonyms.
 * @returns {boolean}
 */
const isReplacements = ({ synonyms }) => {
	return !isHyponyms({ synonyms }) && synonyms.some(isPrimary);
};

/**
 * Validate a new set of replacements.
 *
 * Replacements are valid if there is at least one term and one replacement.
 *
 * @param {Array} synonyms Synonyms.
 * @returns {boolean}
 */
const isReplacementsValid = (synonyms) => {
	return !isHyponyms({ synonyms }) && synonyms.some(isPrimary) && synonyms.some(isNotPrimary);
};

/**
 * Is a list of synonyms a synonyms rule set.
 *
 *
 * @param {Rule} rule Rule set.
 * @param {Synonym[]} rule.synonyms Rule synonyms.
 * @returns {boolean}
 */
const isSynonyms = ({ synonyms }) => {
	return synonyms.every(isNotPrimary);
};

/**
 * Is a synonyms rule set valid.
 *
 * @param {Array} synonyms Rule synonyms.
 * @returns {boolean}
 */
const isSynonymsValid = (synonyms) => {
	return synonyms.length > 1 && synonyms.every(isNotPrimary);
};

/**
 * Reduce state to Solr spec.
 *
 * @param {Rule[]} rules Array of rule sets.
 * @returns {string} new state
 */
const getSolrFromRules = (rules) => {
	const synonyms = rules.filter(isSynonyms).map(getSolr);
	const hyponyms = rules.filter(isHyponyms).map(getSolr);
	const replacements = rules.filter(isReplacements).map(getSolr);

	const lines = [
		__('#Defined synonyms.', 'elasticpress'),
		'',
		...synonyms,
		'',
		__('#Defined hyponyms.', 'elasticpress'),
		'',
		...hyponyms,
		'',
		__('#Defined replacements.', 'elasticpress'),
		'',
		...replacements,
		'',
	];

	return lines.join('\n');
};

/**
 * Reduce Solr text file to State.
 *
 * @param {string} solr A string in the Solr parseable synonym format.
 * @returns {Rule[]} State
 */
const getRulesFromSolr = (solr) => {
	const rules = solr.split(/\r?\n/).reduce((rules, line) => {
		if (line.indexOf('#') === 0) {
			return rules;
		}

		if (line.trim().length === 0) {
			return rules;
		}

		const synonyms = getSynonyms(line);
		const rule = getRule(synonyms);

		rules.push(rule);

		return rules;
	}, []);

	return rules;
};

/**
 * Generate universally unique identifier.
 *
 * @returns {string} A universally unique identifier
 */
const uuid = () => {
	return uuidv4();
};

export {
	isHyponyms,
	isHyponymsValid,
	isReplacements,
	isReplacementsValid,
	isSynonyms,
	isSynonymsValid,
	getRule,
	getRulesFromSolr,
	getSolrFromRules,
	uuid,
};
