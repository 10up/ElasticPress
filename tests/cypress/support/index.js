// ***********************************************************
// This example support/index.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './assertions';
import './commands';
import './global-hooks';

// Import cypress grep
import registerCypressGrep from '@cypress/grep';

registerCypressGrep();

// Alternatively you can use CommonJS syntax:
// require('./commands')

cy.elasticPress = {
	defaultFeatures: {
		search: {
			active: 1,
			highlight_enabled: true,
			highlight_excerpt: true,
			highlight_tag: 'mark',
			highlight_color: '#157d84',
		},
		related_posts: {
			active: 1,
		},
		facets: {
			active: 1,
		},
		searchordering: {
			active: 1,
		},
		autosuggest: {
			active: 1,
		},
		woocommerce: {
			active: 0,
		},
		protected_content: {
			active: 0,
		},
	},
};

/**
 * Ignore ResizeObserver error.
 *
 * @see {@link https://stackoverflow.com/questions/49384120/resizeobserver-loop-limit-exceeded}
 */
Cypress.on('uncaught:exception', (err) => {
	if (err.message?.includes('ResizeObserver loop limit exceeded')) {
		return false;
	}

	return err;
});
