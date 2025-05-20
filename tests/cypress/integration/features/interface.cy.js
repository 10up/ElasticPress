/**
 * Test suite for the feature selection interface in ElasticPress settings.
 *
 * @module FeatureInterface
 */
describe('Feature Grouping and Persistence', () => {
	it('Supports field dependency', () => {
		cy.login();

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
						test_field: '0',
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
