/* global isEpIo */

// eslint-disable-next-line jest/valid-describe-callback
describe('Instant Results Feature', { tags: '@slow' }, () => {
	/**
	 * Create a Search widget.
	 *
	 * As tests for facets will remove all widgets, we recreate it here.
	 */
	function createSearchWidget() {
		cy.openWidgetsPage();
		cy.openBlockInserter();
		cy.getBlocksList().should('contain.text', 'Search'); // Checking if it exists give JS time to process the full list.
		cy.insertBlock('Search');
		cy.intercept('/wp-json/wp/v2/sidebars/*').as('sidebarsRest');
		cy.get('.edit-widgets-header__actions button').contains('Update').click();
		cy.wait('@sidebarsRest');
	}

	before(() => {
		cy.deactivatePlugin('woocommerce', 'wpCli');
		cy.deactivatePlugin('classic-widgets', 'wpCli');
		createSearchWidget();

		// Create some sample posts
		cy.publishPost({
			title: 'Blog post',
			content: 'This is a sample Blog post.',
		});
		cy.publishPost({
			title: 'Test Post',
			content: 'This is a sample test post.',
		});
	});

	beforeEach(() => {
		cy.deactivatePlugin(
			'custom-instant-results-template open-instant-results-with-buttons filter-instant-results-per-page filter-instant-results-args-schema cpt-and-custom-tax',
			'wpCli',
		);
		cy.login();
	});

	/**
	 * Test that the feature cannot be activated when not in ElasticPress.io nor using a custom PHP proxy.
	 */
	it("Can't activate the feature if not in ElasticPress.io nor using a custom PHP proxy", () => {
		if (isEpIo) {
			return;
		}

		// Make sure the proxy is deactivated.
		cy.deactivatePlugin('elasticpress-proxy');

		cy.visitAdminPage('admin.php?page=elasticpress');

		cy.contains('button', 'Instant Results').click();
		cy.contains('.components-notice', 'To use this feature you need').should('exist');
		cy.get('.components-form-toggle__input').should('be.disabled');
	});

	describe('Instant Results Available', () => {
		before(() => {
			cy.activatePlugin('woocommerce');

			if (!isEpIo) {
				cy.activatePlugin('elasticpress-proxy');
			}
		});

		/**
		 * Test that the feature can be activated and it can sync automatically.
		 * Also, it can show a warning when using a custom PHP proxy
		 */
		it('Can activate the feature and sync automatically', () => {
			// Can see the warning if using custom proxy
			cy.visitAdminPage('admin.php?page=elasticpress');
			cy.intercept('/wp-json/elasticpress/v1/features*').as('apiRequest');

			cy.contains('button', 'Instant Results').click();

			const noticeShould = isEpIo ? 'not.contain.text' : 'contain.text';

			cy.get('.components-form-toggle__input').should('not.be.disabled');
			cy.get('.components-notice').should(noticeShould, 'You are using a custom proxy.');

			cy.contains('label', 'Enable').click();
			cy.contains('button', 'Save and sync now').click();

			cy.wait('@apiRequest');

			cy.on('window:confirm', () => true);

			cy.get('.ep-sync-progress strong', {
				timeout: Cypress.config('elasticPressIndexTimeout'),
			}).should('contain.text', 'Sync complete');

			cy.wpCli('elasticpress list-features')
				.its('stdout')
				.should('contain', 'instant-results');
		});

		describe('Instant Results activated', () => {
			before(() => {
				cy.wpCli('wp elasticpress put-search-template', true);
			});

			/**
			 * Test that the instant results list is visible
			 * It can display the number of test results
			 * It can show the modal in the same state after a reload
			 * Can change the URL when search term is changed
			 */
			it('Can see instant results elements, URL changes, reload, and update after changing search term', () => {
				cy.activatePlugin('cpt-and-custom-tax', 'wpCli');
				cy.maybeEnableFeature('instant-results');

				cy.intercept({
					url: '*search=blog*',
					headers: {
						'X-ElasticPress-Request-ID': /[0-9a-f]{32}$/,
					},
				}).as('apiRequest');

				/**
				 * Add product category facet to test the labelling of facets
				 * with the same name.
				 */
				cy.visitAdminPage('admin.php?page=elasticpress');
				cy.intercept('/wp-json/elasticpress/v1/features*').as('apiRequest');

				cy.contains('button', 'Instant Results').click();
				cy.get('.components-form-token-field__input').type('prod{downArrow}{enter}{esc}');
				cy.contains('button', 'Save changes').click();

				cy.wait('@apiRequest');

				/**
				 * Perform a search.
				 */
				cy.visit('/');

				cy.get('.wp-block-search').last().as('searchBlock');

				cy.get('@searchBlock').find('.wp-block-search__input').type('blog');
				cy.get('@searchBlock').find('.wp-block-search__button').click();
				cy.get('.ep-search-modal').as('searchModal').should('be.visible'); // Should be visible immediatly
				cy.get('@searchModal')
					.find('.ep-search-results__title')
					.contains('Loading results');
				cy.url().should('include', 'search=blog');
				cy.url().should('include', 'search=blog');

				cy.wait('@apiRequest');

				cy.get('@searchModal').should('contain.text', 'blog');
				// Show the number of results
				cy.get('@searchModal').find('.ep-search-results__title').contains(/\d+/);

				/**
				 * The Category facet should specify its searchable post types.
				 */
				cy.get('.ep-search-panel__button').contains('Category').as('categoryFacet');
				cy.get('@categoryFacet').should('contain', 'Posts');
				cy.get('@categoryFacet').should('contain', 'Movies');
				cy.get('@categoryFacet').should('not.contain', 'Albums');

				cy.get('.ep-search-sidebar #ep-search-post-type-post')
					.click()
					.then(() => {
						cy.url().should('include', 'ep-post_type=post');
					});

				// Show the modal in the same state after a reload
				cy.reload();
				cy.get('@searchModal')
					.find('.ep-search-results__title')
					.contains('Loading results');
				cy.url().should('include', 'search=blog');
				cy.wait('@apiRequest');
				cy.get('@searchModal').should('be.visible').should('contain.text', 'blog');

				// Update the results when search term is changed
				cy.get('@searchModal')
					.find('.ep-search-input')
					.clearThenType('test')
					.then(() => {
						cy.wait('@apiRequest');
						cy.get('@searchModal').should('be.visible').should('contain.text', 'test');
						cy.url().should('include', 'search=test');
					});

				cy.get('#wpadminbar li#wp-admin-bar-debug-bar').click();
				cy.get('#querylist').should('be.visible');
			});

			it('Is possible to filter the number of results', () => {
				/**
				 * The number of results should match the posts per page
				 * setting by default.
				 */
				cy.maybeEnableFeature('instant-results');
				cy.visitAdminPage('options-reading.php');
				cy.get('input[name="posts_per_page"]').then(($input) => {
					const perPage = $input.val();

					cy.visit('/');
					cy.get('.wp-block-search').last().as('searchBlock');
					cy.get('@searchBlock').find('input[type="search"]').type('block');
					cy.get('@searchBlock').find('button').click();
					cy.url().should('include', `per_page=${perPage}`);

					/**
					 * Activate test plugin with filter.
					 */
					cy.activatePlugin('filter-instant-results-per-page', 'wpCli');

					/**
					 * On searching the per_page parameter should reflect the
					 * filtered value.
					 */
					cy.visit('/');
					cy.get('.wp-block-search').last().as('searchBlock');
					cy.get('@searchBlock').find('input[type="search"]').type('block');
					cy.get('@searchBlock').find('button').click();
					cy.url().should('include', 'per_page=3');
				});
			});

			it('Can filter results by price', () => {
				/**
				 * Add price range facet.
				 */
				cy.maybeEnableFeature('instant-results');

				cy.visitAdminPage('admin.php?page=elasticpress');
				cy.intercept('/wp-json/elasticpress/v1/features*').as('apiRequest');

				cy.contains('button', 'Instant Results').click();
				cy.get('.components-form-token-field__input').type(
					'{backspace}{backspace}{backspace}price{downArrow}{enter}{esc}',
				);
				cy.contains('button', 'Save changes').click();

				cy.wait('@apiRequest');

				/**
				 * Perform a search.
				 */
				cy.visit('/');
				cy.intercept('*search=ergonomic*').as('apiRequest');
				cy.get('.wp-block-search').last().as('searchBlock');
				cy.get('@searchBlock').find('input[type="search"]').type('ergonomic');
				cy.get('@searchBlock').find('button').click();
				cy.get('.ep-search-modal').should('be.visible');
				cy.wait('@apiRequest');
				cy.get('.ep-search-result').should('have.length', 3);

				/**
				 * Adjusting the price range facet should filter results by price.
				 */
				cy.get('.ep-search-range-slider__thumb-0').as('priceThumb');
				cy.get('@priceThumb').type('{rightArrow}');
				cy.wait('@apiRequest');
				cy.url().should('include', 'min_price=420');
				cy.get('.ep-search-result').should('have.length', 2);

				/**
				 * Clearing the filter should return the unfiltered results.
				 */
				cy.get('.ep-search-tokens button').contains('420').click();
				cy.wait('@apiRequest');
				cy.get('.ep-search-result').should('have.length', 3);
			});

			it('Is possible to manually open Instant Results with a plugin', () => {
				Cypress.on(
					'uncaught:exception',
					(err) => !err.message.includes('ResizeObserver loop limit exceeded'),
				);

				/**
				 * Activate test plugin with JavaScript.
				 */
				cy.maybeEnableFeature('instant-results');
				cy.activatePlugin('open-instant-results-with-buttons', 'wpCli');

				/**
				 * Create a post with a Buttons block.
				 */
				cy.publishPost({
					title: `Test openModal()`,
					content: 'Testing openModal()',
				});

				cy.openBlockInserter();
				cy.insertBlock('Buttons');
				cy.get('.wp-block-button__link').type('Search "Block"');

				/**
				 * Update the post and visit the front end.
				 */
				cy.get('.editor-post-publish-button__button').click();
				cy.get('.components-snackbar__action').click();

				/**
				 * Click the button.
				 */
				cy.intercept('*search=block*').as('apiRequest');
				cy.get('.wp-block-button__link').click();

				/**
				 * Instant Results should be open and populated with our search term.
				 */
				cy.wait('@apiRequest');
				cy.get('.ep-search-modal').as('searchModal').should('be.visible');
				cy.get('@searchModal').find('.ep-search-input').should('have.value', 'block');
				cy.get('@searchModal')
					.find('.ep-search-results__title')
					.should('contain.text', 'block');
			});

			it('Can filter the result template', () => {
				/**
				 * Activate test plugin with filter.
				 */
				cy.maybeEnableFeature('instant-results');
				cy.activatePlugin('custom-instant-results-template', 'wpCli');

				/**
				 * Perform a search.
				 */
				cy.intercept('*search=blog*').as('apiRequest');
				cy.visit('/');
				cy.get('.wp-block-search').last().as('searchBlock');
				cy.get('@searchBlock').find('input[type="search"]').type('blog');
				cy.get('@searchBlock').find('button').click();
				cy.get('.ep-search-modal').should('be.visible');
				cy.wait('@apiRequest');

				/**
				 * Results should use the filtered template with a custom class.
				 */
				cy.get('.my-custom-result').should('exist');
				cy.get('.ep-search-result').should('not.exist');
			});

			it('Can display a suggestion', () => {
				cy.maybeEnableFeature('instant-results');
				cy.maybeEnableFeature('did-you-mean');

				cy.wpCli('wp elasticpress sync --setup --yes');

				/**
				 * Perform a search.
				 */
				cy.intercept('*search=wordpless*').as('apiRequest');
				cy.visit('/');
				cy.get('.wp-block-search').last().as('searchBlock');
				cy.get('@searchBlock').find('input[type="search"]').type('wordpless');
				cy.get('@searchBlock').find('button').click();
				cy.get('.ep-search-modal').should('be.visible');
				cy.wait('@apiRequest');
				cy.get('.ep-search-suggestion a').should('have.text', 'wordpress');
			});
		});

		it('Is possible to filter the arguments schema', () => {
			/**
			 * The number of results should match the posts per page
			 * setting by default.
			 */
			cy.maybeEnableFeature('instant-results');
			cy.intercept('*search=block*').as('apiRequest');

			/**
			 * Activate test plugin.
			 */
			cy.activatePlugin('filter-instant-results-args-schema', 'wpCli');

			/**
			 * Perform a search.
			 */
			cy.visit('/');
			cy.get('.wp-block-search').last().as('searchBlock');
			cy.get('@searchBlock').find('input[type="search"]').type('block');
			cy.get('@searchBlock').find('button').click();
			cy.wait('@apiRequest');

			/**
			 * Results should be sorted by date.
			 */
			cy.get('.ep-search-sort :selected').should('contain.text', 'Date, newest to oldest');
		});
	});
});
