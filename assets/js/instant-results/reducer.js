/**
 * Internal dependencies.
 */
import { facets, highlightTag, matchType } from './config';

/**
 * Initial state.
 */
export const initialArg = {
	aggregations: {},
	args: {
		highlight: highlightTag,
		offset: 0,
		orderby: 'relevance',
		order: 'desc',
		per_page: 6,
		relation: matchType === 'all' ? 'and' : 'or',
		search: '',
	},
	isLoading: false,
	isOpen: false,
	isSidebarOpen: false,
	searchResults: [],
	searchedTerm: '',
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
			newState.args = { ...state.args, ...payload, offset: 0 };
			break;
		}
		case 'CLEAR_FILTERS': {
			newState.args = facets.reduce(
				(args, { name, type }) => {
					switch (type) {
						case 'price_range':
							delete args.max_price;
							delete args.min_price;
							break;
						default:
							delete args[name];
							break;
					}

					return args;
				},
				{ ...state.args },
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
				aggregations,
			} = payload;

			/**
			 * Total number of items.
			 */
			const totalNumber = typeof total === 'number' ? total : total.value;

			newState.aggregations = aggregations;
			newState.searchResults = hits;
			newState.searchedTerm = newState.args.search;
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
