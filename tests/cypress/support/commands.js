// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

import 'cypress-file-upload';
import './commands/block-editor';
import '@4tw/cypress-drag-drop';

Cypress.Commands.add('login', (username = 'admin', password = 'password') => {
	cy.visit(`/wp-admin`);
	cy.get('body').then(($body) => {
		if ($body.find('#wpwrap').length === 0) {
			cy.get('input#user_login').clear();
			cy.get('input#user_login').click();
			cy.get('input#user_login').type(username);
			cy.get('input#user_pass').type(`${password}{enter}`);
		}
	});
});

Cypress.Commands.add('visitAdminPage', (page = 'index.php') => {
	cy.login();
	if (page.includes('http')) {
		cy.visit(page);
	} else {
		cy.visit(`/wp-admin/${page.replace(/^\/|\/$/g, '')}`);
	}
});

Cypress.Commands.add('openWidgetsPage', () => {
	cy.login();
	cy.visitAdminPage('widgets.php');
	cy.get('body').then(($body) => {
		const $button = $body.find('.edit-widgets-welcome-guide .components-modal__header button');
		if ($button.is(':visible')) {
			$button.click();
		}
	});
});

Cypress.Commands.add('createTerm', (data) => {
	const { taxonomy, name, parent } = {
		name: 'Test taxonomy',
		taxonomy: 'category',
		parent: null,
		...data,
	};

	cy.visitAdminPage(`edit-tags.php?taxonomy=${taxonomy}`);

	if (parent !== null) {
		cy.get('#parent').select(parent);
	}

	// wait for ajax request to finish.
	cy.intercept('POST', 'wp-admin/admin-ajax.php*').as('ajaxRequest');
	cy.get('#tag-name').click();
	cy.get('#tag-name').type(`${name}{enter}`);
	cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);
});

Cypress.Commands.add('clearThenType', { prevSubject: true }, (subject, text, force = false) => {
	cy.wrap(subject).clear();
	cy.wrap(subject).type(text, { force });
});

