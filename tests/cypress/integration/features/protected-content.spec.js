describe('Protected Content Feature', () => {
	it('Can turn the feature on', () => {
		cy.login();

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-protected_content .settings-button').click();
		cy.get('#feature_active_protected_content_enabled').click();
		cy.get('a.save-settings[data-feature="protected_content"]').click();
		cy.on('window:confirm', () => {
			return true;
		});

		cy.get('.sync-status', { timeout: 60000 }).should('contain.text', 'Sync complete');

		// eslint-disable-next-line jest/valid-expect-in-promise
		cy.wpCli('elasticpress list-features').then((wpCliResponse) => {
			expect(wpCliResponse.stdout).to.contains('protected_content');
		});
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
