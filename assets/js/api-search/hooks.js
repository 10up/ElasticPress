/**
 * WordPress Dependencies.
 */
import { useContext } from '@wordpress/element';

/**
 * Internal Dependencies.
 */
import Context from './src/context';

/**
 * Use the API Search context.
 *
 * @returns {object} API Search Context.
 */
export const useApiSearch = () => {
	return useContext(Context);
};
