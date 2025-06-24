/**
 * Test suite for the feature selection interface in ElasticPress settings.
 *
 * @module FeatureInterface
 */
// eslint-disable-next-line jest/valid-describe-callback
describe('Feature Grouping and Persistence', { tags: '@slow' }, () => {
	/**
	 * CSS selector for the open "Live Search" feature panel.
	 * @constant
	 * @type {string}
	 */
	const panelSelector = 'div[id*="Live Search-view"]:has(.is-opened)';

	it('Renders group tabs, persists across reloads, and supports field dependency', () => {
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

		// Verifies that the group and feature tabs are clickable and persist their selections.
		// eslint-disable-next-line cypress/unsafe-to-chain-command
		cy.get('button[id*="Live Search"]')
			.click()
			.then(() => {
				// eslint-disable-next-line cypress/unsafe-to-chain-command
				cy.get('button[id*="autosuggest"]')
					.click()
					.then(() => {
						// Verify the feature is active
						cy.get('div[id*="autosuggest-view"]').should('be.visible');

						// Reload the page to test persistence
						cy.reload();

						// Wait for UI to load completely
						cy.get('.ep-settings-page form').should('be.visible');

						// Verify group selection persisted
						cy.get('div[id*="Live Search-view"]').should('be.visible');

						// Verify feature selection persisted
						cy.get('div[id*="autosuggest-view"]').should('be.visible');
					});
			});

		cy.visit('/wp-admin/admin.php?page=elasticpress');

		// open the panel
		cy.contains('button', 'Core Search').click();
		cy.contains('button', 'Post Search').click();

		// find both “Highlight search terms” controls (plain + “in excerpts”)
		cy.get('.ep-dashboard-control')
			.filter(':contains("Highlight search terms")')
			.each(($ctl) => {
				cy.wrap($ctl)
					.find('input')
					.then(($input) => {
						if ($input.is(':checked')) {
							cy.wrap($input).click();
						}
					});
			});

		// dependent control should be hidden
		cy.contains('.ep-dashboard-control', 'Highlight tag').should('not.exist');

		// find both “Highlight search terms” controls (plain + “in excerpts”)
		cy.get('.ep-dashboard-control')
			.filter(':contains("Highlight search terms")')
			.each(($ctl) => {
				cy.wrap($ctl)
					.find('input')
					.then(($input) => {
						if (!$input.is(':checked')) {
							cy.wrap($input).click();
						}
					});
			});

		// now the dependent control appears
		cy.contains('.ep-dashboard-control', 'Highlight tag').should('exist').and('be.visible');
	});
});
