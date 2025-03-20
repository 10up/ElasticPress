// eslint-disable-next-line jest/valid-describe-callback
describe('Facets Feature', { tags: '@slow' }, () => {
	/**
	 * Ensure the feature is active, perform a sync, and remove test posts
	 * before running tests.
	 */
	before(() => {
		cy.wpCliEval(`
			\\ElasticPress\\Features::factory()->activate_feature('facets' );
			WP_CLI::runcommand( 'elasticpress sync --setup --yes' );
			$posts = new \\WP_Query(
				[
					's'            => 'A new',
					'ep_integrate' => false,
					'fields'       => 'ids',
				]
			);
			foreach ( $posts->posts as $post ) {
				wp_delete_post( $post, true );
			}
		`);

		cy.updateWeighting();

		cy.visitAdminPage('admin.php?page=elasticpress-weighting');

		cy.intercept('/wp-json/elasticpress/v1/weighting*').as('apiRequest');
		cy.contains('h2', 'Posts').closest('.components-panel').as('postsPanel');

		cy.get('@postsPanel').contains('button', 'Metadata').click();

		cy.get('@postsPanel').find('input[type="text"]').as('metaInput');
		cy.get('@postsPanel').contains('button', 'Add').as('metaAdd');

		cy.get('@metaInput').clearThenType('meta_field_1');
		cy.get('@metaAdd').click();
		cy.get('@metaInput').clearThenType('meta_field_2');
		cy.get('@metaAdd').click();
		cy.get('@metaInput').clearThenType('numeric_meta_field');
		cy.get('@metaAdd').click();
		cy.get('@metaInput').clearThenType('non_numeric_meta_field');
		cy.get('@metaAdd').click();

		cy.contains('button', 'Save changes').click();

		cy.wait('@apiRequest');
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
	 * Test that the Filter by Taxonomy block is functional.
	 */
	it('Can insert, configure, and use the Filter by Taxonomy block', () => {
		/**
		 * Insert two Filter blocks.
		 */
		cy.openWidgetsPage();
		cy.openBlockInserter();
		cy.getBlocksList().should('contain.text', 'Filter by Taxonomy');
		cy.insertBlock('Filter by Taxonomy');
		cy.insertBlock('Filter by Taxonomy');
		cy.get('.wp-block.wp-block-elasticpress-facet').first().as('firstBlock');
		cy.get('.wp-block.wp-block-elasticpress-facet').last().as('secondBlock');

		/**
		 * Verify that the blocks are inserted into the editor, and contain the
		 * expected content.
		 */
		cy.get('@firstBlock').find('select').should('contain', 'Select taxonomy');
		cy.get('@secondBlock').find('select').should('contain', 'Select taxonomy');

		/**
		 * Set the first block to use Categories.
		 */
		cy.get('@firstBlock').click();
		cy.openBlockSettingsSidebar();
		cy.intercept('/wp-json/wp/v2/block-renderer/elasticpress/facet*').as('blockPreview');
		cy.get('.block-editor-block-inspector select').first().select('category');
		cy.wait('@blockPreview');

		/**
		 * Set the last block to use Tags and sort by name in ascending order.
		 */
		cy.get('@secondBlock').click();
		cy.get('.block-editor-block-inspector select').first().select('post_tag');
		cy.wait('@blockPreview');
		cy.get('.block-editor-block-inspector select').last().select('name/asc');
		cy.wait('@blockPreview');

		/**
		 * Verify the blocks have the expected output in the editor based on the
		 * block's settings.
		 */
		cy.get('@firstBlock').find('input').should('have.attr', 'placeholder', 'Search Categories');
		cy.get('@secondBlock').find('input').should('have.attr', 'placeholder', 'Search Tags');
		cy.get('@secondBlock').find('.term').should('be.elementsSortedAlphabetically');

		/**
		 * Verify the display count setting on the editor.
		 */
		cy.get('@secondBlock')
			.contains('.term', /\(\d*\)$/)
			.should('not.exist');
		cy.get('.block-editor-block-inspector .components-form-toggle__input').click();
		cy.wait('@blockPreview');
		cy.get('@secondBlock')
			.contains('.term', /(^\(\d*\))$/)
			.should('not.exist');

		/**
		 * Test that the block supports changing styles.
		 */
		cy.get('@secondBlock').supportsBlockColors(true);
		cy.get('@secondBlock').supportsBlockTypography(true);
		cy.get('@secondBlock').supportsBlockDimensions(true);

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
		cy.get('@firstBlock')
			.contains('.term', /\(\d*\)$/)
			.should('not.exist');
		cy.get('@secondBlock').find('input').should('have.attr', 'placeholder', 'Search Tags');
		cy.get('@secondBlock').find('.term').should('be.elementsSortedAlphabetically');
		cy.get('@secondBlock')
			.contains('.term', /(^\(\d*\))$/)
			.should('not.exist');

		/**
		 * Verify that the block supports changing styles.
		 */
		cy.get('@secondBlock').supportsBlockColors();
		cy.get('@secondBlock').supportsBlockTypography();
		cy.get('@secondBlock').supportsBlockDimensions();

		/**
		 * Typing in the input should filter the list of terms for that block
		 * without affecting other blocks.
		 */
		cy.get('@firstBlock').find('input').as('firstBlockSearch').clearThenType('Parent C');
		cy.get('@firstBlock').contains('.term', 'Parent Category').should('not.have.class', 'hide');
		cy.get('@firstBlock').contains('.term', 'Child Category').should('have.class', 'hide');
		cy.get('@secondBlock').contains('.term.hide').should('not.exist');

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
	 * Filter block.
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
			.should('contain.text', 'ElasticPress - Filter by Taxonomy')
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
		cy.get('.block-editor-block-inspector option[value="name/asc"]').should('be.selected');
	});

	/**
	 * Test that the blog, taxonomy archives, and search only display the
	 * expected post types.
	 */
	it('Does not change post types being displayed', () => {
		cy.wpCliEval(
			`
			activate_plugin( 'cpt-and-custom-tax.php' );
			wp_insert_post(
				[
					'post_title'  => 'A new page',
					'post_type'   => 'page',
					'post_status' => 'publish',
				]
			);
			wp_insert_post(
				[
					'post_title'  => 'A new post',
					'post_type'   => 'post',
					'post_status' => 'publish',
				]
			);

			$movie_id = wp_insert_post(
				[
					'post_title'  => 'A new movie',
					'post_type'   => 'movie',
					'post_status' => 'publish',
				]
			);
			wp_set_object_terms( $movie_id, 'action', 'genre' );
			`,
		).then(() => {
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
	});

	describe('Filter by Metadata block', () => {
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
		 * Test that the Filter by Metadata block is functional.
		 */
		it('Can insert, configure, and use the Filter by Metadata block', () => {
			/**
			 * Insert a Filter by Metadata block.
			 */
			cy.openWidgetsPage();
			cy.openBlockInserter();
			cy.getBlocksList().should('contain.text', 'Filter by Metadata');
			cy.insertBlock('Filter by Metadata');
			cy.get('.wp-block.wp-block-elasticpress-facet-meta').last().as('firstBlock');

			// Configure the block
			cy.get('@firstBlock').click();
			cy.openBlockSettingsSidebar();
			cy.get('.block-editor-block-inspector input[type="text"]').clearThenType(
				'Search Meta 1',
				true,
			);

			cy.intercept('/wp-json/wp/v2/block-renderer/elasticpress/facet-meta*').as(
				'blockPreview',
			);
			cy.get('.block-editor-block-inspector select').first().select('meta_field_1');
			cy.wait('@blockPreview');

			/**
			 * Verify that the blocks are inserted into the editor, and contain the
			 * expected content.
			 */
			cy.get('@firstBlock').find('input').should('have.attr', 'placeholder', 'Search Meta 1');

			/**
			 * Verify the display count setting on the editor.
			 */
			cy.get('@firstBlock')
				.contains('.term', /\(\d*\)$/)
				.should('not.exist');
			cy.get('.block-editor-block-inspector .components-form-toggle__input').click();
			cy.wait('@blockPreview');
			cy.get('@firstBlock')
				.contains('.term', /(^\(\d*\))$/)
				.should('not.exist');

			/**
			 * Insert a second block.
			 */
			cy.openBlockInserter();
			cy.getBlocksList().should('contain.text', 'Filter by Metadata');
			cy.insertBlock('Filter by Metadata');
			cy.get('.wp-block.wp-block-elasticpress-facet-meta').last().as('secondBlock');

			// Configure the block
			cy.get('@secondBlock').click();
			cy.openBlockSettingsSidebar();
			cy.get('.block-editor-block-inspector input[type="text"]').clearThenType(
				'Search Meta 2',
				true,
			);
			cy.get('.block-editor-block-inspector select').first().select('meta_field_2');
			cy.wait('@blockPreview');
			cy.get('.block-editor-block-inspector select').last().select('name/asc');
			cy.wait('@blockPreview');

			/**
			 * Verify the block has the expected output in the editor based on the
			 * block's settings.
			 */
			cy.get('@secondBlock')
				.find('input')
				.should('have.attr', 'placeholder', 'Search Meta 2');
			cy.get('@secondBlock').find('.term').should('be.elementsSortedAlphabetically');

			/**
			 * Test that the block supports changing styles.
			 */
			cy.get('@secondBlock').supportsBlockColors(true);
			cy.get('@secondBlock').supportsBlockTypography(true);
			cy.get('@secondBlock').supportsBlockDimensions(true);

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
			cy.get('@firstBlock')
				.contains('.term', /(^\(\d*\))$/)
				.should('not.exist');

			cy.get('@secondBlock')
				.find('input')
				.should('have.attr', 'placeholder', 'Search Meta 2');
			cy.get('@secondBlock').find('.term').should('be.elementsSortedAlphabetically');
			cy.get('@secondBlock')
				.contains('.term', /\(\d*\)$/)
				.should('not.exist');

			/**
			 * Verify that the block supports changing styles.
			 */
			cy.get('@secondBlock').supportsBlockColors();
			cy.get('@secondBlock').supportsBlockTypography();
			cy.get('@secondBlock').supportsBlockDimensions();

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
			cy.intercept('/wp-json/elasticpress/v1/features*').as('apiRequest');

			cy.contains('button', 'Filters').click();
			cy.contains('label', 'Show results that match any selected filter').click();
			cy.contains('button', 'Save changes').click();

			cy.wait('@apiRequest');

			cy.visit('/');
			cy.get('@secondBlock').contains('.term', 'Meta Value (2) - 20').click();
			cy.get('@secondBlock').contains('.term', 'Meta Value (2) - 1').click();
			cy.get('.wp-block-elasticpress-facet a[aria-disabled="true"]').should('not.exist');
			cy.contains('.site-content article h2', 'Facet By Meta Post 20').should('exist');
			cy.contains('.site-content article h2', 'Facet By Meta Post 1').should('exist');
		});
	});

	describe('Filter by Metadata Range block', () => {
		before(() => {
			/**
			 * Clean up sample posts.
			 */
			cy.wpCli(
				'post list --meta_key=_facet_by_meta_range_tests --meta_compare=EXISTS --format=ids',
			).then((wpCliResponse) => {
				if (wpCliResponse.stdout) {
					cy.wpCli(`post delete ${wpCliResponse.stdout} --force`);
				}
			});

			/**
			 * Create sample posts.
			 */
			cy.wpCliEval(
				`
				for ( $i = 1; $i <= 20; $i++ ) {
					wp_insert_post(
						[
							'post_date_gmt' => "-20 days + {$i} days",
							'post_title'    => "Facet By Meta Range Post {$i}",
							'post_status'   => 'publish',
							'meta_input'    => [
								'_facet_by_meta_range_tests' => 1,
								'numeric_meta_field'        => $i,
								'non_numeric_meta_field'    => "Non-numeric value {$i}",
							],
						]
					);
				}
				`,
			);
		});

		/**
		 * Test that the Filter by Metadata Range block is functional.
		 */
		it('Can insert, configure, and use the Filter by Metadata Range block', () => {
			cy.intercept('/wp-json/elasticpress/v1/meta-keys*').as('keysApiRequest');
			cy.intercept('/wp-json/elasticpress/v1/meta-range*').as('previewApiRequest');

			/**
			 * Insert a Filter by Metadata Range block.
			 */
			cy.openWidgetsPage();
			cy.openBlockInserter();
			cy.getBlocksList().should('contain.text', 'Filter by Metadata Range - Beta');
			cy.insertBlock('Filter by Metadata Range - Beta');
			cy.get('.wp-block.wp-block-elasticpress-facet-meta-range').last().as('block');

			/**
			 * The block should prompt to select a field.
			 */
			cy.get('@block').should('contain.text', 'Filter by Metadata Range');
			cy.get('@block').get('select').should('exist');

			/**
			 * After selecting a field a preview should display.
			 */
			cy.wait('@keysApiRequest');
			cy.get('@block').get('select').select('numeric_meta_field');
			cy.wait('@previewApiRequest');
			cy.get('@block').get('.ep-range-facet').should('exist');
			cy.get('@block').get('.ep-range-facet__values').should('contain.text', '1 — 20');

			/**
			 * Changes to the prefix and suffix should be reflected in the preview.
			 */
			cy.get('@block').click();
			cy.openBlockSettingsSidebar();
			cy.get('.block-editor-block-inspector input[type="text"]').eq(0).clearThenType('$');
			cy.get('.block-editor-block-inspector input[type="text"]').eq(1).clearThenType('/day');
			cy.get('@block')
				.get('.ep-range-facet__values')
				.should('contain.text', '$1/day — $20/day');

			/**
			 * It should be possible to change the field from the block inspector.
			 */
			cy.get('.block-editor-block-inspector select').first().select('non_numeric_meta_field');

			/**
			 * A non-numeric field should show a warning.
			 */
			cy.get('@block').should('contain.text', 'Preview unavailable.');

			/**
			 * Changing the field back should restore a preview.
			 */
			cy.get('.block-editor-block-inspector select').first().select('numeric_meta_field');
			cy.get('@block').get('.ep-range-facet').should('exist');

			/**
			 * Test that the block supports changing styles.
			 */
			cy.get('@block').supportsBlockColors(true);
			cy.get('@block').supportsBlockTypography(true);
			cy.get('@block').supportsBlockDimensions(true);

			/**
			 * Insert a regular Filter by Metadata block.
			 */
			cy.openBlockInserter();
			cy.getBlocksList().should('contain.text', 'Filter by Metadata');
			cy.insertBlock('Filter by Metadata');
			cy.get('.wp-block-elasticpress-facet-meta').last().click();
			cy.openBlockSettingsSidebar();
			cy.get('.block-editor-block-inspector select').first().select('non_numeric_meta_field');

			/**
			 * Save widgets and visit the front page.
			 */
			cy.intercept('/wp-json/wp/v2/sidebars*').as('sidebarsRest');
			cy.get('.edit-widgets-header__actions button').contains('Update').click();
			cy.wait('@sidebarsRest');
			cy.visit('/');

			/**
			 * The block should be rendered on the front end and display the
			 * prefix and suffix.
			 */
			cy.get('.wp-block-elasticpress-facet').as('block');
			cy.get('@block').get('.ep-range-facet').should('exist');
			cy.get('@block').get('.ep-range-slider__thumb').as('thumbs').should('exist');
			cy.get('@block').should('contain.text', '$1/day — $20/day');
			cy.get('@block').get('.ep-range-facet__action a').should('not.exist');

			/**
			 * Verify that the block supports changing styles.
			 */
			cy.get('@block').supportsBlockColors();
			cy.get('@block').supportsBlockTypography();
			cy.get('@block').supportsBlockDimensions();

			/**
			 * Selecting a range and pressing Filter should filter the results.
			 */
			cy.window().then((window) => {
				window.app.sliderChange([9, 12]);
			});
			cy.get('@block').should('contain.text', '$9/day — $12/day');
			cy.get('@block').get('button').first().click();
			cy.get('.post').should('have.length', 4);
			cy.url().should('include', 'ep_meta_range_filter_numeric_meta_field_min=9');
			cy.url().should('include', 'ep_meta_range_filter_numeric_meta_field_max=12');
			cy.get('@block').get('.ep-range-facet__action a').should('exist');
			cy.get('.post').contains('Facet By Meta Range Post 9').should('exist');
			cy.get('.post').contains('Facet By Meta Range Post 10').should('exist');
			cy.get('.post').contains('Facet By Meta Range Post 11').should('exist');
			cy.get('.post').contains('Facet By Meta Range Post 12').should('exist');
			cy.get('.post').contains('Facet By Meta Range Post 14').should('not.exist');
			cy.get('.post').contains('Facet By Meta Range Post 20').should('not.exist');

			/**
			 * After selecting a narrow range of values it should be possible
			 * to adjust the filter to a wider range.
			 */
			cy.window().then((window) => {
				window.app.sliderChange([7, 14]);
			});
			cy.get('@block').should('contain.text', '$7/day — $14/day');
			cy.get('@block').get('button').first().click();
			cy.url().should('include', 'ep_meta_range_filter_numeric_meta_field_min=7');
			cy.url().should('include', 'ep_meta_range_filter_numeric_meta_field_max=14');
			cy.get('.post').contains('Facet By Meta Range Post 14').should('exist');

			/**
			 * Clicking clear should clear the range parameters but not any
			 * other facet parameters.
			 */
			cy.get('.wp-block-elasticpress-facet .term a').first().click();
			cy.url().should('include', 'ep_meta_range_filter_numeric_meta_field_min=7');
			cy.url().should('include', 'ep_meta_range_filter_numeric_meta_field_max=14');
			cy.url().should('include', 'ep_meta_filter_non_numeric_meta_field=Non-numeric');
			cy.get('@block').get('.ep-range-facet__action a').should('exist').click();
			cy.url().should('not.include', 'ep_meta_range_filter_numeric_meta_field_min=7');
			cy.url().should('not.include', 'ep_meta_range_filter_numeric_meta_field_max=14');
			cy.url().should('include', 'ep_meta_filter_non_numeric_meta_field=Non-numeric');
		});
	});

	describe('Facet by Post Type', () => {
		/**
		 * Test that the Facet by Post Type block is functional.
		 */
		it('Can insert, configure, and use the Facet by Post Type block', () => {
			/**
			 * Insert a Facet block.
			 */
			cy.openWidgetsPage();
			cy.openBlockInserter();
			cy.getBlocksList().should('contain.text', 'Filter by Post Type');
			cy.insertBlock('Filter by Post Type');
			cy.get('.wp-block.wp-block-elasticpress-facet-post-type').last().as('block');

			// Configure the block
			cy.get('@block').click();
			cy.openBlockSettingsSidebar();
			cy.get('.block-editor-block-inspector input[type="text"]').clearThenType(
				'Search Post Type',
				true,
			);

			cy.intercept('/wp-json/wp/v2/block-renderer/elasticpress/facet-post-type*').as(
				'blockPreview',
			);

			/**
			 * Verify the display count setting on the editor.
			 */
			cy.get('@block')
				.contains('.term', /\(\d*\)$/)
				.should('not.exist');
			cy.get('.block-editor-block-inspector .components-form-toggle__input').click();
			cy.wait('@blockPreview');
			cy.get('@block')
				.contains('.term', /(^\(\d*\))$/)
				.should('not.exist');
			cy.get('.block-editor-block-inspector .components-select-control__input').select(
				'name/asc',
			);
			cy.wait('@blockPreview');

			/**
			 * Verify the block has the expected output in the editor based on the
			 * block's settings.
			 */
			cy.get('@block').find('.term').should('be.elementsSortedAlphabetically');

			/**
			 * Test that the block supports changing styles.
			 */
			cy.get('@block').supportsBlockColors(true);
			cy.get('@block').supportsBlockTypography(true);
			cy.get('@block').supportsBlockDimensions(true);

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
			cy.get('@firstBlock').find('.term').should('be.elementsSortedAlphabetically');
			cy.get('@firstBlock')
				.contains('.term', /(^\(\d*\))$/)
				.should('not.exist');

			cy.get('@firstBlock').contains('.term', 'Post').click();

			/**
			 * Verify that the block supports changing styles.
			 */
			cy.get('@firstBlock').supportsBlockColors();
			cy.get('@firstBlock').supportsBlockTypography();
			cy.get('@firstBlock').supportsBlockDimensions();

			/**
			 * Selecting that term should lead to the correct URL, mark the correct
			 * item as checked, and all articles being displayed should have the
			 * selected category.
			 */
			cy.url().should('include', 'ep_post_type_filter=post');
			cy.get('@firstBlock')
				.contains('.term', 'Post')
				.find('.ep-checkbox')
				.should('have.class', 'checked');

			/**
			 * Clicking selected facet should remove it while keeping any other
			 * facets active.
			 */
			cy.get('@firstBlock').contains('.term', 'Post').click();
			cy.url().should('not.include', 'ep_post_type_filter=post');
		});
	});

	describe('Facet by Date', () => {
		it('Can insert, configure, and use the Facet by Date block', () => {
			/**
			 * Insert a Facet block.
			 */
			cy.openWidgetsPage();
			cy.openBlockInserter();
			cy.getBlocksList().should('contain.text', 'Filter by Post Date');
			cy.insertBlock('Filter by Post Date');
			cy.get('.wp-block.wp-block-elasticpress-facet-date').last().as('block');

			/**
			 * Verify that there are 4 options
			 */
			cy.get('.ep-facet-date-form .ep-facet-date-option').should('have.length', 4);

			cy.get('@block').click();
			cy.openBlockSettingsSidebar();

			/**
			 * Test that the block supports changing styles.
			 */
			cy.get('@block').supportsBlockColors(true);
			cy.get('@block').supportsBlockTypography(true);
			cy.get('@block').supportsBlockDimensions(true);

			/**
			 * Save widgets and visit the front page.
			 */
			cy.intercept('/wp-json/wp/v2/sidebars/*').as('sidebarsRest');
			cy.get('.edit-widgets-header__actions button').contains('Update').click();
			cy.wait('@sidebarsRest');
			cy.visit('/');

			/**
			 * Verify the blocks have the expected output on the front-end.
			 */
			cy.get('.wp-block-elasticpress-facet-date').first().as('block');

			cy.get('@block')
				.find('.ep-facet-date-form .ep-facet-date-option')
				.should('have.length', 4);

			cy.get('@block')
				.find('.ep-facet-date-form__action-submit')
				.should('exist')
				.contains('Filter');

			/**
			 * Verify that the block supports changing styles.
			 */
			cy.get('@block').supportsBlockColors();
			cy.get('@block').supportsBlockTypography();
			cy.get('@block').supportsBlockDimensions();

			/**
			 * Selecting the last 3 months option should lead to the correct URL, mark the correct
			 */
			cy.get('@block').find('.ep-facet-date-option label').first().click();
			cy.get('@block').find('.wp-element-button').click();

			cy.url().should('include', 'ep_date_filter=last-3-months');
			cy.get('@block')
				.find('.ep-facet-date-option')
				.first()
				.find('input')
				.should('be.checked');

			/**
			 * Verify the custom date range
			 */
			cy.get('@block').find('.ep-facet-date-option').last().find('label').click();

			cy.get('@block').find("[name='ep_date_filter_from']").type('2023-01-01');
			cy.get('@block').find("[name='ep_date_filter_to']").type('2023-12-31');
			cy.get('@block').find('.wp-element-button').click();
			cy.url().should('include', 'ep_date_filter=2023-01-01,2023-12-31');

			/**
			 * Clear filter
			 */
			cy.get('@block').find('.ep-facet-date-form__action-clear').click();
			cy.url().should('not.include', 'ep_date_filter');

			cy.openWidgetsPage();
			cy.openBlockInserter();

			/**
			 * Unselect the Custom Date option
			 */
			cy.get('@block').click();
			cy.openBlockSettingsSidebar();
			cy.get('.block-editor-block-inspector input[type="checkbox"]').uncheck();
			cy.intercept('/wp-json/wp/v2/block-renderer/elasticpress/facet-date*').as(
				'blockPreview',
			);
			cy.wait('@blockPreview');

			cy.get('.ep-facet-date-form .ep-facet-date-option').should('have.length', 3);

			/**
			 * Save widgets and visit the front page.
			 */
			cy.intercept('/wp-json/wp/v2/widgets*').as('widgetsRest');
			cy.get('.edit-widgets-header__actions button').contains('Update').click();
			cy.wait('@widgetsRest');
			cy.visit('/');

			/**
			 * Click on the last option and check its last-12-months.
			 */
			cy.get('@block').find('.ep-facet-date-option label').last().click();
			cy.get('@block').find('.wp-element-button').click();

			cy.url().should('include', 'ep_date_filter=last-12-months');
			cy.get('@block')
				.find('.ep-facet-date-option')
				.last()
				.find('input')
				.should('be.checked');
		});
	});
});
