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
	cy.get('.block-editor-block-types-list__item').contains(blockName).click({ force: true });
});

Cypress.Commands.add('supportsBlockColors', { prevSubject: true }, (subject, isEdit) => {
	if (isEdit) {
		cy.get('.block-editor-block-inspector').as('blockInspector');

		cy.get('.block-editor-block-inspector button[aria-label="Styles"]').click();
		cy.get('.block-editor-block-inspector button').contains('Background').click();

		cy.get('.popover-slot button[aria-label="Color: Black"').click();

		cy.get('.block-editor-block-inspector button[aria-label="Settings"]').click();
	}

	cy.wrap(subject).should('have.css', 'background-color', 'rgb(0, 0, 0)');
});

Cypress.Commands.add('supportsBlockTypography', { prevSubject: true }, (subject, isEdit) => {
	if (isEdit) {
		cy.get('.block-editor-block-inspector button[aria-label="Styles"]').click();
		cy.get('.block-editor-block-inspector button[aria-label="Typography options"]').click();

		cy.get('.popover-slot button').contains('Font size').click();
		cy.get('.popover-slot button').contains('Font size').click().type('{esc}');

		cy.get('.block-editor-block-inspector button[aria-label="Font size"]').click();
		cy.get('.block-editor-block-inspector li[role="option"]').contains('Extra small').click();

		cy.get('.block-editor-line-height-control input').clearThenType(2);

		cy.get('.block-editor-block-inspector button[aria-label="Settings"]').click();
	}

	cy.wrap(subject).should('have.css', 'font-size', '16px');
	cy.wrap(subject).should('have.css', 'line-height', '32px');
});

Cypress.Commands.add('supportsBlockDimensions', { prevSubject: true }, (subject, isEdit) => {
	if (isEdit) {
		cy.get('.block-editor-block-inspector button[aria-label="Styles"]').click();
		cy.get('.block-editor-block-inspector button[aria-label="Dimensions options"]').click();

		cy.get('.dimensions-block-support-panel').as('dimensionsPanel');

		cy.get('.popover-slot button').contains('Padding').click().type('{esc}');

		cy.get('@dimensionsPanel')
			.find('.component-spacing-sizes-control, .spacing-sizes-control__wrapper')
			.first()
			.as('verticalInputsWrapper');

		cy.get('@verticalInputsWrapper')
			.find('button[aria-label="Set custom size"]')
			.first()
			.click();
		cy.get('@verticalInputsWrapper').find('input[type="number"]').clearThenType('10');

		cy.get('@dimensionsPanel')
			.find('.component-spacing-sizes-control, .spacing-sizes-control__wrapper')
			.eq(1)
			.as('horizontalInputsWrapper');

		cy.get('@horizontalInputsWrapper')
			.find('button[aria-label="Set custom size"]')
			.first()
			.click();
		cy.get('@horizontalInputsWrapper').find('input[type="number"]').clearThenType('15');

		cy.get('.block-editor-block-inspector button[aria-label="Settings"]').click();
	}

	cy.wrap(subject).should('have.css', 'padding', '10px 15px');
});
