describe('Facets Feature', () => {
	/**
	 * Ensure the feature is active, perform an index, and remove test posts
	 * before running tests.
	 */
	before(() => {
		cy.maybeEnableFeature('facets');
		cy.wpCli('elasticpress index --setup --yes');
		cy.wpCli('post list --s="A new" --ep_integrate=false --format=ids').then(
			(wpCliResponse) => {
				if (wpCliResponse.stdout) {
					cy.wpCli(`post delete ${wpCliResponse.stdout} --force`);
				}
			},
		);
	});

	/**
	 * Delete all widgets and ensure Classic Widgets is deactivated before each
	 * test.
	 */
	beforeEach(() => {
		cy.emptyWidgets();
		cy.deactivatePlugin('classic-widgets', 'wpCli');
	});

	/**
	 * Test that the Related Posts block is functional.
	 */
	it('Can insert, configure, and use the Facet by Taxonomy block', () => {
		/**
		 * Insert two Facets blocks.
		 */
		cy.openWidgetsPage();
		cy.openBlockInserter();
		cy.getBlocksList().should('contain.text', 'Facet by Taxonomy (ElasticPress)');
		cy.insertBlock('Facet by Taxonomy (ElasticPress)');
		cy.insertBlock('Facet by Taxonomy (ElasticPress)');
		cy.get('.wp-block-elasticpress-facet').last().as('block');

		/**
		 * Verify that the blocks are inserted into the editor, and contain the
		 * expected content.
		 */
		cy.get('@block').find('input').should('have.attr', 'placeholder', 'Search Categories');

		/**
		 * Set the second block to use Tags and sort by name in ascending order.
		 */
		cy.get('@block').click();
		cy.openBlockSettingsSidebar();
		cy.get('.block-editor-block-inspector select').select('post_tag');
		cy.get('.block-editor-block-inspector input[type="radio"][value="name"]').click();
		cy.get('.block-editor-block-inspector input[type="radio"][value="asc"]').click();

		/**
		 * Verify the block has the expected output in the editor based on the
		 * block's settings.
		 */
		cy.get('@block').find('input').should('have.attr', 'placeholder', 'Search Tags');
		cy.get('@block').find('.term').should('be.elementsSortedAlphabetically');

		/**
		 * Save widgets and visit the front page.
		 */
		cy.intercept('/wp-json/wp/v2/sidebars/*').as('sidebarsRest');
		cy.get('.edit-widgets-header__actions button').contains('Update').click();
		cy.wait('@sidebarsRest');
		cy.visit('/');

		/**
		 * Verify the blocks have the expected output on the front-end based on
		 * their settings.
		 */
		cy.get('.wp-block-elasticpress-facet').first().as('firstBlock');
		cy.get('.wp-block-elasticpress-facet').last().as('secondBlock');
		cy.get('@firstBlock').find('input').should('have.attr', 'placeholder', 'Search Categories');
		cy.get('@secondBlock').find('input').should('have.attr', 'placeholder', 'Search Tags');
		cy.get('@secondBlock').find('.term').should('be.elementsSortedAlphabetically');

		/**
		 * Typing in the input should filter the list of terms for that block
		 * without affecting other blocks.
		 */
		cy.get('@firstBlock').find('input').as('firstBlockSearch').clearThenType('Parent C');
		cy.get('@firstBlock').contains('.term', 'Parent Category').should('be.visible');
		cy.get('@firstBlock').contains('.term', 'Child Category').should('not.be.visible');
		cy.get('@secondBlock').contains('.term', 'content').should('be.visible');

		/**
		 * Clearing the input should restore previously hidden terms and allow
		 * them to be selected.
		 */
		cy.get('@firstBlockSearch').clear();
		cy.get('@firstBlock').contains('.term', 'Classic').click();

		/**
		 * Selecting that term should lead to the correct URL, mark the correct
		 * item as checked, and all articles being displayed should have the
		 * selected category.
		 */
		cy.url().should('include', 'ep_filter_category=classic');
		cy.get('@firstBlock')
			.contains('.term', 'Classic')
			.find('.ep-checkbox')
			.should('have.class', 'checked');
		cy.get('article').each(($article) => {
			cy.wrap($article).contains('.cat-links a', 'Classic').should('exist');
		});

		/**
		 * Facets should continue to apply across pagination.
		 */
		cy.get('.page-numbers.next').click();
		cy.url().should('include', 'page/2');
		cy.url().should('include', 'ep_filter_category=classic');
		cy.get('article').each(($article) => {
			cy.wrap($article).contains('.cat-links a', 'Classic').should('exist');
		});

		/**
		 * When another facet is selected pagination should reset and results
		 * should be filtered by both selections.
		 */
		cy.get('@secondBlock').contains('.term', 'template').click();
		cy.url().should('include', 'ep_filter_category=classic');
		cy.url().should('include', 'ep_filter_post_tag=template');
		cy.url().should('not.include', 'page/2');
		cy.get('@firstBlock')
			.contains('.term', 'Classic')
			.find('.ep-checkbox')
			.should('have.class', 'checked');
		cy.get('@secondBlock')
			.contains('.term', 'template')
			.find('.ep-checkbox')
			.should('have.class', 'checked');
		cy.get('article').each(($article) => {
			cy.wrap($article).contains('.cat-links a', 'Classic').should('exist');
			cy.wrap($article).contains('.tags-links a', 'template').should('exist');
		});

		/**
		 * Clicking selected facet should remove it while keeping any other
		 * facets active.
		 */
		cy.get('@secondBlock').contains('.term', 'template').click();
		cy.url().should('not.include', 'ep_filter_post_tag=template');
		cy.url().should('include', 'ep_filter_category=classic');
	});

	/**
	 * Test that the Facet widget is functional and can be transformed into the
	 * Facet block.
	 */
	it('Can insert, configure, use, and transform the legacy Facet widget', () => {
		/**
		 * Add the legacy widget.
		 */
		cy.activatePlugin('classic-widgets', 'wpCli');
		cy.createClassicWidget('ep-facet', [
			{
				name: 'title',
				value: 'My facet',
			},
			{
				name: 'facet',
				value: 'post_tag',
				type: 'select',
			},
			{
				name: 'orderby',
				value: 'name',
				type: 'select',
			},
			{
				name: 'order',
				value: 'asc',
				type: 'select',
			},
		]);

		/**
		 * Verify the widget has the expected output on the front-end based on
		 * the widget's settings.
		 */
		cy.visit('/');
		cy.get('.widget_ep-facet').first().as('widget');
		cy.get('@widget').find('input').should('have.attr', 'placeholder', 'Search Tags');
		cy.get('@widget').find('.term').should('be.elementsSortedAlphabetically');

		/**
		 * Visit the block-based widgets screen.
		 */
		cy.deactivatePlugin('classic-widgets', 'wpCli');
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
		 * Transform the legacy widget into the block.
		 */
		cy.get('.block-editor-block-switcher button').click();
		cy.get(
			'.block-editor-block-switcher__popover .editor-block-list-item-elasticpress-facet',
		).click();

		/**
		 * Check that the widget has been transformed into the correct blocks.
		 */
		cy.get('.wp-block-heading').contains('My facet').should('exist');
		cy.get('.wp-block-elasticpress-facet').should('exist').first().as('block');

		/**
		 * Check that the block's settings match the widget's.
		 */
		cy.get('@block').click();
		cy.get('.block-editor-block-inspector option[value="post_tag"]').should('be.selected');
		cy.get('.block-editor-block-inspector input[value="name"]').should('be.checked');
		cy.get('.block-editor-block-inspector input[value="asc"]').should('be.checked');
	});

	/**
	 * Test that the blog, taxonomy archives, and search only display the
	 * expected post types.
	 */
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
