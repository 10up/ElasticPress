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

Cypress.Commands.add('login', (username = 'admin', password = 'password') => {
	cy.visit(`/wp-admin`);
	cy.get('body').then(($body) => {
		if ($body.find('#wpwrap').length === 0) {
			cy.get('input#user_login').clear();
			cy.get('input#user_login').click().type(username);
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

Cypress.Commands.add('createTaxonomy', (name = 'Test taxonomy', taxonomy = 'category') => {
	cy.visitAdminPage(`edit-tags.php?taxonomy=${taxonomy}`);
	cy.get('#tag-name').click().type(`${name}{enter}`);
});

Cypress.Commands.add('clearThenType', { prevSubject: true }, (subject, text, force = false) => {
	cy.wrap(subject).clear().type(text, { force });
});

Cypress.Commands.add('wpCli', (command, ignoreFailures) => {
	const escapedCommand = command.replace(/"/g, '\\"').replace(/^wp /, '');
	const options = {};
	if (ignoreFailures) {
		options.failOnNonZeroExit = false;
	}
	cy.exec(`npm --silent run env run tests-cli "${escapedCommand}"`, options).then((result) => {
		cy.wrap(result);
	});
});

Cypress.Commands.add('wpCliEval', (command) => {
	const fileName = (Math.random() + 1).toString(36).substring(7);

	// this will be written "local" plugin directory
	const escapedCommand = command.replace(/^<\?php /, '');
	cy.writeFile(fileName, `<?php ${escapedCommand}`);

	const pluginName = Cypress.config('pluginName');

	// which is read from it's proper location in the plugins directory
	cy.exec(
		`npm --silent run env run tests-cli "eval-file wp-content/plugins/${pluginName}/${fileName}"`,
	).then((result) => {
		cy.exec(`rm ${fileName}`);
		cy.wrap(result);
	});
});

Cypress.Commands.add('publishPost', (postData) => {
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

	if (newPostData.status && newPostData.status === 'draft') {
		cy.get('.editor-post-save-draft').click();
		cy.get('.editor-post-saved-state').should('have.text', 'Saved');
	} else {
		cy.get('.editor-post-publish-panel__toggle').should('be.enabled');
		cy.get('.editor-post-publish-panel__toggle').click();

		cy.get('.editor-post-publish-button').click();

		cy.get('.components-snackbar').should('be.visible');
	}

	/**
	 * Give Elasticsearch some time to process the new post.
	 *
	 * @todo instead of waiting for an arbitrary time, we should ensure the post is stored.
	 */
	// eslint-disable-next-line cypress/no-unnecessary-waiting
	cy.wait(2000);
});

Cypress.Commands.add('updateFeatures', (newFeaturesValues = {}) => {
	const features = Object.assign({}, cy.elasticPress.defaultFeatures, ...newFeaturesValues);

	const escapedFeatures = JSON.stringify(features);

	cy.wpCliEval(
		`$features = json_decode( '${escapedFeatures}', true ); update_option( 'ep_feature_settings', $features );`,
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
	cy.wpCli('elasticpress list-features').then((wpCliResponse) => {
		if (!wpCliResponse.stdout.match(new RegExp(featureName, 'g'))) {
			cy.wpCli(`elasticpress activate-feature ${featureName}`);
		}
	});
});

Cypress.Commands.add('maybeDisableFeature', (featureName) => {
	cy.wpCli('elasticpress list-features').then((wpCliResponse) => {
		if (wpCliResponse.stdout.match(new RegExp(featureName, 'g'))) {
			cy.wpCli(`elasticpress deactivate-feature ${featureName}`);
		}
	});
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

Cypress.Commands.add('closeWelcomeGuide', () => {
	cy.get('.edit-post-welcome-guide .components-modal__header button').click();
});

Cypress.Commands.add('openBlockInserter', () => {
	cy.get('.edit-post-header-toolbar__inserter-toggle').click();
});

Cypress.Commands.add('getBlocksList', () => {
	cy.get('.block-editor-inserter__block-list');
});

Cypress.Commands.add('insertBlock', (blockName) => {
	cy.get('.block-editor-block-types-list__item').contains(blockName).click();
});

Cypress.Commands.add('updatePostAndView', () => {
	cy.get('.editor-post-publish-button__button').click();
	cy.get('.components-snackbar__action').click();
});
