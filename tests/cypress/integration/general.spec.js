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

	it('Can see a warning in the dashboard if using other software than Elasticsearch.', () => {
		cy.login();

		cy.wpCli('eval "echo ElasticPress\\Utils\\get_host();"').then((epHost) => {
			// Nothing needs to be done if EP.io.
			if (epHost.stdout.match(/elasticpress\.io/)) {
				return;
			}

			cy.deactivatePlugin('elasticpress', 'wpCli');
			cy.activatePlugin('unsupported-server-software elasticpress', 'wpCli');

			cy.visitAdminPage('plugins.php');
			cy.get('.notice')
				.invoke('text')
				.then((text) => {
					expect(text).to.contains('Your server software is not supported.');
				});

			cy.deactivatePlugin('unsupported-server-software', 'wpCli');
		});
	});

	it('Can see a Sync and Settings buttons on Settings Page', () => {
		cy.visitAdminPage('admin.php?page=elasticpress-settings');
		cy.get('.dashicons.start-sync').should('have.attr', 'title', 'Sync Page');
		cy.get('.dashicons.dashicons-admin-generic').should('have.attr', 'title', 'Settings Page');
	});

	it('Cannot save settings while a sync is in progress', () => {
		cy.login();
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.wpCliEval(`update_option( 'ep_index_meta', true );`).then(() => {
			cy.get('.ep-feature-search .settings-button').click();
			cy.get('.ep-feature-search .button-primary').click();
			cy.get('.ep-feature-search .requirements-status-notice--syncing').should('be.visible');
			cy.wpCliEval(`delete_option( 'ep_index_meta' );`);
		});
	});

	it('Can see ElasticPress Last Sync Accordion', () => {
		cy.login();
		cy.visitAdminPage('site-health.php?tab=debug');
		cy.get('[aria-controls="health-check-accordion-block-ep_last_sync"]').click();
		cy.get(
			'#health-check-accordion-block-ep_last_sync table.widefat.striped.health-check-table tbody tr:nth-child(1) td',
		).should('contain.text', 'Total');
		cy.get(
			'#health-check-accordion-block-ep_last_sync table.widefat.striped.health-check-table tbody tr:nth-child(2) td',
		).should('contain.text', 'Synced');

		cy.get(
			'#health-check-accordion-block-ep_last_sync table.widefat.striped.health-check-table tbody tr:nth-child(3) td',
		).should('contain.text', 'Skipped');

		cy.get(
			'#health-check-accordion-block-ep_last_sync table.widefat.striped.health-check-table tbody tr:nth-child(4) td',
		).should('contain.text', 'Failed');

		cy.get(
			'#health-check-accordion-block-ep_last_sync table.widefat.striped.health-check-table tbody tr:nth-child(5) td',
		).should('contain.text', 'Total time');

		cy.get(
			'#health-check-accordion-block-ep_last_sync table.widefat.striped.health-check-table tbody tr:nth-child(6) td',
		).should('contain.text', 'Errors');

		cy.get(
			'#health-check-accordion-block-ep_last_sync table.widefat.striped.health-check-table tbody tr:nth-child(7) td',
		).should('contain.text', 'End date time');

		cy.get(
			'#health-check-accordion-block-ep_last_sync table.widefat.striped.health-check-table tbody tr:nth-child(9) td',
		).should('contain.text', 'Method');
	});
});
