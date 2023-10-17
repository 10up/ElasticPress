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

		cy.contains('.components-button', 'Log').click();
		cy.get('.ep-sync-messages', { timeout: Cypress.config('elasticPressIndexTimeout') })
			.should('contain.text', 'Mapping sent')
			.should('contain.text', 'Sync complete');

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

		cy.wpCli('elasticpress sync --setup --yes');

		cy.publishPost({
			title: 'Test ElasticPress Draft',
			status: 'draft',
		});

		cy.visitAdminPage('edit.php?post_status=draft&post_type=post');
		cy.getTotal(1);
	});

	it('Can sync autosaved drafts', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');

		// Delete previous drafts, so we can be sure we just expect 1 draft post.
		cy.wpCli('post list --post_status=draft --format=ids').then((wpCliResponse) => {
			if (wpCliResponse.stdout !== '') {
				cy.wpCli(`post delete ${wpCliResponse.stdout}`);
			}
		});

		cy.wpCli('elasticpress sync --setup --yes');

		cy.createAutosavePost();

		cy.visitAdminPage('edit.php?post_status=draft&post_type=post');
		cy.getTotal(1);
	});

	it('Can search password protected post', () => {
		cy.login();
		cy.maybeEnableFeature('protected_content');

		cy.publishPost({
			title: 'Password Protected',
			password: 'password',
		});

		// Admin can see post on front and search page.
		cy.visit('/');
		cy.contains('.site-content article h2', 'Password Protected').should('exist');
		cy.visit('/?s=Password+Protected');
		cy.contains('.site-content article h2', 'Password Protected').should('exist');

		cy.logout();

		// Logout user can see the post on front but not on search page.
		cy.visit('/');
		cy.contains('.site-content article h2', 'Password Protected').should('exist');
		cy.visit('/?s=Password+Protected');
		cy.contains('.site-content article h2', 'Password Protected').should('not.exist');

		cy.createUser({ login: true });

		// subscriber can see the post on front and on search page.
		cy.visit('/');
		cy.contains('.site-content article h2', 'Password Protected').should('exist');
		cy.visit('/?s=Password+Protected');
		cy.contains('.site-content article h2', 'Password Protected').should('exist');
	});
});
