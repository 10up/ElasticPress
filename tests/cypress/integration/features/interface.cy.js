/**
 * Test suite for verifying conditional feature visibility
 */
describe('Field Grouping Tests', () => {
	/**
	 * Test case to verify a conditional feature is hidden until its requirement is met
	 *
	 * This test verifies the visibility behavior of a conditional feature
	 * dependent on a specific requirement selection.
	 *
	 * Testing steps:
	 * 1. Navigate to the Live Search section
	 * 2. Navigate to the Autosuggest feature
	 * 3. Verify the group exists
	 * 4. Verify the fields inside the group exist
	 */
	it('should hide conditional feature until specific requirement is selected', () => {
		/**
		 * Log in to WordPress admin.
		 */
		cy.login();

		/**
		 * Navigate to the ElasticPress dashboard page
		 */
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
