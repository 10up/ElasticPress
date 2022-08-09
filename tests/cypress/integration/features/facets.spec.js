describe('Facets Feature', () => {
	/**
	 * Create a facets widget.
	 *
	 * @param {string} title The widget title
	 * @param {string} category The category slug.
	 */
	function createWidget(title, category) {
		cy.openWidgetsPage();

		let createdWidgetsLength = 0;

		cy.get('.is-opened').then(($openedWidgetArea) => {
			createdWidgetsLength = $openedWidgetArea.find('.wp-block-legacy-widget').length;

			cy.get('.edit-widgets-header-toolbar__inserter-toggle').click();
			cy.get('.block-editor-inserter__panel-content [class*="legacy-widget/ep-facet"]')
				.click({
					force: true,
				})
				.then(() => {
					cy.get(
						`.is-opened .wp-block-legacy-widget:eq(${createdWidgetsLength}) .wp-block-legacy-widget__edit-form:visible .widefat:visible`,
						{ timeout: 10000 },
					)
						.closest('.widget-ep-facet')
						.within(() => {
							cy.get('input[name^="widget-ep-facet"][name$="[title]"]').clearThenType(
								title,
								true,
							);
							cy.get('select[name^="widget-ep-facet"][name$="[facet]"]').select(
								category,
							);
						});

					/**
					 * Wait for WordPress to recognize the title typed.
					 *
					 * @todo investigate why this is needed.
					 */
					// eslint-disable-next-line cypress/no-unnecessary-waiting
					cy.wait(2000);

					cy.get('.edit-widgets-header__actions .components-button.is-primary').click();
					cy.get('body').should('contain.text', 'Widgets saved.');
				});
		});
	}

	before(() => {
		cy.maybeEnableFeature('facets');

		cy.wpCli('elasticpress index --setup --yes');
	});

	it('Can see the widget in the frontend', () => {
		cy.wpCli('widget reset --all');

		createWidget('Facet (categories)', 'category');

		cy.visit('/');

		// Check if the first widget is visible.
		cy.get('.widget_ep-facet').should('be.visible');
		cy.contains('.widget-title', 'Facet (categories)').should('be.visible');

		// Create a second widget, so we can test both working together.
		createWidget('Facet (Tags)', 'post_tag');

		cy.visit('/');

		// We should have two widgets now, one of them the created above.
		cy.get('.widget_ep-facet').should('have.length', 2);
		cy.contains('.widget-title', 'Facet (Tags)').should('be.visible');

		// Check if the widget search works. Additionally, checks a hyphenated slug category.
		cy.get('.widget_ep-facet').first().as('firstWidget');
		cy.get('@firstWidget').find('.facet-search').clearThenType('Parent C');
		cy.get('@firstWidget').contains('.term', 'Parent Category').should('be.visible');
		cy.get('@firstWidget').contains('.term', 'Child Category').should('not.be.visible');

		// Searching in the first widget should not affect the second.
		cy.get('.widget_ep-facet').last().as('lastWidget');
		cy.get('@lastWidget').contains('.term', 'content').should('be.visible');

		// Clear the search input and click in a term that was not visible before.
		cy.get('@firstWidget').find('.facet-search').clear();
		cy.get('@firstWidget').contains('.term', 'Classic').click();

		// URL should have changed and selected term should be marked as checked.
		cy.url().should('include', 'ep_filter_category=classic');
		cy.get('@firstWidget')
			.contains('.term', 'Classic')
			.find('.ep-checkbox')
			.should('have.class', 'checked');

		// Visible articles should contain the selected category.
		cy.get('article').each(($article) => {
			cy.wrap($article).contains('.cat-links a', 'Classic').should('be.visible');
		});

		// Check pagination.
		cy.get('.next.page-numbers').click();
		cy.url().should('include', 'page/2/?ep_filter_category=classic');
		cy.get('article').each(($article) => {
			cy.wrap($article).contains('.cat-links a', 'Classic').should('be.visible');
		});

		// Check if pagination resets when clicking on a different term.
		cy.get('@firstWidget').contains('.term', 'Post Formats').click();
		cy.url().should('include', 'ep_filter_category=classic%2Cpost-formats');
		cy.url().should('not.include', 'page');
	});

	it('Does not change post types being displayed', () => {
		cy.wpCliEval(
			`
			WP_CLI::runcommand( 'plugin activate cpt-and-custom-tax' );
			WP_CLI::runcommand( 'post create --post_title="A new page" --post_type="page" --post_status="publish"' );
			WP_CLI::runcommand( 'post create --post_title="A new post" --post_type="post" --post_status="publish"' );
			WP_CLI::runcommand( 'post create --post_title="A new post" --post_type="post" --post_status="publish"' );

			// tax_input does not seem to work properly in WP-CLI.
			$movie_id = wp_insert_post(
				[
					'post_title'  => 'A new movie',
					'post_type'   => 'movie',
					'post_status' => 'publish',
				]
			);
			if ( $movie_id ) {
				wp_set_object_terms( $movie_id, 'action', 'genre' );
				WP_CLI::runcommand( 'elasticpress index --include=' . $movie_id );
				WP_CLI::runcommand( 'rewrite flush' );
			}
			`,
		);

		// Blog page
		cy.visit('/');
		cy.contains('.site-content article h2', 'A new page').should('not.exist');
		cy.contains('.site-content article h2', 'A new post').should('exist');
		cy.contains('.site-content article h2', 'A new movie').should('not.exist');

		// Specific taxonomy archive
		cy.visit('/blog/genre/action/');
		cy.contains('.site-content article h2', 'A new page').should('not.exist');
		cy.contains('.site-content article h2', 'A new post').should('not.exist');
		cy.contains('.site-content article h2', 'A new movie').should('exist');

		// Search
		cy.visit('/?s=new');
		cy.contains('.site-content article h2', 'A new page').should('exist');
		cy.contains('.site-content article h2', 'A new post').should('exist');
		cy.contains('.site-content article h2', 'A new movie').should('exist');
	});
});
