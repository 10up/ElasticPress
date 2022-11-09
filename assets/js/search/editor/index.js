/**
 * WordPress dependencies.
 */
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies.
 */
import ExcludeFromSearch from './plugins/exclude-from-search';

registerPlugin('ep-exclude-from-search', {
	render: ExcludeFromSearch,
	icon: null,
});
