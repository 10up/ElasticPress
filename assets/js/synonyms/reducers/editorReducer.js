import { reduceSolrToState, reduceStateToSolr, mapEntry } from '../utils';

/**
 * The synonym editor reducer.
 */

const { alternatives, sets, initialMode } = window.epSynonyms.data;
const mappedSets = sets ? sets.map(mapEntry) : [mapEntry()];
const mappedAlternatives = alternatives ? alternatives.map(mapEntry) : [mapEntry()];
const initialState = {
	isSolrEditable: initialMode === 'advanced',
	isSolrVisible: initialMode === 'advanced',
	alternatives: mappedAlternatives,
	sets: mappedSets,
	solr: reduceStateToSolr({ sets: mappedSets, alternatives: mappedAlternatives }),
	dirty: false,
	submit: false,
};

/**
 * editorReducer
 *
 * @param {Object} state  Current state.
 * @param {Object} action The action.
 * @return {Object} New state.
 */
const editorReducer = (state, action) => {
	switch (action.type) {
		case 'ADD_SET':
			return {
				...state,
				sets: [...state.sets, mapEntry()],
				dirty: true,
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
				dirty: true,
			};
		case 'REMOVE_SET':
			return {
				...state,
				sets: state.sets.filter(({ id }) => id !== action.data),
				dirty: true,
			};
		case 'ADD_ALTERNATIVE':
			return {
				...state,
				alternatives: [...state.alternatives, mapEntry()],
				dirty: true,
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
				dirty: true,
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
				dirty: true,
			};
		case 'REMOVE_ALTERNATIVE':
			return {
				...state,
				alternatives: state.alternatives.filter(({ id }) => id !== action.data),
				dirty: true,
			};
		case 'SET_SOLR_EDITABLE':
			return {
				...state,
				isSolrEditable: !!action.data,
				isSolrVisible: !!action.data,
			};
		case 'UPDATE_SOLR':
			return {
				...state,
				solr: action.data,
				dirty: true,
			};
		case 'REDUCE_SOLR_TO_STATE':
			return {
				...reduceSolrToState(state.solr, state),
				dirty: true,
			};
		case 'REDUCE_STATE_TO_SOLR':
			return {
				...state,
				solr: reduceStateToSolr(state),
			};
		case 'VALIDATE_ALL':
			return {
				...state,
				sets: state.sets.map((set) => ({
					...set,
					valid: set.synonyms.length > 1,
				})),
				alternatives: state.alternatives.map((alternative) => ({
					...alternative,
					valid:
						alternative.synonyms.length > 1 &&
						!!alternative.synonyms.filter(
							({ primary, value }) => primary && value.length,
						).length,
				})),
				dirty: false,
			};
		case 'SUBMIT':
			return {
				...state,
				submit: true,
			};
		default:
			return state;
	}
};

export { editorReducer, initialState };
