/* eslint-disable cypress/no-unnecessary-waiting */
/* global isEpIo */

// eslint-disable-next-line jest/valid-describe-callback
describe('Autosuggest V2 Feature', { tags: '@slow' }, () => {
	before(() => {
		cy.deactivatePlugin('autosuggestv2-proxy-plugin', 'wpCli');
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

	describe('Autosuggest V2 enabled', () => {
		before(() => {
			if (!isEpIo) {
				cy.activatePlugin('autosuggestv2-proxy-plugin', 'wpCli');
			}
			cy.maybeEnableFeature('autosuggest-v2');
			cy.wpCli('wp elasticpress sync');
		});

		after(() => {
			cy.maybeDisableFeature('autosuggest-v2');
			if (!isEpIo) {
				cy.deactivatePlugin('autosuggestv2-proxy-plugin', 'wpCli');
			}
		});

		it('Reports as enabled', () => {
			/** Visit the feature */
			cy.visitAdminPage('admin.php?page=elasticpress');
			cy.contains('button', 'Live Search').click();
			cy.contains('button', 'Autosuggest V2').click();

			if (!isEpIo) {
				cy.get('.components-notice').should(
					'contain.text',
					'You are using a custom proxy.',
				);
			}

			cy.get('.components-toggle-control input:checked').should('exist');
			cy.get('.components-toggle-control input:not(:checked)').should('not.exist');
		});

		/**
		 * Test that the feature works after being activated
		 */
		it('Displays autosuggestions after being enabled', () => {
			cy.intercept({ url: /search=[^&]*/, method: 'GET' }).as('apiRequest');

			cy.visit('/');

			cy.get('.wp-block-search__input').type('Markup: HTML Tags and Formatting');

			cy.wait('@apiRequest');

			cy.get('.ep-autosuggest').should(($autosuggestList) => {
				// eslint-disable-next-line no-unused-expressions
				expect($autosuggestList).to.be.visible;
				expect($autosuggestList[0].innerText).to.contains(
					'Markup: HTML Tags and Formatting',
				);
			});
		});
	});

	describe('Autosuggest V2 Disabled', () => {
		before(() => {
			// This block already ensures its desired state
			cy.maybeDisableFeature('autosuggest-v2');
			cy.deactivatePlugin('autosuggestv2-proxy-plugin', 'wpCli');
		});
		it('Can be disabled', () => {
			cy.visitAdminPage('admin.php?page=elasticpress');
			cy.contains('button', 'Live Search').click();
			cy.contains('button', 'Autosuggest V2').click();

			cy.get('.components-toggle-control input:checked').should('not.exist');
			cy.get('.components-toggle-control input:not(:checked)').should('exist');

			cy.contains('button', 'Save changes').click();
		});
	});
});
