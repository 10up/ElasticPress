describe('Facets Feature', { tags: '@slow' }, () => {
	/**
	 * Ensure the feature is active, perform a sync, and remove test posts
	 * before running tests.
	 */
	before(() => {
		cy.maybeEnableFeature('facets');
		cy.wpCli('elasticpress sync --setup --yes');
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
	 * Test that the Facet by Taxonomy block is functional.
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

		// Make sure it waits for the correct request.
		cy.intercept('/wp-json/elasticpress/v1/facets/block-preview*orderby=name&order=asc*').as(
			'blockPreview1',
		);
		cy.get('.block-editor-block-inspector input[type="radio"][value="asc"]').click();
		cy.wait('@blockPreview1');

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
				WP_CLI::runcommand( 'elasticpress sync --include=' . $movie_id );
				WP_CLI::runcommand( 'rewrite flush' );
			}
			`,
		);

		/**
		 * Give Elasticsearch some time to process the post.
		 *
		 */
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(2000);

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

	describe('Facet by Meta Block', () => {
		before(() => {
			cy.wpCli('post list --meta_key=facet_by_meta_tests --meta_value=1 --format=ids').then(
				(wpCliResponse) => {
					if (wpCliResponse.stdout) {
						cy.wpCli(`post delete ${wpCliResponse.stdout} --force`);
					}
				},
			);
			cy.wpCliEval(
				`
				for ( $i = 1; $i <= 20; $i++ ) {
					wp_insert_post(
						[
							'post_title'  => "Facet By Meta Post {$i}",
							'post_status' => 'publish',
							'meta_input'  => [
								'facet_by_meta_tests' => 1,
								'meta_field_1'        => "Meta Value (1) - {$i}",
								'meta_field_2'        => "Meta Value (2) - {$i}",
							],
						]
					);
				}
				`,
			);
		});

		/**
		 * Test that the Facet by Meta block is functional.
		 */
		it('Can insert, configure, and use the Facet by Meta block', () => {
			/**
			 * Insert a Facet block.
			 */
			cy.openWidgetsPage();
			cy.openBlockInserter();
			cy.getBlocksList().should('contain.text', 'Facet by Meta (ElasticPress)');
			cy.insertBlock('Facet by Meta (ElasticPress)');
			cy.get('.wp-block-elasticpress-facet-meta').last().as('block1');

			// Configure the block
			cy.get('@block1').click();
			cy.openBlockSettingsSidebar();
			cy.get('.block-editor-block-inspector input[type="text"]').clearThenType(
				'Search Meta 1',
			);

			cy.intercept(
				'/wp-json/elasticpress/v1/facets/meta/block-preview*facet=meta_field_1*',
			).as('blockPreview1');
			cy.get('.block-editor-block-inspector select').select('meta_field_1');
			cy.wait('@blockPreview1');

			/**
			 * Verify that the blocks are inserted into the editor, and contain the
			 * expected content.
			 */
			cy.get('@block1').find('input').should('have.attr', 'placeholder', 'Search Meta 1');

			/**
			 * Insert a second block.
			 */
			cy.openBlockInserter();
			cy.getBlocksList().should('contain.text', 'Facet by Meta (ElasticPress)');
			cy.insertBlock('Facet by Meta (ElasticPress)');
			cy.get('.wp-block-elasticpress-facet-meta').last().as('block2');

			// Configure the block
			cy.get('@block2').click();
			cy.openBlockSettingsSidebar();
			cy.get('.block-editor-block-inspector input[type="text"]').clearThenType(
				'Search Meta 2',
			);
			cy.get('.block-editor-block-inspector select').select('meta_field_2');
			cy.get('.block-editor-block-inspector input[type="radio"][value="name"]').click();

			cy.intercept(
				'/wp-json/elasticpress/v1/facets/meta/block-preview*orderby=name&order=asc*',
			).as('blockPreview2');
			cy.get('.block-editor-block-inspector input[type="radio"][value="asc"]').click();
			cy.wait('@blockPreview2');

			/**
			 * Verify the block has the expected output in the editor based on the
			 * block's settings.
			 */
			cy.get('@block2').find('input').should('have.attr', 'placeholder', 'Search Meta 2');
			cy.get('@block2').find('.term').should('be.elementsSortedAlphabetically');

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
			cy.get('@firstBlock').find('input').should('have.attr', 'placeholder', 'Search Meta 1');
			cy.get('@secondBlock')
				.find('input')
				.should('have.attr', 'placeholder', 'Search Meta 2');
			cy.get('@secondBlock').find('.term').should('be.elementsSortedAlphabetically');

			/**
			 * Typing in the input should filter the list of terms for that block
			 * without affecting other blocks.
			 */
			cy.get('@firstBlock').find('input').as('firstBlockSearch').clearThenType('12');
			cy.get('@firstBlock').contains('.term', 'Meta Value (1) - 12').should('be.visible');
			cy.get('@firstBlockSearch').clearThenType('Meta Value (1) - 13');
			cy.get('@firstBlock').contains('.term', 'Meta Value (1) - 13').should('be.visible');

			/**
			 * Clearing the input should restore previously hidden terms and allow
			 * them to be selected.
			 */
			cy.get('@firstBlockSearch').clear();
			cy.get('@firstBlock').contains('.term', 'Meta Value (1) - 20').click();

			/**
			 * Selecting that term should lead to the correct URL, mark the correct
			 * item as checked, and all articles being displayed should have the
			 * selected category.
			 */
			cy.url().should('include', 'ep_meta_filter_meta_field_1=Meta%20Value%20(1)%20-%2020');
			cy.get('@firstBlock')
				.contains('.term', 'Meta Value (1) - 20')
				.find('.ep-checkbox')
				.should('have.class', 'checked');
			cy.contains('.site-content article:nth-of-type(1) h2', 'Facet By Meta Post 20').should(
				'exist',
			);

			/**
			 * Facets should continue to apply across pagination.
			cy.get('.page-numbers.next').click();
			cy.url().should('include', 'page/2');
			cy.url().should('include', 'ep_filter_category=classic');
			cy.get('article').each(($article) => {
				cy.wrap($article).contains('.cat-links a', 'Classic').should('exist');
			});
			 */

			/**
			 * When another facet is selected pagination should reset and results
			 * should be filtered by both selections.
			 */
			cy.get('@secondBlock').contains('.term', 'Meta Value (2) - 20').click();
			cy.url().should('include', 'ep_meta_filter_meta_field_1=Meta%20Value%20(1)%20-%2020');
			cy.url().should('include', 'ep_meta_filter_meta_field_2=Meta%20Value%20(2)%20-%2020');
			cy.url().should('not.include', 'page/2');
			cy.get('@firstBlock')
				.contains('.term', 'Meta Value (1) - 20')
				.find('.ep-checkbox')
				.should('have.class', 'checked');
			cy.get('@secondBlock')
				.contains('.term', 'Meta Value (2) - 20')
				.find('.ep-checkbox')
				.should('have.class', 'checked');
			cy.contains('.site-content article:nth-of-type(1) h2', 'Facet By Meta Post 20').should(
				'exist',
			);

			/**
			 * Clicking selected facet should remove it while keeping any other
			 * facets active.
			 */
			cy.get('@secondBlock').contains('.term', 'Meta Value (2) - 20').click();
			cy.url().should(
				'not.include',
				'ep_meta_filter_meta_field_2=Meta%20Value%20(2)%20-%2020',
			);
			cy.url().should('include', 'ep_meta_filter_meta_field_1=Meta%20Value%20(1)%20-%2020');
			cy.get('@secondBlock')
				.contains('a[aria-disabled="true"]', 'Meta Value (2) - 19')
				.should('exist');

			/**
			 * When Match Type is "any", all options need to be clickable
			 */
			cy.visitAdminPage('admin.php?page=elasticpress');
			cy.get('.ep-feature-facets .settings-button').click();
			cy.get('input[name="settings[match_type]"][value="any"]').check();
			cy.get('.ep-feature-facets .button-primary').click();

			cy.visit('/');
			cy.get('@secondBlock').contains('.term', 'Meta Value (2) - 20').click();
			cy.get('@secondBlock').contains('.term', 'Meta Value (2) - 1').click();
			cy.get('.wp-block-elasticpress-facet a[aria-disabled="true"]').should('not.exist');
			cy.contains('.site-content article h2', 'Facet By Meta Post 20').should('exist');
			cy.contains('.site-content article h2', 'Facet By Meta Post 1').should('exist');
		});
	});
});
