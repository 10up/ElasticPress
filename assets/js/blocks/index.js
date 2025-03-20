/**
 * WordPress dependencies.
 */
import { createReduxStore, register, registerStore } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import options from './store';

/**
 * Register data store.
 */
if (typeof createReduxStore === 'function') {
	const store = createReduxStore('elasticpress', options);

	register(store);
} else {
	registerStore('elasticpress', options);
}
