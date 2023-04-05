Cypress.Commands.add('openBlockSettingsSidebar', () => {
	cy.get('body').then(($el) => {
		if ($el.hasClass('widgets-php')) {
			cy.get('.edit-widgets-header__actions button[aria-label="Settings"]').click();
			cy.get('.edit-widgets-sidebar__panel-tab').contains('Block').click();
		} else {
			cy.get('.edit-post-header__settings button[aria-label="Settings"]').click();
			cy.get('.edit-post-sidebar__panel-tab').contains('Block').click();
		}
	});
});

Cypress.Commands.add('openBlockInserter', () => {
	cy.get('body').then(($body) => {
		// If already open, skip.
		if ($body.find('.edit-widgets-layout__inserter-panel-content').length > 0) {
			return;
		}
		if ($body.hasClass('widgets-php')) {
			cy.get('.edit-widgets-header-toolbar__inserter-toggle').click();
		} else {
			cy.get('.edit-post-header-toolbar__inserter-toggle').click();
		}
	});
});

Cypress.Commands.add('getBlocksList', () => {
	cy.get('.block-editor-inserter__block-list');
});

Cypress.Commands.add('insertBlock', (blockName) => {
	cy.get('.block-editor-inserter__search input[type="search"').clearThenType(blockName);
	cy.get('.block-editor-block-types-list__item').contains(blockName).click();
});
