import { reduceSolrToState } from '../utils';

/**
 * The synonym editor reducer.
 */

const { alternatives, sets } = window.epSynonyms.data;

const initialState = {
	isSolrEditable: false,
	alternatives: alternatives ? alternatives: [ [] ],
	sets: sets ? sets : [ [] ],
};

/**
 * editorReducer
 *
 * @param {Object} state  Current state.
 * @param {Object} action The action.
 * @return {Object} New state.
 */
const editorReducer = ( state, action ) => {
	switch( action.type ) {
			case 'ADD_SET':
				state.sets.push( [] );
				return { ...state };
			case 'UPDATE_SET':
				state.sets.splice( action.data.index, 1, action.data.tokens );
				return { ...state };
			case 'REMOVE_SET':
				return {
					...state,
					sets: [
						...state.sets.filter( ( t, i ) => i !== action.data )
					]
				};
			case 'ADD_ALTERNATIVE':
				state.alternatives.push( [] );
				return { ...state };
			case 'UPDATE_ALTERNATIVE':
				state.alternatives.splice( action.data.index, 1, [
					...action.data.tokens,
					...state.alternatives[ action.data.index ].filter( t => t.primary )
				] );
				return { ...state };
			case 'REMOVE_ALTERNATIVE':
				state.alternatives.splice( action.data, 1 );
				return { ...state };
			case 'UPDATE_ALTERNATIVE_PRIMARY':
				state.alternatives[ action.data.index ] = [
					...state.alternatives[ action.data.index ].filter( t => ! t.primary ),
					action.data.token
				];
				return { ...state };
			case 'SET_SOLR_EDITABLE':
				return {
					...state,
					isSolrEditable: ! ! action.data
				};
			case 'REDUCE_STATE_FROM_SOLR':
				return reduceSolrToState( action.data, state );
			default:
				return state;
	}
};

export {
	editorReducer,
	initialState
};
