/**
 * Test suite for the feature selection interface in ElasticPress settings.
 *
 * @module FeatureInterface
 */
describe('Feature Grouping and Persistence', () => {
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

		// Field grouping test
		cy.visit('/wp-admin/admin.php?page=elasticpress');

		cy.contains('button', 'Core Search').click();
		cy.contains('button', 'Post Search').click();

		cy.contains('.ep-field-group', 'Highlighting Options').as('fieldGroup');
		cy.get('@fieldGroup').should('exist');

		cy.visit('/wp-admin/admin.php?page=elasticpress');

		// Test case to verify a conditional feature is hidden until its requirement is met
		cy.window()
			.then((win) => {
				// Wait until epDashboard and features are available
				return new Cypress.Promise((resolve) => {
					const check = () => {
						if (win.epDashboard && win.epDashboard.features) {
							resolve(win);
						} else {
							setTimeout(check, 50);
						}
					};
					check();
				});
			})
			.then((win) => {
				win.epDashboard.features[0].settingsSchema.push({
					default: '1',
					key: 'test_field',
					label: 'Testing Field 1',
					options: [
						{
							label: 'Option A',
							value: '0',
						},
						{
							label: 'Option B',
							value: '1',
						},
					],
					type: 'radio',
				});
				win.epDashboard.features[0].settingsSchema.push({
					default: '1',
					key: 'test_field_2',
					label: 'Testing Field 2',
					options: [
						{
							label: 'Option A',
							value: '0',
						},
						{
							label: 'Option B',
							value: '1',
						},
					],
					type: 'radio',
					requires_fields: {
						conditions: {
							test_field: '0',
						},
					},
				});
			});

		// Toggle Re-Render
		cy.contains('button', 'Live Search').click();
		cy.contains('button', 'Core Search').click();

		cy.contains('.ep-dashboard-control', 'Testing Field 2').should('not.exist');

		cy.contains('.ep-dashboard-control', 'Testing Field 1').as('testField');

		// inside of testField, find the input with the label "Option A" and click it
		cy.get('@testField').find('input[value="0"]').click();

		// now, Testing Field 2 should be visible
		cy.contains('.ep-dashboard-control', 'Testing Field 2').should('exist');
	});
});
