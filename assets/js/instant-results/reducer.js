/**
 * Internal dependencies.
 */
import { facets, highlightTag, matchType } from './config';

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
	filters: {},
	isLoading: false,
	isOpen: false,
	isSidebarOpen: false,
	poppingState: false,
	postTypesAggregation: {},
	priceRangeAggregations: {},
	searchResults: [],
	searchedTerm: '',
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
		case 'APPLY_FILTERS': {
			newState.args.offset = 0;
			newState.filters = { ...state.filters, ...payload };

			break;
		}
		case 'CLEAR_FILTERS': {
			newState.filters = facets.reduce(
				(filters, { name }) => {
					delete filters[name];

					return filters;
				},
				{ ...state.filters },
			);

			break;
		}
		case 'SET_SEARCH_TERM': {
			newState.args.offset = 0;
			newState.args.search = payload;
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

			/**
			 * Total number of items.
			 */
			const totalNumber = typeof total === 'number' ? total : total.value;

			newState.postTypesAggregation = postTypesAggregation;
			newState.priceRangeAggregations = priceRangeAggregation;
			newState.searchResults = hits;
			newState.searchedTerm = newState.args.search;
			newState.taxonomyTermsAggregations = taxonomyTermsAggregations;
			newState.totalResults = totalNumber;

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
