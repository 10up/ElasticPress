describe('Facets Feature', () => {
	/**
	 * Create a facets widget.
	 *
	 * @param {string} title The widget title
	 * @param {string} category The category slug.
	 */
	function createWidget(title, category) {
		cy.intercept('/wp-json/wp/v2/widget-types/*/encode*').as('legacyWidgets');
		cy.openWidgetsPage();

		cy.get('.edit-widgets-header-toolbar__inserter-toggle').click();
		cy.get('.block-editor-inserter__panel-content [class*="legacy-widget/ep-facet"]').click({
			force: true,
		});
		cy.wait('@legacyWidgets');
		// eslint-disable-next-line cypress/no-unnecessary-waiting -- JS processing
		cy.wait(100);

		cy.get('.is-opened .widget-ep-facet')
			.last()
			.within(() => {
				cy.get('input[name^="widget-ep-facet"][name$="[title]"]').clearThenType(
					title,
					true,
				);
				cy.get('select[name^="widget-ep-facet"][name$="[facet]"]').select(category);
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
	}

	before(() => {
		cy.maybeEnableFeature('facets');

		cy.wpCli('widget reset --all');

		// Initial widget that will be used for all tests.
		createWidget('Facet (categories)', 'category');
	});

	it('Can see the widget in the frontend', () => {
		cy.visit('/');

		// Check if the widget is visible.
		cy.get('.widget_ep-facet').should('be.visible');
		cy.contains('.widget-title', 'Facet (categories)').should('be.visible');

		// Check if the widget search works. Additionally, checks a hyphenated slug category.
		cy.get('.widget_ep-facet .facet-search').clearThenType('Parent C');
		cy.contains('.widget_ep-facet .term', 'Parent Category').should('be.visible');
		cy.contains('.widget_ep-facet .term', 'Child Category').should('not.be.visible');
	});

	it('Can use widgets independently', () => {
		// Create a second widget, so we can test both working together.
		createWidget('Facet (Tags)', 'post_tag');

		cy.visit('/');

		// We should have two widgets now, one of them the created above.
		cy.get('.widget_ep-facet').should('have.length', 2);
		cy.contains('.widget-title', 'Facet (Tags)').should('be.visible');
	});

	/**
	 * @todo Can search in widgets independently.
	 * @todo Can reset pagination if clicked.
	 */
});
