describe('Related Posts Feature', () => {
	it('Block can be inserted', () => {
		/**
		 * Make sure the block isn't available if the feature is not active.
		 */
		cy.maybeDisableFeature('related_posts');
		cy.visitAdminPage('post-new.php');
		cy.closeWelcomeGuide();
		cy.openBlockInserter();
		cy.getBlocksList().should('not.contain.text', 'Related Posts (ElasticPress)');

		/**
		 * Create some posts that will be related.
		 */
		cy.maybeEnableFeature('related_posts');

		const postsToPublish = [
			{
				title: 'Test related posts block #1',
				content: 'Inceptos tristique class ac eleifend leo.',
			},
			{
				title: 'Test related posts block #2',
				content: 'Inceptos tristique class ac eleifend leo.',
			},
			{
				title: 'Test related posts block #3',
				content: 'Inceptos tristique class ac eleifend leo.',
			},
		];

		postsToPublish.forEach((postData) => {
			cy.publishPost(postData);
		});

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
		cy.openDocumentSettingsSidebar();
		cy.get('.edit-post-sidebar__panel-tab').contains('Block').click();
		cy.get('input[type="number"][aria-label="Number of items"]').clearThenType('2');
		cy.get(`.wp-block-elasticpress-related-posts li`).should('have.length', 2);

		/**
		 * Check that the block is rendered on the front-end, and contains
		 * the expected content.
		 */
		cy.updatePostAndView();
		cy.get('.wp-block-elasticpress-related-posts li')
			.should('exist')
			.should('contain', 'Test related posts block #1')
			.should('have.length', 2);
	});

	it('Can instantiate and use the widget', () => {
		cy.maybeEnableFeature('related_posts');

		cy.openWidgetsPage();

		cy.get('.edit-widgets-header-toolbar__inserter-toggle').click();
		cy.get(
			'.block-editor-inserter__panel-content [class*="legacy-widget/ep-related-posts"]',
		).click({
			force: true,
		});
		cy.get('input[name^="widget-ep-related-posts"][name$="[title]"]').clearThenType(
			'Related Posts',
		);

		/**
		 * Wait for WordPress to recognize the title typed.
		 *
		 * @todo investigate why this is needed.
		 */
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(2000);

		cy.get('.edit-widgets-header__actions .components-button.is-primary').click();
		cy.get('body').should('contain.text', 'Widgets saved.');

		const postsData = [
			{
				title: 'test related posts 1',
				content: 'findme test 1',
			},
			{
				title: 'test related posts 2',
				content: 'findme test 2',
			},
			{
				title: 'test related posts 3',
				content: 'findme test 3',
			},
		];

		postsData.forEach((postData) => {
			cy.publishPost(postData);
		});

		// Clicking on the "View Post" button.
		cy.get('.post-publish-panel__postpublish-buttons a.components-button.is-primary').click();
		cy.get('body').should('contain.text', 'Related Posts');
		cy.contains('a', 'test related posts 1').should('exist');
		cy.contains('a', 'test related posts 2').should('exist');
	});
});
