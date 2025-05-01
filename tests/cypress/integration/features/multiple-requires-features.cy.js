/**
 * Test suite for verifying conditional feature visibility
 */
describe('Multiple Requires Features Support', () => {
	/**
	 * Checks to confirm that the did you mean feature requires the core search feature.
	 * @todo Switch this test to a feature with multiple feature requirements, when one is available.
	 */
	it('should have two missing features', () => {
		/**
		 * Log in to WordPress admin.
		 */
		cy.login();

		/**
		 * Navigate to the ElasticPress dashboard page
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress');

		cy.contains('button', 'Core Search').click();
		cy.contains('button', 'Post Search').click();

		// Disable search featuer
		cy.get('.components-form-toggle__input').click();

		cy.contains('button', 'Did You Mean').click();
		cy.contains(
			'.components-notice.is-error',
			'The Post Search feature must be enabled to use this feature.',
		).should('be.visible');
	});
});
