describe('WordPress can perform standard ElasticPress actions', () => {
	it('Can see the settings page link in WordPress Dashboard', () => {
		cy.login();

		cy.activatePlugin('elasticpress', 'dashboard', true);

		cy.get('.toplevel_page_elasticpress .wp-menu-name').should('contain.text', 'ElasticPress');
	});
});
