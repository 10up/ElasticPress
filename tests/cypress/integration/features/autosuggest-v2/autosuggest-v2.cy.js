describe('ElasticPress Autosuggest V2', () => {
	let wpEnvDir;
	let themesDir;
	before(() => {
		cy.maybeDisableFeature('instant-results');
		cy.maybeDisableFeature('autosuggest');
		cy.maybeEnableFeature('autosuggest-v2');

		const wpEnvPath = process.env.GITHUB_ACTIONS
			? `${process.env.GITHUB_WORKSPACE}/.wp-env`
			: '~/.wp-env';

		// Find WP environment directory
		cy.exec(`cd ${wpEnvPath} && ls -t | grep -E "^[0-9a-f]{32}$" | head -n 1`).then(
			(result) => {
				wpEnvDir = result.stdout.trim();
				themesDir = `~/.wp-env/${wpEnvDir}/tests-WordPress/wp-content/themes`;

				cy.login();

				// Clone the child theme directory
				cy.exec(
					`cp -r ./tests/cypress/integration/features/autosuggest-v2/tests-child-theme ${themesDir}/child-theme`,
				).then(() => {
					// First check and perform network activation
					cy.visit('/wp-admin/network/themes.php');

					// Break up the chain - check if theme exists
					cy.get('body').should('exist');
					cy.get('.theme-title:contains("Twenty Twenty-One Child")')
						.first()
						.as('childTheme');

					// Check if theme needs activation
					cy.get('@childTheme').then(($theme) => {
						if ($theme.find('.enable').length > 0) {
							// Store reference to the enable button rather than finding it in the chain
							cy.get('.theme-title:contains("Twenty Twenty-One Child")')
								.first()
								.find('.enable .edit')
								.as('enableButton');

							cy.get('@enableButton').click();

							// Wait explicitly for page reload
							// eslint-disable-next-line cypress/no-unnecessary-waiting
							cy.wait(2000);
							cy.reload();
							cy.get('body').should('be.visible');
						} else {
							// Check with a separate command
							cy.get('.theme-title:contains("Twenty Twenty-One Child")')
								.first()
								.find('.disable')
								.should('exist');
						}
					});

					// Activate on main site if needed - also with broken up chains
					cy.visit('/wp-admin/themes.php');
					cy.get('body').should('exist');
					cy.get('.theme:contains("Twenty Twenty-One Child")').as('childThemeMain');

					cy.get('@childThemeMain').then(($theme) => {
						if ($theme.find('.activate').length > 0) {
							cy.get('.theme:contains("Twenty Twenty-One Child")')
								.find('.activate')
								.as('activateButton');

							cy.get('@activateButton').click();
							// eslint-disable-next-line cypress/no-unnecessary-waiting
							cy.wait(3000);
						} else {
							cy.log('Child Theme already active on main site');
						}
					});
				});
			},
		);
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

	// Add cleanup after test is complete
	after(() => {
		// Only execute if we have the theme directory
		if (wpEnvDir && themesDir) {
			// Switch to a different theme first to ensure the child theme can be removed
			cy.visit('/wp-admin/themes.php');

			// Break up the chain and handle the theme activation more safely
			cy.get('body').then(() => {
				// Check if parent theme exists and is not active
				cy.get('.theme:contains("Twenty Twenty-One"):not(:contains("Child"))')
					.first()
					.then(($parentTheme) => {
						// Check if we need to activate it
						if ($parentTheme.find('.activate').length > 0) {
							// Use cy.get again to ensure we have a fresh reference
							cy.get('.theme:contains("Twenty Twenty-One"):not(:contains("Child"))')
								.first()
								.find('.activate')
								.click();

							// Wait for page to reload after theme activation
							cy.reload();
							cy.get('body').should('exist');
						}
					});
			});

			// After theme switching is complete, remove the child theme directory
			cy.exec(`rm -rf ${themesDir}/child-theme`).then(() => {
				cy.log('Child theme removed successfully');
			});
		}
	});
});