Cypress.Commands.add('wpCli', (command, ignoreFailures) => {
	const escapedCommand = command.replace(/"/g, '\\"').replace(/^wp /, '');
	const options = {};
	if (ignoreFailures) {
		options.failOnNonZeroExit = false;
	}
	cy.exec(`./bin/wp-env-cli tests-wordpress "wp --allow-root ${escapedCommand}"`, options).then(
		(result) => {
			cy.wrap(result);
		},
	);
});

Cypress.Commands.add('wpCliEval', (command) => {
	const fileName = (Math.random() + 1).toString(36).substring(7);

	// this will be written "local" plugin directory
	const escapedCommand = command.replace(/^<\?php /, '');
	cy.writeFile(fileName, `<?php ${escapedCommand}`);

	const pluginName = Cypress.config('pluginName');

	// which is read from it's proper location in the plugins directory
	cy.exec(
		`./bin/wp-env-cli tests-wordpress "wp --allow-root eval-file wp-content/plugins/${pluginName}/${fileName}"`,
	).then((result) => {
		cy.exec(`rm ${fileName}`);
		cy.wrap(result);
	});
});

Cypress.Commands.add('publishPost', (postData, viewPost) => {
	const newPostData = { title: 'Test Post', content: 'Test content.', ...postData };

	cy.visitAdminPage('post-new.php');
	cy.get('h1.editor-post-title__input, #post-title-0').should('exist');
	cy.get('body').then(($body) => {
		const welcomeGuide = $body.find(
			'.edit-post-welcome-guide .components-modal__header button',
		);
		if (welcomeGuide.length) {
			welcomeGuide.click();
		}
	});

	cy.get('h1.editor-post-title__input, #post-title-0').clearThenType(newPostData.title);
	cy.get('.block-editor-default-block-appender__content').type(newPostData.content);

	if (newPostData.password && newPostData.password !== '') {
		cy.get('h1.editor-post-title__input').click();
		cy.get('body').then(($body) => {
			const $button = $body.find('.edit-post-post-visibility__toggle');
			if (!$button.is(':visible')) {
				cy.get('.edit-post-header__settings button[aria-label="Settings"]').click();
			}
		});
		cy.get('.edit-post-post-visibility__toggle').click();
		cy.get('.editor-post-visibility__dialog-radio, .editor-post-visibility__radio').check(
			'password',
		);
		cy.get(
			'.editor-post-visibility__dialog-password-input, .editor-post-visibility__password-input',
		).type(newPostData.password);
	}

	if (newPostData.status && newPostData.status === 'draft') {
		cy.get('.editor-post-save-draft').click();
		cy.get('.editor-post-saved-state').should('have.text', 'Saved');
	} else {
		cy.get('.editor-post-publish-panel__toggle').should('be.enabled');
		cy.get('.editor-post-publish-panel__toggle').click();

		cy.get('.editor-post-publish-button').click();

		cy.get('.components-snackbar').should('be.visible');

		if (viewPost) {
			cy.get('.post-publish-panel__postpublish-buttons a').contains('View Post').click();
		}
	}

	/**
	 * Give Elasticsearch some time to process the new post.
	 *
	 * @todo instead of waiting for an arbitrary time, we should ensure the post is stored.
	 */
	// eslint-disable-next-line cypress/no-unnecessary-waiting
	cy.wait(2000);
});

Cypress.Commands.add('updateFeatures', (featureName, newValues) => {
	const escapedNewValues = JSON.stringify(newValues);

	cy.wpCliEval(
		`
		$feature_settings = get_option( 'ep_feature_settings', [] );

		$feature_settings['${featureName}'] = json_decode( '${escapedNewValues}', true );
		update_option( 'ep_feature_settings', $feature_settings );
		`,
	);
});

Cypress.Commands.add('updateWeighting', (newWeightingValues = null) => {
	const defaultWeighting = {
		post: {
			post_title: {
				weight: 1,
				enabled: true,
			},
			post_content: {
				weight: 1,
				enabled: true,
			},
			post_excerpt: {
				weight: 1,
				enabled: true,
			},
			author_name: {
				weight: 0,
				enabled: false,
			},
		},
		page: {
			post_title: {
				weight: 1,
				enabled: true,
			},
			post_content: {
				weight: 1,
				enabled: true,
			},
			post_excerpt: {
				weight: 1,
				enabled: true,
			},
			author_name: {
				weight: 0,
				enabled: false,
			},
		},
	};

	const escapedWeighting = newWeightingValues
		? JSON.stringify(newWeightingValues)
		: JSON.stringify(defaultWeighting);

	cy.wpCliEval(
		`$weighting = json_decode( '${escapedWeighting}', true ); update_option( 'elasticpress_weighting', $weighting );`,
	);
});

Cypress.Commands.add('maybeEnableFeature', (featureName) => {
	cy.wpCli(`elasticpress activate-feature ${featureName}`, true);
});

Cypress.Commands.add('maybeDisableFeature', (featureName) => {
	cy.wpCli(`elasticpress deactivate-feature ${featureName}`, true);
});

Cypress.Commands.add('getTotal', (totalNumber) => {
	cy.get('.query-results')
		.invoke('text')
		.should('match', new RegExp(`"(total|value)": ${totalNumber}`, 'g'));
});

Cypress.Commands.add('activatePlugin', (slug, method = 'dashboard', mode = 'singleSite') => {
	if (method === 'dashboard') {
		if (mode === 'network') {
			cy.visitAdminPage('network/plugins.php');
		} else {
			cy.visitAdminPage('plugins.php');
		}

		cy.get('body').then(($body) => {
			const $activateButton = $body.find(`#activate-${slug}`);
			if ($activateButton.length) {
				cy.get($activateButton).click();
			}
		});

		return;
	}

	const pluginSlug = slug.replace('elasticpress', Cypress.config('pluginName'));
	let command = `wp plugin activate ${pluginSlug}`;
	if (mode === 'network') {
		command += ' --network';
	}
	cy.wpCli(command);
});

Cypress.Commands.add('deactivatePlugin', (slug, method = 'dashboard', mode = 'singleSite') => {
	if (method === 'dashboard') {
		if (mode === 'network') {
			cy.visitAdminPage('network/plugins.php');
		} else {
			cy.visitAdminPage('plugins.php');
		}

		cy.get('body').then(($body) => {
			const $deactivateButton = $body.find(`#deactivate-${slug}`);
			if ($deactivateButton.length) {
				cy.get($deactivateButton).click();
			}
		});

		return;
	}

	const pluginSlug = slug.replace('elasticpress', Cypress.config('pluginName'));
	let command = `wp plugin deactivate ${pluginSlug}`;
	if (mode === 'network') {
		command += ' --network';
	}
	cy.wpCli(command);
});

Cypress.Commands.add('createClassicWidget', (widgetId, settings) => {
	cy.openWidgetsPage();
	cy.intercept('/wp-admin/admin-ajax.php').as('adminAjax');

	/**
	 * Find and add the widget to the first widget area.
	 */
	cy.get(`#widget-list [id$="${widgetId}-__i__"]`).click('top');
	cy.get(`#widget-list [id$="${widgetId}-__i__"]`).within(() => {
		cy.get('.widgets-chooser-add').click();
	});
	cy.wait('@adminAjax');

	/**
	 * Set widget settings and save.
	 */
	cy.get(`#widgets-right .widget[id*="${widgetId}"]`)
		.last()
		.within(() => {
			for (const setting of settings) {
				cy.get(`[name*="[${setting.name}]"]`).as('control');

				switch (setting.type) {
					case 'select':
						cy.get('@control').select(setting.value);
						break;
					case 'checkbox':
					case 'radio':
						cy.get('@control').check(setting.value);
						break;
					default:
						cy.get('@control').clear();
						cy.get('@control').type(setting.value);
						break;
				}
			}

			cy.get('input[type="submit"]').click();
			cy.wait('@adminAjax').its('response.statusCode').should('eq', 200);
		});
	cy.wait('@adminAjax');
});

Cypress.Commands.add('emptyWidgets', () => {
	cy.wpCliEval(
		`
		WP_CLI::runcommand('widget reset --all');

		$inactive_widgets = WP_CLI::runcommand('widget list wp_inactive_widgets --format=ids', [ 'return' => true ] );
		if ( $inactive_widgets ) {
			WP_CLI::runcommand("widget delete {$inactive_widgets}" );
		}
		`,
	);
});

// Command to drag and drop React DnD element. Original code from: https://github.com/cypress-io/cypress/issues/3942#issuecomment-485648100
Cypress.Commands.add('dragAndDrop', (subject, target) => {
	Cypress.log({
		name: 'DRAGNDROP',
		message: `Dragging element ${subject} to ${target}`,
		consoleProps: () => {
			return {
				subject,
				target,
			};
		},
	});
	const BUTTON_INDEX = 0;
	const SLOPPY_CLICK_THRESHOLD = 10;
	cy.get(target)
		.first()
		.then(($target) => {
			const coordsDrop = $target[0].getBoundingClientRect();
			cy.get(subject)
				.first()
				.then((subject) => {
					const coordsDrag = subject[0].getBoundingClientRect();
					cy.wrap(subject).trigger('mousedown', {
						button: BUTTON_INDEX,
						clientX: coordsDrag.x,
						clientY: coordsDrag.y,
						force: true,
					});
					cy.wrap(subject).trigger('mousemove', {
						button: BUTTON_INDEX,
						clientX: coordsDrag.x + SLOPPY_CLICK_THRESHOLD,
						clientY: coordsDrag.y,
						force: true,
					});
					cy.get('body').trigger('mousemove', {
						button: BUTTON_INDEX,
						clientX: coordsDrop.x,
						clientY: coordsDrop.y,
						force: true,
					});
					cy.get('body').trigger('mouseup');
				});
		});
});

Cypress.Commands.add('createAutosavePost', (postData) => {
	cy.activatePlugin('shorten-autosave', 'wpCli');
	const newPostData = { title: 'Test Post', content: 'Test content.', ...postData };

	cy.visitAdminPage('post-new.php');
	cy.get('h1.editor-post-title__input, #post-title-0').should('exist');
	cy.get('body').then(($body) => {
		const welcomeGuide = $body.find(
			'.edit-post-welcome-guide .components-modal__header button',
		);
		cy.log(welcomeGuide);
		if (welcomeGuide.length) {
			welcomeGuide.click();
		}
	});

	cy.get('h1.editor-post-title__input, #post-title-0').clearThenType(newPostData.title);
	cy.get('.block-editor-default-block-appender__content').type(newPostData.content);

	/**
	 * Wait for autosave to complete.
	 *
	 */
	// eslint-disable-next-line cypress/no-unnecessary-waiting
	cy.wait(5000);
	cy.deactivatePlugin('shorten-autosave', 'wpCli');
});

Cypress.Commands.add('logout', () => {
	cy.visit('/wp-admin');
	cy.get('body').then(($body) => {
		if ($body.find('#wpadminbar').length !== 0) {
			cy.get('#wp-admin-bar-my-account').invoke('addClass', 'hover');
			cy.get('#wp-admin-bar-logout > a').click();
		}
	});
});

Cypress.Commands.add('createUser', (userData) => {
	const newUserDate = {
		username: 'testuser',
		password: 'password',
		email: 'testuser@example.com',
		role: 'subscriber',
		login: false,
		...userData,
	};

	// delete the user.
	cy.wpCli(`wp user delete ${newUserDate.username} --yes --network`, true);

	// create the user
	cy.wpCli(
		`wp user create ${newUserDate.username} ${newUserDate.email} --user_pass=${newUserDate.password} --role=${newUserDate.role}`,
	);

	if (newUserDate.login) {
		cy.visit('wp-login.php');
		cy.get('#user_login').clear();
		cy.get('#user_login').type(newUserDate.username);
		cy.get('#user_pass').clear();
		cy.get('#user_pass').type(`${newUserDate.password}{enter}`);
	}
});

Cypress.Commands.add('setPerIndexCycle', (number = 350) => {
	cy.wpCli(`option set ep_bulk_setting ${number}`);
});

Cypress.Commands.add('refreshIndex', (indexable) => {
	cy.wpCliEval(
		`
		$index = \\ElasticPress\\Indexables::factory()->get( "${indexable}" )->get_index_name();
		WP_CLI::runcommand("elasticpress request {$index}/_refresh --method=POST");`,
	);
});
