describe('Related Posts Feature', () => {
	function openWidgetsPage() {
		cy.visitAdminPage('widgets.php');
		cy.get('body').then(($body) => {
			const $button = $body.find(
				'.edit-widgets-welcome-guide .components-modal__header button',
			);
			if ($button.is(':visible')) {
				$button.click();
			}
		});
	}

	it('Can see the widget in the Dashboard', () => {
		cy.login();

		// Disable the feature.
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-related_posts .settings-button').click();
		cy.get('.ep-feature-related_posts [name="settings[active]"][value="0"]').click();
		cy.get('.ep-feature-related_posts .button-primary').click();

		openWidgetsPage();

		cy.get('.edit-widgets-header-toolbar__inserter-toggle').click();
		cy.get('.components-search-control__input').clearThenType('ElasticPress Related Posts');

		cy.get('.block-editor-inserter__no-results').should('exist');

		// Re-enable the feature.
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-related_posts .settings-button').click();
		cy.get('.ep-feature-related_posts [name="settings[active]"][value="1"]').click();
		cy.get('.ep-feature-related_posts .button-primary').click();

		openWidgetsPage();

		cy.get('.edit-widgets-header-toolbar__inserter-toggle').click();
		cy.get('.components-search-control__input').clearThenType('ElasticPress Related Posts');

		cy.get('.block-editor-inserter__no-results').should('not.exist');
		cy.get('.block-editor-block-types-list').should(($widgetsList) => {
			expect($widgetsList).to.contain.text('ElasticPress - Related Posts');
			expect($widgetsList).to.contain.text('Related Posts (ElasticPress)');
		});
	});

	it('Can instantiate and use the widget', () => {
		cy.login();

		cy.maybeEnableFeature('related_posts');

		openWidgetsPage();

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
