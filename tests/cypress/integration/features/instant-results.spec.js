describe('Instant Results Feature', () => {
	/**
	 * Create a Search widget.
	 */
	function createSearchWidget() {
		cy.openWidgetsPage();

		cy.get('.edit-widgets-header-toolbar__inserter-toggle').click();
		cy.get('.block-editor-inserter__panel-content [class*="search/default"]').click({
			force: true,
		});

		cy.get('.edit-widgets-header__actions .components-button.is-primary').click();
		cy.get('body').should('contain.text', 'Widgets saved.');
	}

	before(() => {
		cy.wpCli('eval "echo ElasticPress\\Utils\\get_host();"').then((epHost) => {
			// Nothing needs to be done if EP.io.
			if (epHost.stdout.match(/elasticpress\.io/)) {
				return;
			}
			cy.activatePlugin('elasticpress-proxy', 'dashboard');
		});
		// Add search widget that will be used for the tests.
		createSearchWidget();
	});

	after(() => {
		cy.deactivatePlugin('elasticpress-proxy', 'dashboard');
	});

	it("Can't activate the feature If not in ElasticPress.io nor using a custom PHP proxy", () => {
		cy.login();

		cy.wpCli('eval "echo ElasticPress\\Utils\\get_host();"').then((epHost) => {
			// Nothing needs to be done if EP.io.
			if (epHost.stdout.match(/elasticpress\.io/)) {
				return;
			}
			cy.deactivatePlugin('elasticpress-proxy', 'dashboard');
			cy.visitAdminPage('admin.php?page=elasticpress');
			cy.get('.ep-feature-instant-results .settings-button').click();
			cy.get('.requirements-status-notice').should(
				'contain.text',
				'To use this feature you need to be an ElasticPress.io customer or implement a custom proxy',
			);
			cy.get('.ep-feature-instant-results .input-wrap').should('have.class', 'disabled');
			cy.activatePlugin('elasticpress-proxy', 'dashboard');
		});
	});

	it('Can see a warning if using cusotom proxy', () => {
		cy.login();
		cy.wpCli('eval "echo ElasticPress\\Utils\\get_host();"').then((epHost) => {
			// Nothing needs to be done if EP.io.
			if (epHost.stdout.match(/elasticpress\.io/)) {
				return;
			}
			cy.visitAdminPage('admin.php?page=elasticpress');
			cy.get('.ep-feature-instant-results .settings-button').click();
			cy.get('.requirements-status-notice').should(
				'contain.text',
				'You are using a custom proxy. Make sure you implement all security measures needed',
			);
			cy.get('.ep-feature-instant-results .input-wrap').should('not.have.class', 'disabled');
		});
	});

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

	it('Can see instant results list', () => {
		cy.login();
		cy.maybeEnableFeature('instant-results');

		cy.visit('/');
		cy.get('.wp-block-search__input').type('blog');
		cy.get('.wp-block-search__button')
			.click()
			.then(() => {
				cy.get('.ep-search-modal').should('be.visible').should('contain.text', 'blog');
			});
	});

	it('Can display the number of results', () => {
		cy.login();
		cy.maybeEnableFeature('instant-results');

		cy.visit('/');
		cy.get('.wp-block-search__input').type('blog');
		cy.get('.wp-block-search__button')
			.click()
			.then(() => {
				cy.get('.ep-search-modal').should('be.visible').should('contain.text', 'blog');
				cy.get('.ep-search-results__title').contains(/\d+/);
			});
	});

	it('Can show the modal in the same state after a reload', () => {
		cy.login();
		cy.maybeEnableFeature('instant-results');

		cy.visit('/');
		cy.get('.wp-block-search__input').type('blog');
		cy.get('.wp-block-search__button')
			.click()
			.then(() => {
				cy.get('.ep-search-modal').should('be.visible').should('contain.text', 'blog');
			});
		cy.reload();
		cy.get('.ep-search-modal').should('be.visible').should('contain.text', 'blog');
	});

	it('Can update the results after changing the search term', () => {
		cy.login();
		cy.maybeEnableFeature('instant-results');

		cy.visit('/');
		cy.get('.wp-block-search__input').type('blog');
		cy.get('.wp-block-search__button')
			.click()
			.then(() => {
				cy.get('.ep-search-modal').should('be.visible').should('contain.text', 'blog');
			});
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
		cy.get('ep-search-sidebar #ep-search-post-type-post')
			.click()
			.then(() => {
				cy.url().should('include', 'ep-post_type=post');
			});
	});

	it('Can show post type label alongside taxonomies', () => {
		cy.login();
		cy.maybeEnableFeature('instant-results');
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-instant-results .settings-button').click();
		cy.get('.ep-feature-instant-results .components-form-token-field__input')
			.type('category')
			.first()
			.click();
		cy.get('.ep-feature-instant-results .components-form-token-field__input')
			.type('category')
			.first()
			.click()
			.then(() => {
				cy.get('.ep-feature-instant-results .button-primary').click();
			});

		cy.visit('/');
		cy.get('.wp-block-search__input').type('test');
		cy.get('.wp-block-search__button')
			.click()
			.then(() => {
				cy.get('.ep-search-modal ep-search-sidebar')
					.last()
					.should('contain.text', 'Category (Products)');
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
