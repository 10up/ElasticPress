describe('Post Search Feature - Synonyms Functionality', () => {
	/**
	 * Save synonyms settings.
	 */
	function saveSynonyms() {
		cy.intercept('/wp-json/elasticpress/v1/synonyms*').as('apiRequest');
		cy.contains('button', 'Save changes').click();
		cy.wait('@apiRequest');
		cy.contains('Synonym settings saved.').should('exist');
	}

	/**
	 * Delete synonyms recreate test posts before running tests.
	 */
	before(() => {
		cy.wpCliEval(
			`
			$ep_synonyms_tests = get_posts(
				[
					'post_type'   => 'any',
					'meta_key'    => '_synonyms_tests',
					'meta_value'  => 1,
					'numberposts' => 999,
				]
			);
			foreach( $ep_synonyms_tests as $test ) {
				wp_delete_post( $test->ID, true );
			}

			$posts = [
				'Plugin',
				'Extension',
				'Module',
				'ElasticPress',
				'Safe Redirect Manager',
				'Bandeirole',
				'Flag',
				'Banner',
				'Red',
				'Carmine',
				'Cordovan',
				'Crimson',
			];
			foreach ( $posts as $post ) {
				wp_insert_post(
					[
						'post_title'   => $post,
						'post_content' => '',
						'post_status'  => 'publish',
						'meta_input'   => [
							'_synonyms_tests' => 1,
						],
					]
				);
			}`,
		);
	});

	/**
	 * Log in before each test.
	 */
	beforeEach(() => {
		cy.login();
		cy.wpCliEval(
			`
			$ep_synonyms = get_posts(
				[
					'post_type'   => 'ep-synonym',
					'post_status' => 'any',
					'numberposts' => 999,
				]
			);
			foreach( $ep_synonyms as $synonym ) {
				wp_delete_post( $synonym->ID, true );
			}`,
		);

		/**
		 * Save synonyms settings.
		 */
		cy.visitAdminPage('admin.php?page=elasticpress-synonyms');
		saveSynonyms();
	});

	/**
	 * Test that synonyms work as expected.
	 */
	it('Is possible to create, edit, and delete synonym rules', () => {
		/**
		 * Confirm that only results with our search term are returned.
		 */
		cy.visit('/?s=plugin');
		cy.contains('article h2', 'Plugin').should('exist');
		cy.contains('article h2', 'Extension').should('not.exist');
		cy.contains('article h2', 'Module').should('not.exist');

		/**
		 * Enter a synonym.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress-synonyms');
		cy.get('.ep-synonyms-edit-panel').as('panel');
		cy.get('@panel').contains('Add Synonyms').should('exist');
		cy.get('@panel').find('input[type="text"]').as('input').type('plugin,');

		/**
		 * Add button should be disabled when there's only one synonym.
		 */
		cy.get('@panel').contains('button', 'Add synonyms').as('add').should('be.disabled');

		/**
		 * Enter another synonym and submit.
		 */
		cy.get('@input').type('extension,');
		cy.get('@add').click();

		/**
		 * The synonyms should appear in the list.
		 */
		cy.contains('.ep-synonyms-list-table tr', 'plugin, extension').should('exist');

		saveSynonyms();

		/**
		 * Results should reflect the synonym rules.
		 */
		cy.visit('/?s=plugin');
		cy.contains('article h2', 'Plugin').should('exist');
		cy.contains('article h2', 'Extension').should('exist');
		cy.contains('article h2', 'Module').should('not.exist');

		/**
		 * It should be possible to edit synonym rules.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress-synonyms');
		cy.contains('.ep-synonyms-list-table tr', 'plugin, extension').as('row');
		cy.get('@row').find('button[aria-label="Edit"]').click();
		cy.get('.ep-synonyms-edit-panel').as('panel');
		cy.get('@panel').contains('Edit Synonyms').should('exist');
		cy.get('@panel').find('input').type('{backspace}module,');
		cy.get('@panel').contains('button', 'Save changes').click();
		cy.contains('.ep-synonyms-list-table tr', 'plugin, module').should('exist');

		saveSynonyms();

		/**
		 * Results should reflect the new synonyms.
		 */
		cy.visit('/?s=plugin');
		cy.contains('article h2', 'Plugin').should('exist');
		cy.contains('article h2', 'Extension').should('not.exist');
		cy.contains('article h2', 'Module').should('exist');

		/**
		 * In the advanced editor, synonyms should be represented as expected.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress-synonyms');
		cy.contains('button', 'Switch to advanced text editor').click();
		cy.get('textarea').should('contain', 'plugin, module');

		/**
		 * It should be possible to delete synonym rules.
		 */
		cy.contains('button', 'Switch to visual editor').click();
		cy.contains('.ep-synonyms-list-table tr', 'plugin, module').as('row');
		cy.get('@row').find('button[aria-label="Delete"]').click();
		cy.contains('.ep-synonyms-list-table tr', 'plugin').should('not.exist');

		saveSynonyms();

		/**
		 * Results should reflect the deleted synonyms.
		 */
		cy.visit('/?s=plugin');
		cy.contains('article h2', 'Plugin').should('exist');
		cy.contains('article h2', 'Extension').should('not.exist');
		cy.contains('article h2', 'Module').should('not.exist');
	});

	/**
	 * Test that hyponyms work as expected.
	 */
	it('Is possible to create, edit, and delete hyponym rules', () => {
		/**
		 * Confirm that only results with our search term are returned.
		 */
		cy.visit('/?s=plugin');
		cy.contains('article h2', 'Plugin').should('exist');
		cy.contains('article h2', 'ElasticPress').should('not.exist');
		cy.contains('article h2', 'Safe Redirect Manager').should('not.exist');

		/**
		 * Enter a hypernym.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress-synonyms');
		cy.contains('button', 'Hyponyms').click();
		cy.get('.ep-synonyms-edit-panel').as('panel');
		cy.get('@panel').contains('Add Hyponyms').should('exist');
		cy.get('@panel').find('input[type="text"]').eq(0).type('plugin');

		/**
		 * Add button should be disabled when there's no hyponyms.
		 */
		cy.get('@panel').contains('button', 'Add hyponyms').as('add').should('be.disabled');

		/**
		 * Enter a hyponym and submit.
		 */
		cy.get('@panel').find('input[type="text"]').eq(1).type('ElasticPress,');
		cy.get('@add').click();

		/**
		 * The rule should appear in the list,
		 */
		cy.contains('.ep-synonyms-list-table tr', 'plugin').should('exist');

		saveSynonyms();

		/**
		 * Results should reflect the hyponym rules.
		 */
		cy.visit('/?s=plugin');
		cy.contains('article h2', 'Plugin').should('exist');
		cy.contains('article h2', 'ElasticPress').should('exist');
		cy.contains('article h2', 'Safe Redirect Manager').should('not.exist');

		cy.visit('/?s=elasticpress');
		cy.contains('article h2', 'Plugin').should('not.exist');
		cy.contains('article h2', 'ElasticPress').should('exist');
		cy.contains('article h2', 'Safe Redirect Manager').should('not.exist');

		cy.visit('/?s=redirect');
		cy.contains('article h2', 'Plugin').should('not.exist');
		cy.contains('article h2', 'ElasticPress').should('not.exist');
		cy.contains('article h2', 'Safe Redirect Manager').should('exist');

		/**
		 * It should be possible to edit hyponym rules.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress-synonyms');
		cy.contains('button', 'Hyponyms').click();
		cy.contains('.ep-synonyms-list-table tr', 'plugin').as('row');
		cy.get('@row').find('button[aria-label="Edit"]').click();
		cy.get('.ep-synonyms-edit-panel').as('panel');
		cy.get('@panel').contains('Edit Hyponyms').should('exist');
		cy.get('@panel').find('input').eq(1).type('Safe Redirect Manager,');
		cy.get('@panel').contains('button', 'Save changes').click();
		cy.get('@row')
			.contains('td', 'ElasticPress, Safe Redirect Manager')
			.should('exist')
			.should('not.contain', 'plugin');

		saveSynonyms();

		/**
		 * Results should reflect the new hyponyms.
		 */
		cy.visit('/?s=plugin');
		cy.contains('article h2', 'Plugin').should('exist');
		cy.contains('article h2', 'ElasticPress').should('exist');
		cy.contains('article h2', 'Safe Redirect Manager').should('exist');

		cy.visit('/?s=elasticpress');
		cy.contains('article h2', 'Plugin').should('not.exist');
		cy.contains('article h2', 'ElasticPress').should('exist');
		cy.contains('article h2', 'Safe Redirect Manager').should('not.exist');

		cy.visit('/?s=redirect');
		cy.contains('article h2', 'Plugin').should('not.exist');
		cy.contains('article h2', 'ElasticPress').should('not.exist');
		cy.contains('article h2', 'Safe Redirect Manager').should('exist');

		/**
		 * In the advanced editor, hyponyms should be represented as
		 * replacements where the hypernym is also included as a replacement.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress-synonyms');
		cy.contains('button', 'Switch to advanced text editor').click();
		cy.get('textarea').should(
			'contain',
			'plugin => plugin, ElasticPress, Safe Redirect Manager',
		);

		/**
		 * It should be possible to delete hyponym rules.
		 */
		cy.contains('button', 'Switch to visual editor').click();
		cy.contains('button', 'Hyponyms').click();
		cy.contains('.ep-synonyms-list-table tr', 'plugin').as('row');
		cy.get('@row').find('button[aria-label="Delete"]').click();
		cy.contains('.ep-synonyms-list-table tr', 'plugin').should('not.exist');

		saveSynonyms();

		/**
		 * Results should not longer reflect the deleted rule.
		 */
		cy.visit('/?s=plugin');
		cy.contains('article h2', 'Plugin').should('exist');
		cy.contains('article h2', 'ElasticPress').should('not.exist');
		cy.contains('article h2', 'Safe Redirect Manager').should('not.exist');

		cy.visit('/?s=elasticpress');
		cy.contains('article h2', 'Plugin').should('not.exist');
		cy.contains('article h2', 'ElasticPress').should('exist');
		cy.contains('article h2', 'Safe Redirect Manager').should('not.exist');

		cy.visit('/?s=redirect');
		cy.contains('article h2', 'Plugin').should('not.exist');
		cy.contains('article h2', 'ElasticPress').should('not.exist');
		cy.contains('article h2', 'Safe Redirect Manager').should('exist');
	});

	/**
	 * Test that replacements work as expected.
	 */
	it('Is possible to create, edit, and delete replacement rules', () => {
		cy.activatePlugin('disable-fuzziness', 'wpCli');

		/**
		 * Confirm that our replacements are not returned yet.
		 */
		cy.visit('/?s=bandeirole');
		cy.contains('article h2', 'Bandeirole').should('exist');
		cy.contains('article h2', 'Flag').should('not.exist');
		cy.contains('article h2', 'Banner').should('not.exist');

		/**
		 * Enter a term.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress-synonyms');
		cy.contains('button', 'Replacements').click();
		cy.get('.ep-synonyms-edit-panel').as('panel');
		cy.get('@panel').contains('Add Replacements').should('exist');
		cy.get('@panel').find('input[type="text"]').eq(0).type('bandeirole,');

		/**
		 * Add button should be disabled when there's no replacements.
		 */
		cy.get('@panel').contains('button', 'Add replacements').as('add').should('be.disabled');

		/**
		 * Enter a replacement and submit.
		 */
		cy.get('@panel').find('input[type="text"]').eq(1).type('flag,');
		cy.get('@add').click();

		/**
		 * The replacements should appear in the list.
		 */
		cy.contains('.ep-synonyms-list-table tr', 'bandeirole').should('exist');

		saveSynonyms();

		/**
		 * Results should reflect the replacement rules.
		 */
		cy.visit('/?s=bandeirole');
		cy.contains('article h2', 'Bandeirole').should('not.exist');
		cy.contains('article h2', 'Flag').should('exist');
		cy.contains('article h2', 'Banner').should('not.exist');

		/**
		 * It should be possible to edit replacement rules.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress-synonyms');
		cy.contains('button', 'Replacements').click();
		cy.contains('.ep-synonyms-list-table tr', 'bandeirole').as('row');
		cy.get('@row').find('button[aria-label="Edit"]').click();
		cy.get('.ep-synonyms-edit-panel').as('panel');
		cy.get('@panel').contains('Edit Replacements').should('exist');
		cy.get('@panel').find('input').eq(1).type('banner,');
		cy.get('@panel').contains('button', 'Save changes').click();
		cy.contains('.ep-synonyms-list-table tr', 'flag, banner').should('exist');

		saveSynonyms();

		/**
		 * Results should reflect the new replacements.
		 */
		cy.visit('/?s=bandeirole');
		cy.contains('article h2', 'Bandeirole').should('not.exist');
		cy.contains('article h2', 'Flag').should('exist');
		cy.contains('article h2', 'Banner').should('exist');

		/**
		 * In the advanced editor, replacements hould be represented as
		 * expected.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress-synonyms');
		cy.contains('button', 'Switch to advanced text editor').click();
		cy.get('textarea').should('contain', 'bandeirole => flag, banner');

		/**
		 * It should be possible to delete replacement rules.
		 */
		cy.contains('button', 'Switch to visual editor').click();
		cy.contains('button', 'Replacements').click();
		cy.contains('.ep-synonyms-list-table tr', 'bandeirole').as('row');
		cy.get('@row').find('button[aria-label="Delete"]').click();
		cy.contains('.ep-synonyms-list-table tr', 'bandeirole').should('not.exist');

		saveSynonyms();

		/**
		 * Results should not longer reflect the deleted rule.
		 */
		cy.visit('/?s=bandeirole');
		cy.contains('article h2', 'Bandeirole').should('exist');
		cy.contains('article h2', 'Flag').should('not.exist');
		cy.contains('article h2', 'Banner').should('not.exist');

		cy.deactivatePlugin('disable-fuzziness', 'wpCli');
	});

	/**
	 * Test the advanced text editor.
	 */
	it('Is possible to edit rules using the text editor', () => {
		/**
		 * Our rule should not be reflected in results yet.
		 */
		cy.visit('/?s=red');
		cy.contains('article h2', 'Red').should('exist');
		cy.contains('article h2', 'Carmine').should('not.exist');
		cy.contains('article h2', 'Cordovan').should('not.exist');
		cy.contains('article h2', 'Crimson').should('not.exist');

		/**
		 * Add a hyponym rule to the text editor.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress-synonyms');
		cy.contains('button', 'Switch to advanced text editor').click();
		cy.get('textarea').type('red => red, carmine, cordovan, crimson');

		saveSynonyms();

		/**
		 * Our rule should be reflected in results.
		 */
		cy.visit('/?s=red');
		cy.contains('article h2', 'Red').should('exist');
		cy.contains('article h2', 'Carmine').should('exist');
		cy.contains('article h2', 'Cordovan').should('exist');
		cy.contains('article h2', 'Crimson').should('exist');

		cy.visit('/?s=carmine');
		cy.contains('article h2', 'Red').should('not.exist');
		cy.contains('article h2', 'Carmine').should('exist');
		cy.contains('article h2', 'Cordovan').should('not.exist');
		cy.contains('article h2', 'Crimson').should('not.exist');

		/**
		 * The settings page should remember that we used the text editor.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress-synonyms');
		cy.get('textarea').should('exist');

		/**
		 * Our rule should be visible under Hyponyms when we switch to the
		 * visual editor.
		 */
		cy.contains('button', 'Switch to visual editor').click();
		cy.contains('button', 'Hyponyms').click();
		cy.contains('.ep-synonyms-list-table tr', 'carmine, cordovan, crimson').should('exist');
	});
});
