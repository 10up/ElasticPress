describe('Facets Feature', () => {
	before(() => {
		cy.maybeEnableFeature('facets');
		cy.wpCli('elasticpress index --setup --yes');
		cy.wpCli('wp plugin install classic-widgets');
	});

	beforeEach(() => {
		cy.wpCli('widget reset --all');
		cy.wpCli('wp plugin deactivate classic-widgets');
	});

	/**
	 * Test that the Related Posts block is functional.
	 */
	it('Facets block is functional', () => {
		/**
		 * Visit the block-based Widgets screen.
		 */
		cy.openWidgetsPage();

		/**
		 * Insert a Facets block.
		 */
		cy.openBlockInserter();
		cy.getBlocksList().should('contain.text', 'Facet (ElasticPress)');
		cy.insertBlock('Facet (ElasticPress)');
		cy.get('.wp-block-elasticpress-facet').first().as('facetBlock');

		/**
		 * Check that the block is inserted into the editor, and contains the
		 * expected content.
		 */
		cy.get('@facetBlock').find('input').should('have.attr', 'placeholder', 'Search Categories');
		cy.get('@facetBlock').click();

		/**
		 * Set the block to use Tags and sort by name in ascending order.
		 */
		cy.openBlockSettingsSidebar();
		cy.get('.block-editor-block-inspector select').select('post_tag');
		cy.get('.block-editor-block-inspector input[type="radio"][value="name"]').click();
		cy.get('.block-editor-block-inspector input[type="radio"][value="asc"]').click();

		/**
		 * Verify the block has the expected output.
		 */
		cy.get('@facetBlock').find('input').should('have.attr', 'placeholder', 'Search Tags');
		cy.get('@facetBlock').find('.term').should('be.elementsSortedAlphabetically');

		/**
		 * Save widgets.
		 */
		cy.intercept('/wp-json/wp/v2/sidebars/*').as('sidebarsRest');
		cy.get('.edit-widgets-header__actions button').contains('Update').click();
		cy.wait('@sidebarsRest');

		/**
		 * Check the output of the block on the front end.
		 */
		cy.visit('/');
		cy.get('.wp-block-elasticpress-facet').first().as('facetBlock');
		cy.get('@facetBlock').find('input').should('have.attr', 'placeholder', 'Search Tags');
		cy.get('@facetBlock').find('.term').should('be.elementsSortedAlphabetically');
	});

	/**
	 * Test that the Related Posts widget is functional and can be transformed
	 * into the Related Posts block.
	 */
	it('Related Posts widget is functional', () => {
		/**
		 * Visit the classic Widgets screen.
		 */
		cy.wpCli('wp plugin activate classic-widgets');
		cy.openWidgetsPage();
		cy.intercept('/wp-admin/admin-ajax.php').as('adminAjax');

		/**
		 * Find and add the widget to the first widget area.
		 */
		cy.get(`#widget-list [id$="ep-facet-__i__"]`)
			.click('top')
			.within(() => {
				cy.get('.widgets-chooser-add').click();
			})
			.wait('@adminAjax');

		/**
		 * Add a title, set facet options, and save.
		 */
		cy.get(`#widgets-right [id*="ep-facet"]`)
			.within(() => {
				cy.get('input[name$="[title]"]').clearThenType('My facet');
				cy.get('select[name$="[facet]"]').select('post_tag');
				cy.get('select[name$="[orderby]"]').select('name');
				cy.get('select[name$="[order]"]').select('asc');
				cy.get('input[type="submit"]').click();
			})
			.wait('@adminAjax');

		/**
		 * When viewing the last post the widget should be visible and contain
		 * the correct title and number of posts.
		 */
		cy.visit('/');
		cy.get('.widget_ep-facet').first().as('facetWidget');
		cy.get('@facetWidget').find('input').should('have.attr', 'placeholder', 'Search Tags');
		cy.get('@facetWidget').find('.term').should('be.elementsSortedAlphabetically');

		/**
		 * Visit the block-based Widgets screen.
		 */
		cy.wpCli('wp plugin deactivate classic-widgets');
		cy.openWidgetsPage();

		/**
		 * Check that the widget is inserted in to the editor as a Legacy
		 * Widget block.
		 */
		cy.get('.wp-block-legacy-widget')
			.should('contain.text', 'ElasticPress - Facet')
			.first()
			.click();

		/**
		 * Transform the legacywidget into the block.
		 */
		cy.get('.block-editor-block-switcher button').click();
		cy.get(
			'.block-editor-block-switcher__popover .editor-block-list-item-elasticpress-facet',
		).click();

		/**
		 * Check that the widget has been transformed into the correct blocks
		 * and that their content matches the widget's settings.
		 */
		cy.get('.wp-block-heading').contains('My facet').should('exist');
		cy.get('.wp-block-elasticpress-facet').first().as('facetBlock');
		cy.get('@facetBlock').find('input').should('have.attr', 'placeholder', 'Search Tags');
		cy.get('@facetBlock').find('.term').should('be.elementsSortedAlphabetically');

		/**
		 * Save widgets.
		 */
		cy.intercept('/wp-json/wp/v2/sidebars/*').as('sidebarsRest');
		cy.get('.edit-widgets-header__actions button').contains('Update').click();
		cy.wait('@sidebarsRest');

		/**
		 * Check the output of the block on the front end.
		 */
		cy.visit('/');
		cy.get('.wp-block-elasticpress-facet').first().as('facetBlock');
		cy.get('@facetBlock').find('input').should('have.attr', 'placeholder', 'Search Tags');
		cy.get('@facetBlock').find('.term').should('be.elementsSortedAlphabetically');

		/**
		 * TODO: Test searching within block and if links are correct.
		 */
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
