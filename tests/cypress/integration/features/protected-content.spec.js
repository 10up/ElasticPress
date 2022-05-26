describe('Protected Content Feature', () => {
	it('Can turn the feature on', () => {
		cy.login();

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-protected_content .settings-button').click();
		cy.get('.ep-feature-protected_content [name="settings[active]"][value="1"]').click();
		cy.get('.ep-feature-protected_content .button-primary').click();
		cy.on('window:confirm', () => {
			return true;
		});

		cy.get('.ep-sync-progress strong', {
			timeout: Cypress.config('elasticPressIndexTimeout'),
		}).should('contain.text', 'Sync complete');

		cy.wpCli('elasticpress list-features').its('stdout').should('contain', 'protected_content');
	});

	it('Can use Elasticsearch in the Posts List Admin Screen', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');

		cy.visitAdminPage('edit.php');
		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress').should('contain.text', 'Time Taken');
	});

	it('Can use Elasticsearch in the Draft Posts List Admin Screen', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');

		// Delete previous drafts, so we can be sure we just expect 1 draft post.
		cy.wpCli('post list --post_status=draft --format=ids').then((wpCliResponse) => {
			if (wpCliResponse.stdout !== '') {
				cy.wpCli(`post delete ${wpCliResponse.stdout}`);
			}
		});

		cy.wpCli('elasticpress index --setup --yes');

		cy.publishPost({
			title: 'Test ElasticPress Draft',
			status: 'draft',
		});

		cy.visitAdminPage('edit.php?post_status=draft&post_type=post');
		cy.getTotal(1);
	});
});
