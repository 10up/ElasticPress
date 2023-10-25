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
			highlight_enabled: '1',
			highlight_excerpt: '1',
			highlight_tag: 'mark',
		},
		related_posts: {
			active: true,
		},
		facets: {
			active: true,
		},
		searchordering: {
			active: true,
		},
		autosuggest: {
			active: true,
		},
		woocommerce: {
			active: false,
		},
		protected_content: {
			active: false,
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
