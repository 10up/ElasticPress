describe('Feature Selection Persistence', () => {
	/**
	 * Verifies that the group and feature tabs are clickable and persist their selections.
	 */
	it('allows selecting groups and features, and persists selections on reload', () => {
		/**
		 * Log in to WordPress admin.
		 */
		cy.login();

		/**
		 * Navigate to the ElasticPress settings page.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress');

		// First select a group (for example, the second group)
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
	});
});
