/**
 * Internal dependencies.
 */
import { matchType } from './config';
import { clearFacetsFromArgs } from './functions';

/**
 * Initial state.
 */
export const initialState = {
	aggregations: {},
	args: {
		highlight: '',
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
	isPoppingState: false,
	searchResults: [],
	searchedTerm: '',
	totalResults: 0,
};

/**
 * Reducer function for handling state changes.
 *
 * @param {object} state          The current state.
 * @param {object} action         Action data.
 * @param {string} action.type    The action name.
 * @param {object} action.payload New state data from the action.
 * @returns {object} Updated state.
 */
export const reducer = (state, { type, payload }) => {
	const newState = { ...state, isPoppingState: false };

	switch (type) {
		case 'APPLY_ARGS': {
			newState.args = { ...newState.args, ...payload, offset: 0 };
			newState.isOpen = true;
			break;
		}
		case 'CLEAR_FACETS': {
			newState.args = clearFacetsFromArgs(newState.args);
			break;
		}
		case 'NEW_SEARCH_TERM': {
			newState.args = clearFacetsFromArgs(newState.args);
			newState.args.offset = 0;
			newState.args.search = payload;

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
		case 'CLOSE_MODAL': {
			newState.args = clearFacetsFromArgs(newState.args);
			newState.isOpen = false;
			break;
		}
		case 'POP_STATE': {
			const { isOpen, ...args } = payload;

			newState.args = args;
			newState.isOpen = isOpen;
			newState.isPoppingState = true;

			break;
		}
		default:
			break;
	}

	return newState;
};
