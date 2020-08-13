import { reduceSolrToState, mapEntry } from '../utils';

/**
 * The synonym editor reducer.
 */

const { alternatives, sets } = window.epSynonyms.data;

const initialState = {
	isSolrEditable: false,
	isSolrVisible: false,
	alternatives: alternatives ? alternatives.map(mapEntry) : [mapEntry()],
	sets: sets ? sets.map(mapEntry) : [mapEntry()],
};

/**
 * editorReducer
 *
 * @param {object} state  Current state.
 * @param {object} action The action.
 * @returns {object} New state.
 */
const editorReducer = (state, action) => {
	switch (action.type) {
		case 'ADD_SET':
			return {
				...state,
				sets: [...state.sets, mapEntry()],
			};
		case 'UPDATE_SET':
			return {
				...state,
				sets: state.sets.map((entry) => {
					if (entry.id !== action.data.id) {
						return entry;
					}
					return mapEntry(action.data.tokens, action.data.id);
				}),
			};
		case 'REMOVE_SET':
			return {
				...state,
				sets: state.sets.filter(({ id }) => id !== action.data),
			};
		case 'ADD_ALTERNATIVE':
			return {
				...state,
				alternatives: [...state.alternatives, mapEntry()],
			};
		case 'UPDATE_ALTERNATIVE':
			return {
				...state,
				alternatives: [
					...state.alternatives.map((entry) => {
						if (entry.id !== action.data.id) {
							return entry;
						}
						return mapEntry(
							[...action.data.tokens, ...entry.synonyms.filter((t) => t.primary)],
							action.data.id,
						);
					}),
				],
			};
		case 'UPDATE_ALTERNATIVE_PRIMARY':
			return {
				...state,
				alternatives: [
					...state.alternatives.map((entry) => {
						if (entry.id !== action.data.id) {
							return entry;
						}
						return mapEntry(
							[action.data.token, ...entry.synonyms.filter((t) => !t.primary)],
							action.data.id,
						);
					}),
				],
			};
		case 'REMOVE_ALTERNATIVE':
			return {
				...state,
				alternatives: state.alternatives.filter(({ id }) => id !== action.data),
			};
		case 'SET_SOLR_EDITABLE':
			return {
				...state,
				isSolrEditable: !!action.data,
			};
		case 'REDUCE_STATE_FROM_SOLR':
			return reduceSolrToState(action.data, state);
		default:
			return state;
	}
};

export { editorReducer, initialState };
