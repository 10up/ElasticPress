/**
 * Internal dependencies.
 */
import { getArgsWithoutConstraints } from './utilities';

/**
 * Reorder search results to respect Custom Results ordering.
 *
 * Posts with an ep_custom_result taxonomy term matching the search term
 * are placed at their specified term_order positions. Mirrors the PHP
 * SearchOrdering::posts_results() logic.
 *
 * @param {Array}  hits       Elasticsearch hits array.
 * @param {string} searchTerm Current search term.
 * @returns {Array} Reordered hits.
 */
const applyCustomResultsOrdering = (hits, searchTerm) => {
	if (!searchTerm || !hits.length) {
		return hits;
	}

	const normalizedTerm = searchTerm.toLowerCase();
	const toInject = {};
	const remaining = [];

	for (const hit of hits) {
		const customResults = hit._source?.terms?.ep_custom_result;
		const matchingTerm = Array.isArray(customResults)
			? customResults.find((term) => term.name && term.name.toLowerCase() === normalizedTerm)
			: null;

		if (matchingTerm && matchingTerm.term_order) {
			toInject[matchingTerm.term_order] = hit;
		} else {
			remaining.push(hit);
		}
	}

	if (!Object.keys(toInject).length) {
		return hits;
	}

	const sortedPositions = Object.keys(toInject)
		.map(Number)
		.sort((a, b) => a - b);

	for (const position of sortedPositions) {
		remaining.splice(position - 1, 0, toInject[position]);
	}

	return remaining;
};

/**
 * Reducer function.
 *
 * @param {object} state Current state.
 * @param {object} action Action data.
 * @returns {object} Updated state.
 */
export default (state, action) => {
	const newState = { ...state, isPoppingState: false };

	switch (action.type) {
		case 'CLEAR_CONSTRAINTS': {
			const clearedArgs = getArgsWithoutConstraints(newState.args, newState.argsSchema);

			newState.args = clearedArgs;
			newState.args.offset = 0;

			break;
		}
		case 'CLEAR_RESULTS': {
			newState.aggregations = {};
			newState.searchResults = [];
			newState.totalResults = 0;
			break;
		}
		case 'SEARCH': {
			const { updateDefaults, ...args } = action.args;

			newState.args = { ...newState.args, ...args, offset: 0 };
			newState.isOn = true;

			if (updateDefaults && args.post_type.length) {
				newState.argsSchema.post_type.default = args.post_type;
			}

			break;
		}
		case 'SEARCH_FOR': {
			const clearedArgs = getArgsWithoutConstraints(newState.args, newState.argsSchema);

			newState.args = clearedArgs;
			newState.args.search = action.searchTerm;
			newState.args.offset = 0;
			newState.isOn = true;

			break;
		}
		case 'SET_IS_LOADING': {
			newState.isLoading = action.isLoading;
			break;
		}
		case 'TURN_OFF': {
			newState.args = { ...newState.args };
			newState.isOn = false;
			break;
		}
		case 'SET_RESULTS': {
			const {
				hits: { hits, total },
				aggregations,
				suggest,
			} = action.response;

			newState.isFirstSearch = false;

			/**
			 * Total number of items.
			 */
			const totalNumber = typeof total === 'number' ? total : total.value;

			newState.aggregations = aggregations;
			newState.searchResults = applyCustomResultsOrdering(hits, newState.args.search);
			newState.searchTerm = newState.args.search;
			newState.totalResults = totalNumber;
			newState.suggestedTerms = suggest?.ep_suggestion?.[0]?.options || [];

			break;
		}
		case 'NEXT_PAGE': {
			newState.args.offset += newState.args.per_page;
			break;
		}
		case 'PREVIOUS_PAGE': {
			newState.args.offset = Math.max(newState.args.offset - newState.args.per_page, 0);
			break;
		}
		case 'SET_OFFSET': {
			const offset = Number.isFinite(action.offset) ? action.offset : 0;
			newState.args.offset = Math.max(offset, 0);
			break;
		}
		case 'POP_STATE': {
			const { isOn, args } = action.args;

			newState.args = args;
			newState.isOn = isOn;
			newState.isPoppingState = true;

			break;
		}
		default:
			break;
	}

	return newState;
};
