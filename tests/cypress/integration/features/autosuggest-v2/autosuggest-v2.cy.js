// autosuggest-v2.cy.js

describe('ElasticPress Autosuggest V2', () => {
	before(() => {
		process.env.NODE_TLS_REJECT_UNAUTHORIZED = '1';
		cy.maybeDisableFeature('instant-results');
		cy.maybeDisableFeature('autosuggest');
		cy.maybeEnableFeature('autosuggest-v2');

		// Find WP environment directory
		cy.exec('cd ~/.wp-env && ls -t | grep -E "^[0-9a-f]{32}$" | head -n 1').then((result) => {
			const wpEnvDir = result.stdout.trim();
			const themesDir = `~/.wp-env/${wpEnvDir}/tests-WordPress/wp-content/themes`;

			cy.login();

			// Clone the child theme directory
			cy.exec(
				`cp -r ./tests/cypress/integration/features/autosuggest-v2/tests-child-theme ${themesDir}/child-theme`,
			).then(() => {
				// First check and perform network activation
				cy.visit('/wp-admin/network/themes.php');

				// Check if the theme is already network-activated
				cy.get('.theme-title:contains("Twenty Twenty-One Child")').then(($theme) => {
					// Check if the theme has .enable child element
					if ($theme.find('.enable').length > 0) {
						// Find and click the edit link under enable
						cy.wrap($theme).find('.enable .edit').click();
					} else {
						// Confirm it has a disable element instead
						cy.wrap($theme).find('.disable').should('exist');
					}
				});

				// Activate on main site if needed
				cy.visit('/wp-admin/themes.php');
				cy.get('.theme:contains("Twenty Twenty-One Child")').then(($theme) => {
					// Check if theme needs activation without clicking first
					if ($theme.find('.activate').length > 0) {
						// Theme not activated, activate it
						cy.wrap($theme).find('.activate').click();
						// eslint-disable-next-line cypress/no-unnecessary-waiting
						cy.wait(2000);
					} else {
						// Already active, no need to do anything
						cy.log('Child Theme already active on main site');
					}
				});
			});
		});
	});

	it('Should display results, and display the hooked customizations', () => {
		// Visit the site
		cy.visit('/');

		// Check if the autosuggest element is visible
		cy.get('.wp-block-search__input').type('blog');
		cy.get('.ep-autosuggest').should('be.visible');
		cy.get('.ep-autosuggest .custom-header').should('be.visible');
		cy.get('.ep-autosuggest .suggestion-group').should('be.visible');
	});
});
