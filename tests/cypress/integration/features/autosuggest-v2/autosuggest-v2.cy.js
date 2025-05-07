describe('ElasticPress Autosuggest V2', () => {
	let wpEnvDir;
	let themesDir;

	before(() => {
		cy.maybeDisableFeature('instant-results');
		cy.maybeDisableFeature('autosuggest');
		cy.maybeEnableFeature('autosuggest-v2');
		// Handle site activation
		const activateThemeOnSite = () => {
			cy.visit('/wp-admin/themes.php');
			cy.get('body').should('exist');

			// Check if theme exists
			cy.get('body').then(($body) => {
				if ($body.find('.theme:contains("Twenty Twenty-One Child")').length) {
					cy.get('.theme:contains("Twenty Twenty-One Child")').as('childThemeMain');

					cy.get('@childThemeMain').then(($theme) => {
						if ($theme.find('.activate').length > 0) {
							cy.get('.theme:contains("Twenty Twenty-One Child")')
								.find('.activate')
								.click();
							// eslint-disable-next-line cypress/no-unnecessary-waiting
							cy.wait(3000);
						} else {
							cy.log('Child Theme already active on main site');
						}
					});
				} else {
					cy.log('Warning: Child theme not found in site themes list');
				}
			});
		};

		// Handle network activation
		const activateThemeOnNetwork = () => {
			cy.visit('/wp-admin/network/themes.php');
			cy.get('body').should('exist');

			// Check if theme exists
			cy.get('body').then(($body) => {
				if ($body.find('.theme-title:contains("Twenty Twenty-One Child")').length) {
					cy.get('.theme-title:contains("Twenty Twenty-One Child")')
						.first()
						.as('childTheme');

					// Check if theme needs activation
					cy.get('@childTheme').then(($theme) => {
						if ($theme.find('.enable').length > 0) {
							cy.get('.theme-title:contains("Twenty Twenty-One Child")')
								.first()
								.find('.enable .edit')
								.click();

							// Wait for page reload
							// eslint-disable-next-line cypress/no-unnecessary-waiting
							cy.wait(2000);
							cy.reload();
							cy.get('body').should('be.visible');
						} else {
							// Already enabled on network
							cy.log('Child Theme already enabled on network');
						}
						// Continue with site activation
						activateThemeOnSite();
					});
				} else {
					cy.log('Warning: Child theme not found in network themes list');
					activateThemeOnSite(); // Try to continue anyway
				}
			});
		};
		// Setup and activate child theme
		const setupChildTheme = () => {
			cy.login();

			// Clone the child theme directory with error handling
			cy.exec(
				`cp -r ./tests/cypress/integration/features/autosuggest-v2/tests-child-theme ${themesDir}/child-theme`,
				{ failOnNonZeroExit: false },
			).then((result) => {
				if (result.code !== 0) {
					cy.log(`Warning: Failed to copy child theme: ${result.stderr}`);
					// Try a different approach - maybe the path just needs to be relative to the WP install
					cy.exec(
						'cp -r ./tests/cypress/integration/features/autosuggest-v2/tests-child-theme wp-content/themes/child-theme',
						{ failOnNonZeroExit: false },
					).then((secondAttempt) => {
						if (secondAttempt.code !== 0) {
							cy.log(
								`Warning: Second attempt to copy child theme failed: ${secondAttempt.stderr}`,
							);
						} else {
							cy.log('Second attempt to copy child theme succeeded');
						}
					});
				}

				// Network activate the theme
				activateThemeOnNetwork();
			});
		};
		// Fallback to relative path as last resort
		const useRelativePath = () => {
			// Use relative path as last resort
			themesDir = './wp-content/themes';
			cy.log(`Using relative path as fallback: ${themesDir}`);
			return setupChildTheme();
		};
		// Function to find theme directory with simplified GitHub detection
		const findThemesDirectory = () => {
			// First attempt: Local development with .wp-env
			cy.exec('cd ~/.wp-env && ls -t | grep -E "^[0-9a-f]{32}$" | head -n 1', {
				failOnNonZeroExit: false,
				// eslint-disable-next-line consistent-return
			}).then((result) => {
				if (result.code === 0 && result.stdout.trim() !== '') {
					// Local approach succeeded
					wpEnvDir = result.stdout.trim();
					themesDir = `~/.wp-env/${wpEnvDir}/tests-WordPress/wp-content/themes`;
					cy.log(`Found WordPress themes directory at: ${themesDir}`);
					return setupChildTheme();
				}

				// Local approach failed, check if we're in GitHub Actions
				cy.exec('test -d "/home/runner/work" && echo "exists" || echo "not found"', {
					failOnNonZeroExit: false,
				}).then((actionResult) => {
					if (actionResult.stdout.includes('exists')) {
						// We're in GitHub Actions environment, use the known path
						themesDir = '/home/runner/work/ElasticPress/ElasticPress/wp-content/themes';
						cy.log(`Using GitHub runner path: ${themesDir}`);
						return setupChildTheme();
					}
					// Not in GitHub Actions, use relative path as fallback
					return useRelativePath();
				});
			});
		};

		// Start the process
		findThemesDirectory();
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
		// Try to switch back to parent theme
		cy.visit('/wp-admin/themes.php');
		cy.get('body').then(($body) => {
			if ($body.find('.theme:contains("Twenty Twenty-One"):not(:contains("Child"))').length) {
				cy.get('.theme:contains("Twenty Twenty-One"):not(:contains("Child"))')
					.first()
					.then(($parentTheme) => {
						if ($parentTheme.find('.activate').length > 0) {
							cy.get('.theme:contains("Twenty Twenty-One"):not(:contains("Child"))')
								.first()
								.find('.activate')
								.click();
							cy.reload();
							cy.get('body').should('exist');
						}
					});
			}
		});

		// Try to cleanup the child theme directory using multiple approaches
		if (themesDir) {
			cy.exec(`rm -rf ${themesDir}/child-theme`, { failOnNonZeroExit: false }).then(
				(result) => {
					if (result.code !== 0) {
						cy.log(`Cleanup attempt 1 failed: ${result.stderr}`);
						// Try alternate path
						cy.exec('rm -rf wp-content/themes/child-theme', {
							failOnNonZeroExit: false,
						}).then((secondAttempt) => {
							if (secondAttempt.code !== 0) {
								cy.log(`Cleanup attempt 2 failed: ${secondAttempt.stderr}`);
							} else {
								cy.log('Cleanup succeeded with alternate path');
							}
						});
					} else {
						cy.log('Child theme removed successfully');
					}
				},
			);
		} else {
			// If themesDir wasn't set, try the relative path
			cy.exec('rm -rf wp-content/themes/child-theme', { failOnNonZeroExit: false });
		}
	});
});
