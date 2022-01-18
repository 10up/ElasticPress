describe('Dashboard Sync', () => {
	it('Can index content and see indexes names in the Health Screen', () => {
		cy.login();

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.start-sync').click();
		cy.get('.sync-status').should('contain.text', 'Sync complete');

		cy.wpCli('elasticpress get-indexes').then((wpCliResponse) => {
			const indexes = JSON.parse(wpCliResponse.stdout);
			cy.visitAdminPage('admin.php?page=elasticpress-health');
			cy.get('.metabox-holder')
				.invoke('text')
				.then((text) => {
					indexes.forEach((index) => {
						expect(text).to.contains(index);
					});
				});
		});
	});
});
