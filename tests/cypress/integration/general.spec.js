describe('WordPress can perform standard ElasticPress actions', () => {
	it('Can see the settings page link in WordPress Dashboard', () => {
		cy.login();

		cy.activatePlugin('elasticpress', 'dashboard');

		cy.get('.toplevel_page_elasticpress .wp-menu-name').should('contain.text', 'ElasticPress');
	});

	it('Can see quick setup message after enabling the plugin for the first time', () => {
		cy.login();

		cy.deactivatePlugin('elasticpress', 'wpCli');
		cy.activatePlugin('fake-new-activation elasticpress', 'wpCli');

		cy.visitAdminPage('/');
		cy.get('.wrap').should('contain.text', 'ElasticPress is almost ready to go.');

		cy.deactivatePlugin('fake-new-activation', 'wpCli');
	});

	it('Can select features if user is setting up plugin for the first time', () => {
		cy.login();

		cy.deactivatePlugin('elasticpress', 'wpCli');
		cy.activatePlugin('fake-new-activation elasticpress', 'wpCli');

		cy.visitAdminPage('admin.php?page=elasticpress');

		cy.get('.setup-button').should('contain.text', 'Save Features');

		cy.deactivatePlugin('fake-new-activation', 'wpCli');
	});

	it('Can sync post data and meta details in Elasticsearch if user creates/updates a published post', () => {
		cy.login();

		cy.publishPost({
			title: 'Test ElasticPress 1',
		});

		cy.visit('/?s=Test+ElasticPress+1');
		cy.contains('.site-content article h2', 'Test ElasticPress 1').should('exist');
	});

	it('Can see a warning in the dashboard if user activates plugin with an Elasticsearch version before or after min/max requirements.', () => {
		cy.login();

		cy.wpCli('eval "echo ElasticPress\\Utils\\get_host();"').then((epHost) => {
			// Nothing needs to be done if EP.io.
			if (epHost.stdout.match(/elasticpress\.io/)) {
				return;
			}

			cy.deactivatePlugin('elasticpress', 'wpCli');
			cy.activatePlugin('unsupported-elasticsearch-version elasticpress', 'wpCli');

			cy.visitAdminPage('plugins.php');
			cy.get('.notice')
				.invoke('text')
				.then((text) => {
					expect(text).to.contains('ElasticPress may or may not work properly.');
				});

			cy.deactivatePlugin('unsupported-elasticsearch-version', 'wpCli');
		});
	});
});
