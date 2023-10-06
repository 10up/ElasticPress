/**
 * Internal dependencies.
 */
import { getArgsWithoutConstraints } from './utilities';

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
			newState.args = { ...newState.args, ...action.args, offset: 0 };
			newState.isOn = true;
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
			newState.searchResults = hits;
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
