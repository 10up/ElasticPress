describe('Instant Results Feature', () => {
	/**
	 * Check if it's Elasticpress.io.
	 */
	function isEpIo() {
		cy.wpCli('eval "echo ElasticPress\\Utils\\get_host();"').then((epHost) => {
			// Nothing needs to be done if EP.io.
			if (epHost.stdout.match(/elasticpress\.io/)) {
				return true;
			}
			return false;
		});
	}

	before(() => {
		if (!isEpIo()) {
			cy.activatePlugin('elasticpress-proxy', 'dashboard');
		}
		// Create a sample post and index
		cy.publishPost({
			title: 'Blog post',
			content: 'This is a sample Blog post.',
		});
		cy.publishPost({
			title: 'Test Post',
			content: 'This is a sample test post.',
		});
		cy.wpCli('elasticpress index --setup --yes');
	});

	after(() => {
		cy.deactivatePlugin('elasticpress-proxy', 'dashboard');
	});

	/**
	 * Test that the feature cannot be activted when not in ElasticPress.io nor using a custom PHP proxy.
	 * Also, it can show a warning when using a custom PHP proxy
	 */
	it("Can't activate the feature If not in ElasticPress.io nor using a custom PHP proxy and can see a warning if using cusotom proxy", () => {
		cy.login();
		if (!isEpIo()) {
			cy.deactivatePlugin('elasticpress-proxy', 'dashboard');
			cy.visitAdminPage('admin.php?page=elasticpress');
			cy.get('.ep-feature-instant-results .settings-button').click();
			cy.get('.requirements-status-notice').should(
				'contain.text',
				'To use this feature you need to be an ElasticPress.io customer or implement a custom proxy',
			);
			cy.get('.ep-feature-instant-results .input-wrap').should('have.class', 'disabled');
			// Can see the warning if using custom proxy
			cy.activatePlugin('elasticpress-proxy', 'dashboard');
			cy.visitAdminPage('admin.php?page=elasticpress');
			cy.get('.ep-feature-instant-results .settings-button').click();
			cy.get('.requirements-status-notice').should(
				'contain.text',
				'You are using a custom proxy. Make sure you implement all security measures needed',
			);
			cy.get('.ep-feature-instant-results .input-wrap').should('not.have.class', 'disabled');
		}
	});

	/**
	 * Test that the feature can be activted and it can sync automatically.
	 */
	it('Can activate the feature and sync automatically', () => {
		cy.login();

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-instant-results .settings-button').click();
		cy.get('.ep-feature-instant-results [name="settings[active]"][value="1"]').click();
		cy.get('.ep-feature-instant-results .button-primary').click();
		cy.on('window:confirm', () => {
			return true;
		});

		cy.get('.ep-sync-progress strong', {
			timeout: Cypress.config('elasticPressIndexTimeout'),
		}).should('contain.text', 'Sync complete');

		cy.wpCli('elasticpress list-features').its('stdout').should('contain', 'instant-results');
	});

	/**
	 * Test that the instant results list is visible
	 * It can display the number of test results
	 * It can show the modal in the same state after a reload
	 * Can change the URL when search term is changed
	 */
	it('Can see instant results list, number of results, modal in the same state after reload, and updated result after changing the search term', () => {
		cy.login();
		cy.maybeEnableFeature('instant-results');

		cy.visit('/');
		cy.get('.wp-block-search__input').type('blog');
		cy.get('.wp-block-search__button')
			.click()
			.then(() => {
				cy.get('.ep-search-modal').should('be.visible').should('contain.text', 'blog');
				// Show the number of results
				cy.get('.ep-search-results__title').contains(/\d+/);
			});
		// Show the modal in the same state after a reload
		cy.reload();
		cy.get('.ep-search-modal').should('be.visible').should('contain.text', 'blog');
		// Update the results when search term is changed
		cy.get('#ep-instant-results .ep-search-input')
			.clearThenType('test')
			.then(() => {
				cy.get('.ep-search-modal').should('be.visible').should('contain.text', 'test');
			});
	});

	it('Can update the URL after changing the filters', () => {
		cy.login();
		cy.maybeEnableFeature('instant-results');

		cy.visit('/');
		cy.get('.wp-block-search__input').type('test');
		cy.get('.wp-block-search__button')
			.click()
			.then(() => {
				cy.get('.ep-search-modal').should('be.visible').should('contain.text', 'test');
			});
		cy.get('.ep-search-sidebar #ep-search-post-type-post')
			.click()
			.then(() => {
				cy.url().should('include', 'ep-post_type=post');
			});
	});

	it('Can click outside when instant results are shown', () => {
		cy.login();
		cy.maybeEnableFeature('instant-results');

		cy.visit('/');
		cy.get('.wp-block-search__input').type('blog');
		cy.get('.wp-block-search__button').click();
		cy.get('.ep-search-modal').should('be.visible');

		cy.get('#wpadminbar li#wp-admin-bar-debug-bar').click();
		cy.get('#querylist').should('be.visible');
	});
});
