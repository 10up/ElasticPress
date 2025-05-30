/* eslint-disable cypress/no-unnecessary-waiting */
/* global isEpIo */

// eslint-disable-next-line jest/valid-describe-callback
describe('Autosuggest V2 Feature', () => {
	before(() => {
		cy.deactivatePlugin('autosuggestv2-proxy-plugin', 'wpCli');
		cy.deactivatePlugin('customize-autosuggest-v2', 'wpCli');
		cy.maybeDisableFeature('autosuggest-v2');
		cy.maybeDisableFeature('autosuggest');
	});
	/**
	 * Test that the feature cannot be activated when not in ElasticPress.io nor using a custom PHP proxy.
	 */
	it("Can't activate the feature if not in ElasticPress.io nor using a custom PHP proxy", () => {
		if (isEpIo) {
			return;
		}

		cy.visitAdminPage('admin.php?page=elasticpress');

		cy.contains('button', 'Live Search').click();
		cy.contains('button', 'Autosuggest V2').click();
		cy.contains('.components-notice', 'To use this feature you need').should('exist');
		cy.get('.components-form-toggle__input').should('be.disabled');
	});

	/**
	 * Test that the feature works after being activated
	 */
	it('Displays autosuggestions after being enabled', () => {
		if (!isEpIo) {
			cy.activatePlugin('autosuggestv2-proxy-plugin', 'wpCli');
		}

		cy.visitAdminPage('admin.php?page=elasticpress');

		cy.contains('button', 'Live Search').click();
		cy.contains('button', 'Autosuggest V2').click();

		if (!isEpIo) {
			cy.get('.components-notice').should('contain.text', 'You are using a custom proxy.');
		}

		cy.maybeEnableFeature('autosuggest-v2');

		cy.visitAdminPage('admin.php?page=elasticpress-sync');
		cy.contains('.components-button', 'Start sync').click();

		cy.on('window:confirm', () => true);

		cy.get('.ep-sync-progress strong', {
			timeout: Cypress.config('elasticPressIndexTimeout'),
		}).should('contain.text', 'Sync complete');

		cy.visit('/');

		cy.get('.wp-block-search__input').type('Markup: HTML Tags and Formatting');

		cy.wait(500);

		cy.get('.ep-autosuggest').should(($autosuggestList) => {
			// eslint-disable-next-line no-unused-expressions
			expect($autosuggestList).to.be.visible;
			expect($autosuggestList[0].innerText).to.contains('Markup: HTML Tags and Formatting');
		});
	});

	/**
	 * Test that the feature can be modified via filters in a custom plugin
	 */
	it('Can be customized using filters', () => {
		cy.activatePlugin('customize-autosuggest-v2', 'wpCli');
		cy.visit('/');

		cy.get('.wp-block-search__input').type('Markup: HTML Tags and Formatting');

		cy.wait(500);

		cy.get('.ep-autosuggest').should(($autosuggestList) => {
			// eslint-disable-next-line no-unused-expressions
			expect($autosuggestList).to.be.visible;
			expect($autosuggestList[0].innerText).to.contains('Custom Search Results');
			expect($autosuggestList[0].innerText).to.contains('Type:');
			expect($autosuggestList[0].innerText).to.contains('Markup: HTML Tags and Formatting');
		});
	});

	after(() => {
		cy.maybeDisableFeature('autosuggest-v2');
		cy.deactivatePlugin('autosuggestv2-proxy-plugin', 'wpCli');
		cy.deactivatePlugin('customize-autosuggest-v2', 'wpCli');
	});
});
