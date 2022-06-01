describe('Settings Page', () => {
	it('Can see a Sync and Settings buttons on Settings Page', () => {
		cy.visitAdminPage('admin.php?page=elasticpress-settings');
		cy.get('.dashicons.start-sync').should('have.attr', 'title', 'Sync Page');
		cy.get('.dashicons.dashicons-admin-generic').should('have.attr', 'title', 'Settings Page');
	});
});
