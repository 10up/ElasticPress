describe('Can see highlighted text', () => {
	before(() => {
		cy.wpCli('elasticpress index --setup --yes');
	});

	it('Can see highlighted text', () => {
		cy.login();
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-search .settings-button').click();
		cy.get('#highlighting_enabled').click();
		cy.get('a.save-settings[data-feature="search"]').click();

		cy.publishPost({
			title: 'test highlight color',
			content: 'findme findme findme',
		});

		cy.visit('/?s=findme');

		cy.get('.ep-highlight').should('be.visible');
	});
});
