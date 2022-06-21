describe('Instant Results Feature', () => {
	before(() => {
		cy.deactivatePlugin('elasticpress-proxy', 'wpCli');
	});

	after(() => {
		cy.deactivatePlugin('elasticpress-proxy', 'wpCli');
	});

	it('Can activate the feature and sync automatically', () => {
		cy.login();

		cy.activatePlugin('elasticpress-proxy');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-instant-results .settings-button').click();
		cy.get('.ep-feature-instant-results [name="settings[active]"][value="1"]').click();
		cy.get('.ep-feature-instant-results .button-primary').click();
		cy.on('window:confirm', () => {
			return true;
		});

		cy.get('.ep-sync-progress strong', {
			timeout: Cypress.config('elasticPressIndexTimeout'),
		}).should('contain.text', 'Sync complete');

		cy.wpCli('elasticpress list-features').its('stdout').should('contain', 'instant-results');
	});

	it('Can see instant results list', () => {
		cy.login();
		cy.maybeEnableFeature('instant-results');

		cy.visit('/');
		cy.get('.wp-block-search__input').type('hello');
		cy.get('.wp-block-search__button').click();
		cy.get('.ep-search-modal').should('be.visible').should('contain.text', 'Search results');
	});

	it('Can click outside when instant results are shown', () => {
		cy.login();
		cy.maybeEnableFeature('instant-results');

		cy.visit('/');
		cy.get('.wp-block-search__input').type('hello');
		cy.get('.wp-block-search__button').click();
		cy.get('.ep-search-modal').should('be.visible');

		cy.get('#wpadminbar li#wp-admin-bar-debug-bar').click();
		cy.get('#querylist').should('be.visible');
	});
});
