/**
 * Test suite for verifying conditional feature visibility
 */
describe('Feature Visibility Tests', () => {
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

		/**
		 * Navigate to the correct feature group and subfeature
		 */
		cy.contains('button', 'Live Search').click();
		cy.contains('button', 'Autosuggest').click();

		cy.contains('.ep-field-group', 'Field Group A').as('fieldGroup');
		cy.get('@fieldGroup').should('exist');
		cy.get('@fieldGroup').find('.ep-dashboard-control').should('exist');
	});
});
