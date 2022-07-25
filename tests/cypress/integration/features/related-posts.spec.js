describe('Related Posts Feature', () => {

	/**
	 * Test that the Related Posts block is functional.
	 */
	it('Related Posts block is functional', () => {
		/**
		 * Enable the feature.
		 */
		cy.maybeEnableFeature('related_posts');

		/**
		 * Create some posts that will be related.
		 */
		for (let i = 0; i < 4; i++) {
			cy.publishPost({
				title: `Test related posts block #${i + 1}`,
				content: 'Inceptos tristique class ac eleifend leo.',
			});
		}

		/**
		 * On the last post insert a Related Posts block.
		 */
		cy.openBlockInserter();
		cy.getBlocksList().should('contain.text', 'Related Posts (ElasticPress)');
		cy.insertBlock('Related Posts (ElasticPress)');

		/**
		 * Check that the block is inserted into the editor, and contains the
		 * expected content.
		 */
		cy.get(`.block-editor-block-list__layout .wp-block-elasticpress-related-posts`)
			.should('exist')
			.should('contain.text', 'Test related posts block #1')
			.click();

		/**
		 * Check that the number control works.
		 */
		cy.openSettingsSidebar();
		cy.get('.edit-post-sidebar__panel-tab').contains('Block').click();
		cy.get('input[type="number"][aria-label="Number of items"]').clearThenType('2');
		cy.get(`.wp-block-elasticpress-related-posts li`).should('have.length', 2);

		/**
		 * Check that the block is rendered on the front-end, and contains
		 * the expected content.
		 */
		cy.get('.editor-post-publish-button__button').click();
		cy.get('.components-snackbar__action').click();
		cy.get('.wp-block-elasticpress-related-posts li')
			.should('exist')
			.should('contain', 'Test related posts block #')
			.should('have.length', 2);
	});

	/**
	 * Test that the Related Posts widget is functional and can be transformed
	 * into the Related Posts block.
	 */
	it('Related Posts widget is functional', () => {
		/**
		 * Enable the feature.
		 */
		cy.maybeEnableFeature('related_posts');

		/**
		 * Clear any existing widgets.
		 */
		cy.wpCli('widget reset --all');

		/**
		 * Visit the classic Widgets screen.
		 */
		cy.wpCli('wp plugin install classic-widgets --activate');
		cy.openWidgetsPage();

		/**
		 * Find and add the widget to the first widget area.
		 */
		cy.intercept('/wp-admin/admin-ajax.php').as('adminAjax');

		cy.get(`#widget-list [id$="ep-related-posts-__i__"]`)
			.click('top')
			.within(() => {
				cy.get('.widgets-chooser-add').click();
			});

		cy.wait('@adminAjax');

		/**
		 * Add a title, set the post count, and save.
		 */
		cy.get(`#widgets-right [id*="ep-related-posts"]`).within(() => {
			cy.get('input[name$="[title]"]').clearThenType('My related posts');
			cy.get('input[name$="[num_posts]"]').clearThenType('2');
			cy.get('input[type="submit"]').click();
		});

		cy.wait('@adminAjax');

		/**
		 * Create some posts that will be related and view the last post.
		 */
		for (let i = 0; i < 4; i++) {
			const viewPost = i === 3;

			cy.publishPost(
				{
					title: `Test related posts widget #${i + 1}`,
					content: 'Inceptos tristique class ac eleifend leo.',
				},
				viewPost,
			);
		}

		/**
		 * When viewing the last post the widget should be visible and contain
		 * the correct title and number of posts.
		 */
		cy.get(`[id^="ep-related-posts"]`)
			.should('be.visible')
			.should('contain.text', 'My related posts')
			.within(() => {
				cy.get('li')
					.should('exist')
					.should('contain', 'Test related posts widget #')
					.should('have.length', 2);
			});

		/**
		 * Visit the block-based Widgets screen.
		 */
		cy.wpCli('wp plugin deactivate classic-widgets');
		cy.openWidgetsPage();

		/**
		 * Check that the widget is inserted in to the editor as a Legacy
		 * Widget block.
		 */
		cy.get(`.block-editor-block-list__layout .wp-block-legacy-widget`)
			.should('exist')
			.should('contain.text', 'ElasticPress - Related Posts')
			.first()
			.click();

		/**
		 * Transform the legacywidget into the block.
		 */
		cy.get('.block-editor-block-switcher button').click();
		cy.get(
			'.block-editor-block-switcher__popover .editor-block-list-item-elasticpress-related-posts',
		).click();

		/**
		 * Check that the widget has been transformed into the correct blocks
		 * and that their content matches the widget's settings.
		 */
		cy.get(`.block-editor-block-list__layout .wp-block-heading`)
			.should('exist')
			.should('contain.text', 'My related posts');

		cy.get(`.block-editor-block-list__layout .wp-block-elasticpress-related-posts li`)
			.should('exist')
			.should('contain.text', 'Test related posts widget #')
			.should('have.length', 2);

		cy.get('.edit-widgets-header__actions button').contains('Update').click();

		/**
		 * Create a new post and view it.
		 */
		cy.publishPost(
			{
				title: 'Test related posts widget #5',
				content: 'Inceptos tristique class ac eleifend leo.',
			},
			true,
		);

		/**
		 * Confirm that the transformed block is rendered on the front-end,
		 * and contains the expected content.
		 */
		cy.get('.wp-block-elasticpress-related-posts li')
			.should('exist')
			.should('contain', 'Test related posts widget #')
			.should('have.length', 2);
	});
});
