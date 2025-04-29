/**
 * @file features/groups.cy.js
 * @description Cypress test suite for ElasticPress Feature Grouping Tabs in the WordPress admin.
 */

/**
 * Test suite for the Feature Grouping functionality in ElasticPress settings.
 *
 * @module FeatureGrouping
 */
describe('Feature Grouping', () => {
	/**
	 * CSS selector for the open "Live Search" feature panel.
	 * @constant
	 * @type {string}
	 */
	const panelSelector = 'div[id*="Live Search-view"]:has(.is-opened)';

	/**
	 * Test that verifies:
	 * - Feature group tabs render correctly.
	 * - Tab switching works as expected.
	 *
	 * @function
	 * @name renders feature group tabs and handles tab switching
	 * @memberof module:FeatureGrouping
	 */
	it('renders feature group tabs and handles tab switching', () => {
		// Log in as an admin user (assumes cy.login() is a custom Cypress command).
		cy.login();

		// Visit the ElasticPress settings page in the WordPress admin.
		cy.visit('/wp-admin/admin.php?page=elasticpress');

		// Ensure the settings form is visible.
		cy.get('.ep-settings-page form').should('be.visible');

		// Find and click the "Live Search" feature group tab button.
		cy.get('button[id*="Live Search"]').should('be.visible').and('not.be.disabled').click();

		// Assert that the "Live Search" panel is open and visible.
		cy.get(panelSelector, { timeout: 30000 }).should('exist').and('be.visible');
	});
});
