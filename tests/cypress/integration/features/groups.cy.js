/**
 * features/groups.cy.js
 * Test suite for ElasticPress Feature Grouping Tabs
 */

describe('Feature Grouping', () => {
	const panelSelector = 'div[id*="Live Search-view"][data-open="true"]';

	it('renders feature group tabs and handles tab switching', () => {
		cy.login();
		cy.visit('/wp-admin/admin.php?page=elasticpress');

		cy.get('.ep-settings-page form').should('be.visible');

		cy.get('button[id*="Live Search"]').should('be.visible').and('not.be.disabled').click();

		cy.get(panelSelector, { timeout: 30000 }) // Wait up to 30s for the panel itself
			.should('exist')
			.and('be.visible')
			.should('have.attr', 'data-open', 'true'); // Crucially, wait for the attribute change
	});
});
