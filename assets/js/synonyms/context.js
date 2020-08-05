import React, { createContext, useReducer } from 'react';
import { editorReducer, initialState } from './reducers/editorReducer';

const State = createContext();
const Dispatch = createContext();

/**
 * App Context.
 * @param {Object} props Props.
 */
const AppContext = props => {
	const [ state, dispatch ] = useReducer( editorReducer, initialState );

	window.appState = state;
	window.appDispatch = dispatch;
	return(
		<State.Provider value={state}>
			<Dispatch.Provider value={dispatch}>
				{ props.children }
			</Dispatch.Provider>
		</State.Provider>
	);
};

export {
	AppContext,
	State,
	Dispatch
};
