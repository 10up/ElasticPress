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

	it('Renders group tabs, persists across reloads, and supports field groups.', () => {
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
				win.epDashboard.features[2].settingsSchema.push({
					type: 'field_group',
					key: 'fieldgroupa',
					label: 'Field Group ABC',
					fields: [
						{
							default: '.ep-autosuggest',
							help: 'Input additional selectors where you would like to include autosuggest, separated by a comma. Example: <code>.custom-selector, #custom-id, input[type="text"]</code>',
							key: 'autosuggest_selector',
							label: 'Additional selectors',
							type: 'text',
						},
						{
							default: '0',
							key: 'trigger_ga_event',
							help: 'Enable to fire a gtag tracking event when an autosuggest result is clicked.',
							label: 'Trigger Google Analytics events',
							type: 'checkbox',
						},
					],
				});
			});

		/**
		 * Navigate to the correct feature group and subfeature
		 */
		cy.contains('button', 'WooCommerce').click();
		cy.contains('button', 'Live Search').click();
		cy.contains('button', 'Autosuggest').click();

		cy.contains('.ep-field-group', 'Field Group ABC').as('fieldGroup');
		cy.get('@fieldGroup').should('exist');
		cy.get('@fieldGroup').find('.ep-dashboard-control').should('exist');
	});
});
