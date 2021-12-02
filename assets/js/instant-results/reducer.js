/**
 * Internal dependencies.
 */
import { highlightTag, matchType } from './config';

/**
 * Initial state.
 */
export const initialArg = {
	args: {
		highlight: highlightTag,
		offset: 0,
		orderby: 'relevance',
		order: 'desc',
		per_page: 6,
		relation: matchType === 'all' ? 'and' : 'or',
		search: '',
		elasticpress: '1',
	},
	isLoading: false,
	isOpen: false,
	isSidebarOpen: false,
	poppingState: false,
	postTypes: [],
	postTypesAggregation: {},
	priceRange: [],
	priceRangeAggregations: {},
	searchResults: [],
	searchedTerm: '',
	taxonomyTerms: {},
	taxonomyTermsAggregations: {},
	totalResults: 0,
};

/**
 * Reducer function for handling state changes.
 *
 * @param {Object} state The current state.
 * @param {Object} action Action data.
 * @param {string} action.type The action name.
 * @param {Object} action.payload New state data from the action.
 * @return {Object} Updated state.
 */
export const reducer = (state, { type, payload }) => {
	const newState = { ...state };

	switch (type) {
		case 'CLEAR_FILTERS': {
			newState.postTypes = [];
			newState.priceRange = [];
			newState.taxonomyTerms = {};
			break;
		}
		case 'POP_STATE': {
			newState.args = payload.args;
			newState.isOpen = payload.isOpen;
			newState.poppingState = true;
			newState.postTypes = payload.postTypes;
			newState.priceRange = payload.priceRange;
			newState.taxonomyTerms = payload.taxonomyTerms;
			break;
		}
		case 'SET_SEARCH_TERM': {
			newState.args.offset = 0;
			newState.args.search = payload;

			if (payload) {
				newState.postTypes = [];
				newState.priceRange = [];
				newState.taxonomyTerms = {};
			}

			break;
		}
		case 'SET_POST_TYPES': {
			newState.args.offset = 0;
			newState.postTypes = payload;
			break;
		}
		case 'SET_PRICE_RANGE': {
			newState.args.offset = 0;
			newState.priceRange = payload;
			break;
		}
		case 'SET_TAXONOMY_TERMS': {
			const newTaxonomyTerms = { ...newState.taxonomyTerms };

			newTaxonomyTerms[payload.taxonomy] = payload.terms;
			newState.args.offset = 0;
			newState.taxonomyTerms = newTaxonomyTerms;
			break;
		}
		case 'SORT_RESULTS': {
			newState.args.offset = 0;
			newState.args.order = payload.order;
			newState.args.orderby = payload.orderby;
			break;
		}
		case 'NEW_SEARCH_RESULTS': {
			const {
				hits: { hits, total },
				aggregations: {
					post_type: postTypesAggregation,
					price_range: priceRangeAggregation,
					...taxonomyTermsAggregations
				} = {},
			} = payload;

			newState.poppingState = false;
			newState.postTypesAggregation = postTypesAggregation;
			newState.priceRangeAggregations = priceRangeAggregation;
			newState.searchResults = hits;
			newState.searchedTerm = newState.args.search;
			newState.taxonomyTermsAggregations = taxonomyTermsAggregations;
			newState.totalResults = total;

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
		case 'START_LOADING': {
			newState.isLoading = true;
			break;
		}
		case 'FINISH_LOADING': {
			newState.isLoading = false;
			break;
		}
		case 'TOGGLE_SIDEBAR': {
			newState.isSidebarOpen = !state.isSidebarOpen;
			break;
		}
		case 'OPEN_MODAL': {
			newState.isOpen = true;
			break;
		}
		case 'CLOSE_MODAL': {
			newState.isOpen = false;
			break;
		}
		default:
			break;
	}

	return newState;
};
