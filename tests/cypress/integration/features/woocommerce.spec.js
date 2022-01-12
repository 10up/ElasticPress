describe('WooCommerce Feature', () => {
	it('Can auto-activate the feature', () => {
		cy.login();

		cy.activatePlugin('woocommerce');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-woocommerce').should('have.class', 'feature-active');
	});

	it('Can automatically start a sync if activate the feature', () => {
		cy.login();

		cy.wpCli(`elasticpress deactivate-feature woocommerce`);

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-woocommerce .settings-button').click();
		cy.get('#feature_active_woocommerce_enabled').click();
		cy.get('a.save-settings[data-feature="woocommerce"]').click();
		cy.on('window:confirm', () => {
			return true;
		});

		cy.get('.sync-status').should('contain.text', 'Sync complete');

		// eslint-disable-next-line jest/valid-expect-in-promise
		cy.wpCli('elasticpress list-features').then((wpCliResponse) => {
			expect(wpCliResponse.stdout).to.contains('woocommerce');
		});
	});
});
