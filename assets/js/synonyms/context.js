/**
 * WordPress dependencies.
 */
import { createContext, useReducer, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { editorReducer, initialState } from './reducers/editorReducer';

const State = createContext();
const Dispatch = createContext();

/**
 * App Context.
 *
 * @param {object} props Props.
 * @returns {WPElement} AppContext component
 */
const AppContext = (props) => {
	const { children } = props;
	const [state, dispatch] = useReducer(editorReducer, initialState);

	return (
		<State.Provider value={state}>
			<Dispatch.Provider value={dispatch}>{children}</Dispatch.Provider>
		</State.Provider>
	);
};

export { AppContext, State, Dispatch };
