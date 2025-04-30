/**
 * Test suite for verifying conditional feature visibility
 */
describe('Multiple Requires Features Support', () => {
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
	it('should have two missing features', () => {
		/**
		 * Log in to WordPress admin.
		 */
		cy.login();

		/**
		 * Navigate to the ElasticPress dashboard page
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress');

		// Disable AutoSuggest
		cy.contains('button', 'Live Search').click();
		cy.contains('button', 'Autosuggest').click();
		cy.get('[id*="autosuggest-view"]').find('.components-form-toggle').as('formToggle');
		cy.get('@formToggle').then(($toggle) => {
			if ($toggle.hasClass('is-checked')) {
				cy.wrap($toggle).find('input').click();
			}
		});

		// Disable Protected Content
		cy.contains('button', 'Indexing Options').click();
		cy.contains('button', 'Protected Content').click();
		cy.get('[id*="protected_content-view"]').find('.components-form-toggle').as('formToggle');
		cy.get('@formToggle').then(($toggle) => {
			if ($toggle.hasClass('is-checked')) {
				cy.wrap($toggle).find('input').click();
			}
		});

		// Confirm Documents can't be enabled
		cy.contains('button', 'Indexing Options').click();
		cy.contains('button', 'Documents').click();

		cy.contains(
			'.components-notice',
			'Autosuggest, and Protected Content feature must be enabled to use this feature.',
		).should('exist');
	});
});
