describe('Related Posts widget', () => {
	const blockId = 'elasticpress/related-posts';
	const blockName = 'Related Posts (ElasticPress)';
	const widgetId = 'ep-related-posts';
	const widgetName = 'ElasticPress - Related Posts';
	const widgetTitle = 'My related posts';
	const widgetNumPosts = '3';

	/**
	 * Add a copy of the widget for all tests.
	 */
	before(() => {
		cy.wpCli('widget reset --all');
		cy.wpCli('wp plugin install classic-widgets --activate');

		cy.maybeEnableFeature('related_posts');

		cy.visitAdminPage('widgets.php');
		cy.intercept('/wp-admin/admin-ajax.php').as('adminAjax');

		/**
		 * Find and add the widget.
		 */
		cy.get(`#widget-list [id*="${widgetId}"]`)
			.click()
			.within(() => {
				cy.get('.widgets-chooser-add').click();
			});

		cy.wait('@adminAjax');

		/**
		 * Set the widget values and save.
		 */
		cy.get(`#widgets-right [id*="${widgetId}"]`).within(() => {
			cy.get('input[name$="[title]"]').clearThenType(widgetTitle);
			cy.get('input[name$="[num_posts]"]').clearThenType(widgetNumPosts);
			cy.get('input[type="submit"]').click();
		});

		cy.wait('@adminAjax');
	});

	it('Can see the widget on the front end', () => {
		cy.visit('/');

		/**
		 * The widget should be visible and contain the correct title.
		 */
		cy.get(`[id^="${widgetId}"]`).should('be.visible').should('contain.text', widgetTitle);
	});

	it('Can transform legacy widget into block', () => {
		cy.wpCli('wp plugin deactivate classic-widgets');

		cy.visitAdminPage('widgets.php');

		/**
		 * Find the legacy widget block, select it, and get its values.
		 */
		cy.get('.wp-block')
			.contains(`input[name="id_base"][value="${widgetId}"]`)
			.click()
			.within(() => {
				cy.get('input[name$="[title]"]').invoke('val').as('@title');
				cy.get('input[name$="[num_posts]"]').invoke('val').as('@numPosts');
			});

		/**
		 * Transform the widget into to the block.
		 */
		cy.get('.block-editor-block-switcher button').click();
		cy.get('.block-editor-block-switcher__popover button').contains(blockName).click();

		/**
		 * Check that the widget has been transformed into the correct block
		 * and that the title was transformed into a heading block.
		 */
		cy.focused()
			.invoke('attr', 'data-type')
			.should('eq', blockId)
			.prev()
			.invoke('attr', 'data-type')
			.should('eq', 'core/heading')
			.invoke('text')
			.should('eq', widgetTitle);

		/**
		 * Check that the number of items matches what was set on the widget.
		 */
		cy.get('.block-editor-block-inspector input[aria-label="Number of items"]')
			.invoke('val')
			.should('eq', widgetNumPosts);
	});

	it('Related Posts legacy widget is not available as a block', () => {
		cy.visitAdminPage('widgets.php');
		cy.getBlocksList().should('not.contain.text', widgetName);
	});
});
