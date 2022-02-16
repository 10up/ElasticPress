/* global indexNames */

describe('Dashboard Sync', () => {
	function setPerIndexCycle(number) {
		let oldValue;
		cy.visitAdminPage('admin.php?page=elasticpress-settings');

		cy.get('#ep_bulk_setting').then(($input) => {
			oldValue = $input.val();
			$input.val(number);
		});
		cy.get('#submit').click();

		return oldValue;
	}

	function canSeeIndexesNames() {
		cy.visitAdminPage('admin.php?page=elasticpress-health');
		cy.get('.metabox-holder')
			.invoke('text')
			.then((text) => {
				indexNames.forEach((index) => {
					expect(text).to.contains(index);
				});
			});
	}

	after(() => {
		if (cy.state('test').state === 'failed') {
			cy.wpCli('wp plugin deactivate elasticpress --network', true);
			cy.wpCli('wp plugin activate elasticpress', true);
			cy.wpCli('wp elasticpress clear-index', true);
			cy.visitAdminPage('admin.php?page=elasticpress-settings');
			cy.get('body').then(($body) => {
				const $cancelSyncButton = $body.find('.cancel-sync');
				if ($cancelSyncButton.length) {
					$cancelSyncButton.click();
				}
			});
		}
	});

	it('Can index content and see indexes names in the Health Screen', () => {
		cy.login();

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.start-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);

		canSeeIndexesNames();
	});

	it('Can sync via Dashboard when activated in single site', () => {
		cy.login();

		cy.wpCli('wp elasticpress delete-index --yes');

		cy.visitAdminPage('admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.start-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);

		cy.visitAdminPage('admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'not.contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		canSeeIndexesNames();
	});

	it('Can sync via Dashboard when activated in multisite', () => {
		cy.login();

		cy.wpCli('wp elasticpress delete-index --yes');

		/**
		 * @todo Investigate why these were failing if through wp-cli.
		 */
		cy.deactivatePlugin('elasticpress');
		cy.activatePlugin('elasticpress', 'dashboard', 'network');

		// Sync and remove, so EP doesn't think it is a fresh install.
		cy.wpCli('wp elasticpress index --setup --yes');
		cy.wpCli('wp elasticpress delete-index --yes');

		cy.visitAdminPage('network/admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		cy.visitAdminPage('network/admin.php?page=elasticpress');
		cy.get('.start-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);

		cy.visitAdminPage('network/admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'not.contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		cy.wpCli('elasticpress get-indexes').then((wpCliResponse) => {
			const indexes = JSON.parse(wpCliResponse.stdout);
			cy.visitAdminPage('network/admin.php?page=elasticpress-health');
			cy.get('.metabox-holder')
				.invoke('text')
				.then((text) => {
					indexes.forEach((index) => {
						expect(text).to.contains(index);
					});
				});
		});

		cy.deactivatePlugin('elasticpress', 'dashboard', 'network');
		cy.activatePlugin('elasticpress');

		cy.wpCli('wp elasticpress index --setup --yes');
	});

	it('Can pause the dashboard sync if left the page', () => {
		cy.login();

		const oldPostsPerCycle = setPerIndexCycle(10);

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.start-sync').click();
		cy.get('.pause-sync').should('be.visible');

		cy.visitAdminPage('index.php');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.sync-status').should('contain.text', 'Sync paused');

		cy.get('.resume-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);

		setPerIndexCycle(oldPostsPerCycle);

		canSeeIndexesNames();
	});

	it("Can't activate features during a sync", () => {
		cy.login();

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.start-sync').click();
		cy.get('.pause-sync').should('be.visible');
		cy.get('.pause-sync').click();

		cy.get('.error-overlay').should('have.class', 'syncing');

		cy.get('.resume-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);
		cy.get('.error-overlay').should('not.have.class', 'syncing');
	});

	it("Can't index via WP-CLI if indexing via Dashboard", () => {
		cy.login();

		const oldPostsPerCycle = setPerIndexCycle(10);

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.start-sync').click();
		cy.get('.pause-sync').should('be.visible');
		cy.get('.pause-sync').click();

		cy.wpCli('wp elasticpress index', true)
			.its('stderr')
			.should('contain', 'An index is already occurring');

		cy.get('.resume-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);

		setPerIndexCycle(oldPostsPerCycle);
	});
});
