describe('Related Posts Feature', () => {
	/**
	 * Ensure the feature is active and ensure Classic Widgets is installed
	 * before running tests.
	 */
	before(() => {
		cy.maybeEnableFeature('related_posts');
	});

	/**
	 * Delete all widgets, ensure Classic Widgets is deactivated, and remove
	 * test posts before each test.
	 */
	beforeEach(() => {
		cy.emptyWidgets();
		cy.wpCliEval(
			`
			WP_CLI::runcommand( 'plugin deactivate classic-widgets', [ 'return' => true ] );

			$posts_ids = WP_CLI::runcommand( 'post list --s="Test related posts" --ep_integrate=false --format=ids', [ 'return' => true ] );
			if ( $posts_ids ) {
				WP_CLI::runcommand( "post delete {$posts_ids} --force" );
			}
			`,
		);
	});

	/**
	 * Test that the Related Posts block is functional.
	 */
	it('Can insert, configure, and use the Related Posts block', () => {
		/**
		 * Create some posts that will be related.
		 */
		cy.wpCliEval(
			`
			for ( $i = 1; $i <= 4; $i++ ) {
				wp_insert_post(
					[
						'post_title'   => "Test related posts block #{$i}",
						'post_content' => 'Inceptos tristique class ac eleifend leo',
						'post_status'  => 'publish',
					]
				);
			}
			`,
		);

		cy.publishPost({
			title: `Test related posts block #5`,
			content: 'Inceptos tristique class ac eleifend leo.',
		});

		/**
		 * On the last post insert a Related Posts block.
		 */
		cy.openBlockInserter();
		cy.getBlocksList().should('contain.text', 'Related Posts');
		cy.insertBlock('Related Posts');

		/**
		 * Verify that the block is inserted into the editor, and contains the
		 * expected content.
		 */
		cy.get('.wp-block.wp-block-elasticpress-related-posts').first().as('block');
		cy.get('@block')
			.find('li')
			.should('contain', 'Test related posts block #')
			.should('have.length', 5);

		/**
		 * Set the block to display 2 related posts.
		 */
		cy.get('@block').click();
		cy.openBlockSettingsSidebar();
		cy.get('input[type="number"][aria-label="Number of items"]').clearThenType('2');

		/**
		 * Verify the block has the expected output in the editor based on the
		 * block's settings.
		 */
		cy.get('@block')
			.find('li')
			.should('contain', 'Test related posts block #')
			.should('have.length', 2);

		/**
		 * Test that the block supports changing styles.
		 */
		cy.get('@block').supportsBlockColors(true);
		cy.get('@block').supportsBlockTypography(true);
		cy.get('@block').supportsBlockDimensions(true);

		/**
		 * Clicking a related post link in the editor shouldn't change the URL.
		 *
		 * By default, Cypress does not allow a click on an element with `pointer-events: none`,
		 * hence why `{ force: true }`
		 */
		cy.get('@block').find('a').first().click({ force: true });
		cy.url().should('include', 'wp-admin/post.php');

		/**
		 * Update the post and visit the front end.
		 */
		cy.get('.editor-post-publish-button__button').click();
		cy.get('.components-snackbar__action').click();

		/**
		 * Verify the block has the expected output on the front-end based on the
		 * block's settings.
		 */
		cy.get('.wp-block-elasticpress-related-posts').first().as('block');
		cy.get('@block')
			.find('li')
			.should('contain', 'Test related posts block #')
			.should('have.length', 2);

		/**
		 * Verify that the block supports changing styles.
		 */
		cy.get('@block').supportsBlockColors();
		cy.get('@block').supportsBlockTypography();
		cy.get('@block').supportsBlockDimensions();
	});

	/**
	 * Test that the Related Posts widget is functional and can be transformed
	 * into the Related Posts block.
	 */
	it('Can insert, configure, use, and transform the legacy Related Posts widget', () => {
		/**
		 * Add the legacy widget.
		 */
		cy.activatePlugin('classic-widgets', 'wpCli');
		cy.createClassicWidget('ep-related-posts', [
			{
				name: 'title',
				value: 'My related posts widget',
			},
			{
				name: 'num_posts',
				value: '2',
			},
		]);

		/**
		 * Create some posts that will be related and view the last post.
		 */
		cy.wpCliEval(
			`
			for ( $i = 1; $i <= 2; $i++ ) {
				wp_insert_post(
					[
						'post_title'   => "Test related posts widget #{$i}",
						'post_content' => 'Inceptos tristique class ac eleifend leo',
						'post_status'  => 'publish',
					]
				);
			}
			`,
		);

		cy.publishPost(
			{
				title: `Test related posts widget #3`,
				content: 'Inceptos tristique class ac eleifend leo.',
			},
			true,
		);

		/**
		 * Verify the widget has the expected output on the front-end based on
		 * the widget's settings.
		 */
		cy.get(`[id^="ep-related-posts"]`).first().as('widget');
		cy.get('@widget')
			.should('contain.text', 'My related posts widget')
			.find('li')
			.should('contain', 'Test related posts widget #')
			.should('have.length', 2);

		/**
		 * Visit the block-based Widgets screen.
		 */
		cy.deactivatePlugin('classic-widgets', 'wpCli');
		cy.openWidgetsPage();

		/**
		 * Check that the widget is inserted in to the editor as a Legacy
		 * Widget block.
		 */
		cy.get(`.wp-block-legacy-widget`).first().as('widget');
		cy.get('@widget').should('contain.text', 'ElasticPress - Related Posts');

		/**
		 * Transform the legacywidget into the block.
		 */
		cy.get('@widget').click();
		cy.get('.block-editor-block-switcher button').click();
		cy.get(
			'.block-editor-block-switcher__popover .editor-block-list-item-elasticpress-related-posts',
		).click();

		/**
		 * Check that the widget has been transformed into the correct blocks.
		 */
		cy.get(`.wp-block-heading`).contains('My related posts widget').should('exist');
		cy.get('.wp-block-elasticpress-related-posts').should('exist').first().as('block');

		/**
		 * Check that the block's settings match the widget's.
		 */
		cy.get('@block').click();
		cy.get('.edit-widgets-header__actions button[aria-label="Settings"]').then(($button) => {
			if (!$button.attr('aria-expanded')) {
				$button.trigger('click');
			}
		});
		cy.get('input[type="number"][aria-label="Number of items"]').should('have.value', '2');
	});
});
