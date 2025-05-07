/* eslint-disable no-unused-vars */
/* eslint-disable no-undef */
/* eslint-disable no-console */
describe('ElasticPress Autosuggest V2', () => {
	let wpEnvDir;
	let themesDir;

	before(() => {
		cy.task('log', '---------------------- TEST SETUP BEGINS ----------------------');
		cy.task('log', 'Disabling features and enabling autosuggest-v2');
		cy.maybeDisableFeature('instant-results');
		cy.maybeDisableFeature('autosuggest');
		cy.maybeEnableFeature('autosuggest-v2');

		// Handle site activation
		const activateThemeOnSite = () => {
			cy.task('log', 'Attempting to activate theme on site');
			cy.visit('/wp-admin/themes.php');
			cy.get('body').should('exist');

			// Check if theme exists
			cy.get('body').then(($body) => {
				cy.task('log', 'Checking if Twenty Twenty-One Child theme exists on site');
				if ($body.find('.theme:contains("Twenty Twenty-One Child")').length) {
					cy.task('log', 'Child theme found on site: Twenty Twenty-One Child');
					cy.get('.theme:contains("Twenty Twenty-One Child")').as('childThemeMain');

					cy.get('@childThemeMain').then(($theme) => {
						if ($theme.find('.activate').length > 0) {
							cy.task(
								'log',
								'Child theme needs activation - clicking activate button',
							);
							cy.get('.theme:contains("Twenty Twenty-One Child")')
								.find('.activate')
								.click();
							// eslint-disable-next-line cypress/no-unnecessary-waiting
							cy.wait(3000);
							cy.task('log', 'Theme activated on site');
						} else {
							cy.task('log', 'Child Theme already active on main site');
						}
					});
				} else {
					cy.task('log', 'WARNING: Child theme not found in site themes list');
				}
			});
		};

		// Handle network activation
		const activateThemeOnNetwork = () => {
			cy.task('log', 'Attempting to activate theme on network');
			cy.visit('/wp-admin/network/themes.php');
			cy.get('body').should('exist');

			// Check if theme exists
			cy.get('body').then(($body) => {
				cy.task('log', 'Checking if Twenty Twenty-One Child theme exists on network');
				if ($body.find('.theme-title:contains("Twenty Twenty-One Child")').length) {
					cy.task('log', 'Child theme found on network: Twenty Twenty-One Child');
					cy.get('.theme-title:contains("Twenty Twenty-One Child")')
						.first()
						.as('childTheme');

					// Check if theme needs activation
					cy.get('@childTheme').then(($theme) => {
						if ($theme.find('.enable').length > 0) {
							cy.task(
								'log',
								'Child theme needs network activation - clicking enable button',
							);
							cy.get('.theme-title:contains("Twenty Twenty-One Child")')
								.first()
								.find('.enable .edit')
								.click();

							// Wait for page reload
							// eslint-disable-next-line cypress/no-unnecessary-waiting
							cy.wait(2000);
							cy.reload();
							cy.get('body').should('be.visible');
							cy.task('log', 'Theme enabled on network');
						} else {
							// Already enabled on network
							cy.task('log', 'Child Theme already enabled on network');
						}
						// Continue with site activation
						cy.task('log', 'Proceeding to site activation');
						activateThemeOnSite();
					});
				} else {
					cy.task('log', 'WARNING: Child theme not found in network themes list');
					cy.task('log', 'Attempting site activation anyway');
					activateThemeOnSite(); // Try to continue anyway
				}
			});
		};

		// Setup and activate child theme
		const setupChildTheme = () => {
			cy.task('log', 'Setting up child theme');
			cy.task('log', `Current themes directory path: ${themesDir}`);
			cy.login();
			cy.task('log', 'Logged in, preparing to copy child theme');

			// Clone the child theme directory with error handling
			cy.task(
				'log',
				`Executing: cp -r ./tests/cypress/integration/features/autosuggest-v2/tests-child-theme ${themesDir}/child-theme`,
			);
			cy.exec(
				`cp -r ./tests/cypress/integration/features/autosuggest-v2/tests-child-theme ${themesDir}/child-theme`,
				{ failOnNonZeroExit: false },
			).then((result) => {
				if (result.code !== 0) {
					cy.task(
						'log',
						`WARNING: Failed to copy child theme. Exit code: ${result.code}`,
					);
					cy.task('log', `ERROR: ${result.stderr}`);
					cy.task('log', 'Attempting second copy approach with different path');
					// Try a different approach - maybe the path just needs to be relative to the WP install
					cy.exec(
						'cp -r ./tests/cypress/integration/features/autosuggest-v2/tests-child-theme wp-content/themes/child-theme',
						{ failOnNonZeroExit: false },
					).then((secondAttempt) => {
						if (secondAttempt.code !== 0) {
							cy.task(
								'log',
								`WARNING: Second attempt to copy child theme failed. Exit code: ${secondAttempt.code}`,
							);
							cy.task('log', `ERROR: ${secondAttempt.stderr}`);
						} else {
							cy.task('log', 'Second attempt to copy child theme succeeded');
						}
					});
				} else {
					cy.task('log', 'Successfully copied child theme to themes directory');
				}

				// Network activate the theme
				cy.task('log', 'Proceeding to theme network activation');
				activateThemeOnNetwork();
			});
		};

		// Fallback to relative path as last resort
		const useRelativePath = () => {
			// Use relative path as last resort
			cy.task(
				'log',
				'All directory detection attempts failed, using relative path as fallback',
			);
			themesDir = './wp-content/themes';
			cy.task('log', `Setting themes directory to: ${themesDir}`);
			return setupChildTheme();
		};

		// Function to find theme directory with simplified GitHub detection
		const findThemesDirectory = () => {
			cy.task('log', 'Starting theme directory detection');

			// First attempt: Local development with .wp-env
			cy.task('log', 'Attempting to find local .wp-env directory');
			cy.exec('cd ~/.wp-env && ls -t | grep -E "^[0-9a-f]{32}$" | head -n 1', {
				failOnNonZeroExit: false,
				// eslint-disable-next-line consistent-return
			}).then((result) => {
				if (result.code === 0 && result.stdout.trim() !== '') {
					// Local approach succeeded
					wpEnvDir = result.stdout.trim();
					themesDir = `~/.wp-env/${wpEnvDir}/tests-WordPress/wp-content/themes`;
					cy.task('log', `SUCCESS: Found WordPress themes directory at: ${themesDir}`);
					cy.task('log', 'Using local development path with .wp-env');
					return setupChildTheme();
				}

				cy.task(
					'log',
					'Local .wp-env directory not found, checking for GitHub Actions environment',
				);
				// Local approach failed, check if we're in GitHub Actions
				cy.exec('test -d "/home/runner/work" && echo "exists" || echo "not found"', {
					failOnNonZeroExit: false,
				}).then((actionResult) => {
					cy.task('log', `GitHub runner check result: ${actionResult.stdout}`);
					if (actionResult.stdout.includes('exists')) {
						// We're in GitHub Actions environment, use the known path
						themesDir = '/home/runner/work/ElasticPress/ElasticPress/wp-content/themes';
						cy.task('log', `SUCCESS: Detected GitHub Actions environment`);
						cy.task('log', `Using GitHub runner path: ${themesDir}`);
						return setupChildTheme();
					}
					cy.task('log', 'GitHub Actions environment not detected');
					// Not in GitHub Actions, use relative path as fallback
					return useRelativePath();
				});
			});
		};

		// Start the process
		cy.task('log', 'Starting directory detection and theme setup process');
		findThemesDirectory();
	});

	it('Should display results, and display the hooked customizations', () => {
		cy.task('log', '---------------------- TEST EXECUTION BEGINS ----------------------');
		// Visit the site
		cy.task('log', 'Visiting homepage');
		cy.visit('/');

		// Verify search input exists
		cy.get('body').then(($body) => {
			cy.task('log', `Body HTML content length: ${$body.html().length} characters`);
			if ($body.find('.wp-block-search__input').length) {
				cy.task('log', 'Search input element found');
			} else {
				cy.task(
					'log',
					'WARNING: Search input element NOT FOUND! Available search elements:',
				);
				// Log available search elements
				const searchElements = $body.find('input[type="search"], [class*="search"]').length;
				cy.task('log', `Found ${searchElements} potential search-related elements`);
				$body.find('input[type="search"], [class*="search"]').each((i, el) => {
					cy.task(
						'log',
						`Potential search element ${i}: class="${$(el).attr('class')}" id="${$(el).attr('id')}"`,
					);
				});
			}
		});

		// Check if the autosuggest element is visible
		cy.task('log', 'Typing "blog" into search input');
		cy.get('.wp-block-search__input').type('blog');

		cy.task('log', 'Checking for autosuggest dropdown visibility');
		cy.get('.ep-autosuggest')
			.should('be.visible')
			.then(($el) => {
				cy.task('log', 'Autosuggest dropdown is visible');
			});

		cy.task('log', 'Checking for custom header in dropdown');
		cy.get('.ep-autosuggest .custom-header')
			.should('be.visible')
			.then(($el) => {
				cy.task('log', 'Custom header in dropdown is visible');
			});

		cy.task('log', 'Checking for suggestion group in dropdown');
		cy.get('.ep-autosuggest .suggestion-group')
			.should('be.visible')
			.then(($el) => {
				cy.task('log', 'Suggestion group in dropdown is visible');
			});
	});

	// Add cleanup after test is complete
	after(() => {
		cy.task('log', '---------------------- TEST CLEANUP BEGINS ----------------------');
		// Try to switch back to parent theme
		cy.task('log', 'Switching back to parent theme');
		cy.visit('/wp-admin/themes.php');
		cy.get('body').then(($body) => {
			cy.task('log', 'Checking for parent Twenty Twenty-One theme');
			if ($body.find('.theme:contains("Twenty Twenty-One"):not(:contains("Child"))').length) {
				cy.task('log', 'Parent theme found');
				cy.get('.theme:contains("Twenty Twenty-One"):not(:contains("Child"))')
					.first()
					.then(($parentTheme) => {
						if ($parentTheme.find('.activate').length > 0) {
							cy.task('log', 'Activating parent theme');
							cy.get('.theme:contains("Twenty Twenty-One"):not(:contains("Child"))')
								.first()
								.find('.activate')
								.click();
							cy.reload();
							cy.get('body').should('exist');
							cy.task('log', 'Parent theme activated');
						} else {
							cy.task('log', 'Parent theme already active, no need to activate');
						}
					});
			} else {
				cy.task('log', 'WARNING: Parent theme not found');
			}
		});

		// Try to cleanup the child theme directory using multiple approaches
		cy.task('log', 'Cleaning up child theme directory');
		if (themesDir) {
			cy.task('log', `Removing child theme from: ${themesDir}/child-theme`);
			cy.exec(`rm -rf ${themesDir}/child-theme`, { failOnNonZeroExit: false }).then(
				(result) => {
					if (result.code !== 0) {
						cy.task('log', `Cleanup attempt 1 failed. Exit code: ${result.code}`);
						cy.task('log', `ERROR: ${result.stderr}`);
						cy.task('log', 'Trying alternate cleanup path');
						// Try alternate path
						cy.exec('rm -rf wp-content/themes/child-theme', {
							failOnNonZeroExit: false,
						}).then((secondAttempt) => {
							if (secondAttempt.code !== 0) {
								cy.task(
									'log',
									`Cleanup attempt 2 failed. Exit code: ${secondAttempt.code}`,
								);
								cy.task('log', `ERROR: ${secondAttempt.stderr}`);
							} else {
								cy.task('log', 'Cleanup succeeded with alternate path');
							}
						});
					} else {
						cy.task('log', 'Child theme removed successfully');
					}
				},
			);
		} else {
			// If themesDir wasn't set, try the relative path
			cy.task('log', 'Theme directory not set, trying relative path for cleanup');
			cy.exec('rm -rf wp-content/themes/child-theme', { failOnNonZeroExit: false }).then(
				(result) => {
					if (result.code !== 0) {
						cy.task('log', `Relative path cleanup failed. Exit code: ${result.code}`);
						cy.task('log', `ERROR: ${result.stderr}`);
					} else {
						cy.task('log', 'Relative path cleanup succeeded');
					}
				},
			);
		}
		cy.task('log', '---------------------- TEST COMPLETED ----------------------');
	});
});
