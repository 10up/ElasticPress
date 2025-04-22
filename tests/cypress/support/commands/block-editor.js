/* global wpVersion */

import { getIframe } from '../functions/get-iframe';

Cypress.Commands.add('openBlockSettingsSidebar', () => {
	cy.get('body').then(($el) => {
		if ($el.hasClass('widgets-php')) {
			cy.get('.edit-widgets-header__actions button[aria-label="Settings"]').click();
			cy.get('.edit-widgets-sidebar__panel-tab,.edit-widgets-sidebar__panel-tabs button')
				.contains('Block')
				.click();
		} else {
			cy.get(
				`.edit-post-header__settings button[aria-label="Settings"],
				.editor-header__settings button[aria-label="Settings"]`,
			).click();
			cy.get(
				`.edit-post-sidebar__panel-tab,
				.edit-post-sidebar__panel-tabs button,
				.editor-sidebar__panel-tabs button:contains('Block')`,
			)
				.contains('Block')
				.click();
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
			cy.get(
				'.edit-post-header-toolbar__inserter-toggle,.editor-document-tools__inserter-toggle',
			).click();
		}
	});
});

Cypress.Commands.add('closeBlockInserter', () => {
	cy.get('body').then(($body) => {
		if ($body.hasClass('widgets-php')) {
			cy.get('.edit-widgets-header-toolbar__inserter-toggle').click();
		} else {
			cy.get(
				'.edit-post-header-toolbar__inserter-toggle,.editor-document-tools__inserter-toggle',
			).click();
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
		cy.get('.block-editor-block-inspector button[aria-label="Styles"]').click();
		cy.get('.block-editor-block-inspector button').contains('Background').click();

		cy.get(
			`.block-editor-color-gradient-control button[aria-label="Black"],
			.block-editor-color-gradient-control__panel button[aria-label="Color: Black"]`,
		).click();

		cy.get('.block-editor-block-inspector button[aria-label="Settings"]').click();
	}

	cy.wrap(subject).should('have.css', 'background-color', 'rgb(0, 0, 0)');
});

Cypress.Commands.add('supportsBlockTypography', { prevSubject: true }, (subject, isEdit) => {
	if (isEdit) {
		cy.get('.block-editor-block-inspector button[aria-label="Styles"]').click();
		cy.get('.block-editor-block-inspector button[aria-label="Typography options"]').click();

		cy.get('[aria-label="Typography options"] button, .popover-slot button')
			.contains(/Font size|Size/)
			.as('fontSizeButton');
		cy.get('@fontSizeButton').click();
		cy.get('@fontSizeButton').click();
		cy.get('@fontSizeButton').type('{esc}');

		cy.get('.block-editor-block-inspector fieldset.components-font-size-picker')
			.find('button[role="combobox"], button[aria-label="Font size"]')
			.click();

		cy.get(
			'.block-editor-block-inspector li[role="option"], .block-editor-block-inspector div[role="option"]',
		)
			.contains('Extra small')
			.click();

		if (wpVersion === '6.2') {
			cy.get('.block-editor-block-inspector button[aria-label="Typography options"]').click();
			cy.get('[aria-label="Typography options"] button, .popover-slot button')
				.contains('Line height')
				.as('fontSizeButton');
			cy.get('@fontSizeButton').click();
			cy.get('@fontSizeButton').click();
			cy.get('@fontSizeButton').type('{esc}');
			cy.get('.components-input-control__input[placeholder="1.5"]').clearThenType(2);
		} else {
			cy.get('.block-editor-line-height-control input').clearThenType(2);
			cy.get('.block-editor-block-inspector button[aria-label="Settings"]').click();
		}
	}

	cy.wrap(subject).should('have.css', 'font-size', '16px');
	cy.wrap(subject).should('have.css', 'line-height', '32px');
});

Cypress.Commands.add('supportsBlockDimensions', { prevSubject: true }, (subject, isEdit) => {
	if (isEdit) {
		cy.get('.block-editor-block-inspector button[aria-label="Styles"]').click();
		cy.get('.block-editor-block-inspector button[aria-label="Dimensions options"]').click();

		cy.get('.dimensions-block-support-panel').as('dimensionsPanel');

		cy.get('[aria-label="Dimensions options"] button, .popover-slot button')
			.contains('Padding')
			.as('paddingButton');
		cy.get('@paddingButton').click();
		cy.get('@paddingButton').click();
		cy.get('@paddingButton').type('{esc}');

		if (wpVersion === '6.2') {
			cy.get('.components-button[aria-label="Unlink sides"]').click();

			const inputs = [
				{ label: 'Top padding', value: 10 },
				{ label: 'Right padding', value: 15 },
				{ label: 'Bottom padding', value: 10 },
				{ label: 'Left padding', value: 15 },
			];

			inputs.forEach(({ label, value }) => {
				cy.get('@dimensionsPanel')
					.find('button[aria-label="Set custom size"]')
					.first()
					.click({ force: true });
				cy.get('@dimensionsPanel')
					.contains('label', label)
					.closest('div')
					.find('input')
					.clearThenType(value);
			});

			cy.get('.dimensions-block-support-panel').click();
		} else {
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
	}

	cy.wrap(subject).should('have.css', 'padding', '10px 15px');
});

Cypress.Commands.add('getBlockEditor', () => {
	// Ensure the editor is loaded.
	cy.get('.edit-post-visual-editor').should('exist');

	return cy
		.get('body')
		.then(($body) => {
			if ($body.find('iframe[name="editor-canvas"]').length) {
				return getIframe('iframe[name="editor-canvas"]');
			}
			return $body;
		})
		.then(cy.wrap);
});
