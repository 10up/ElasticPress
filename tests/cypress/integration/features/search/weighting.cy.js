describe('Post Search Feature - Weighting Functionality', () => {
	/**
	 * Delete test posts before running tests.
	 */
	before(() => {
		cy.wpCli(
			'post list --meta_key="_weighting_tests" --meta_value="1" --ep_integrate=false --format=ids',
		).then((wpCliResponse) => {
			if (wpCliResponse.stdout) {
				cy.wpCli(`post delete ${wpCliResponse.stdout} --force`);
			}
		});
	});

	/**
	 * Reset weighting settings and log in before each test.
	 */
	beforeEach(() => {
		cy.deactivatePlugin('auto-meta-mode', 'wpCli');
		cy.updateWeighting();
		cy.login();
	});

	/**
	 * Test that the Searchable checkbox works as expected.
	 */
	it("Can't find a post by title if title is not marked as searchable", () => {
		/**
		 * Create post with a unique title.
		 */
		cy.wpCliEval(
			`wp_insert_post(
				[
					'post_title'   => 'supercustomtitle',
					'post_content' => '',
					'post_status'  => 'publish',
					'meta_input'   => [
						'_weighting_tests' => 1,
					],
				]
			);`,
		);

		/**
		 * Sync.
		 */
		cy.wpCli('wp elasticpress sync --yes').its('stdout').should('contain', 'Success: Done!');
		cy.wait(500); // eslint-disable-line cypress/no-unnecessary-waiting

		/**
		 * Search for the new post. It should be returned.
		 */
		cy.visit('/?s=supercustomtitle');
		cy.get('.entry-title').should('contain.text', 'supercustomtitle');

		/**
		 * Make the title non-searchable for Posts.
		 */
		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('.components-panel__header')
			.contains('Posts')
			.closest('.components-panel')
			.find('fieldset')
			.contains('Title')
			.closest('fieldset')
			.find('input[type="checkbox"]')
			.uncheck();

		/**
		 * Save weighting settings.
		 */
		cy.intercept('/wp-json/elasticpress/v1/weighting*').as('apiRequest');
		cy.contains('button', 'Save changes').click();
		cy.wait('@apiRequest');

		/**
		 * Sync.
		 */
		cy.wpCli('wp elasticpress sync --yes').its('stdout').should('contain', 'Success: Done!');
		cy.wait(500); // eslint-disable-line cypress/no-unnecessary-waiting

		/**
		 * Search for the post again. No results should be returned.
		 */
		cy.visit('/?s=supercustomtitle');
		cy.get('.entry-title').should('not.exist');
	});

	/**
	 * Test that adjusting weighting influences search results as expected.
	 */
	it('Can increase post_title weighting and influence search results', () => {
		cy.login();

		cy.wpCliEval(
			`wp_insert_post(
				[
					'post_title'   => 'test weighting content',
					'post_content' => 'findbyweighting findbyweighting findbyweighting',
					'post_status'  => 'publish',
					'meta_input'   => [
						'_weighting_tests' => 1,
					],
				]
			);

			wp_insert_post(
				[
					'post_title'   => 'test weighting title findbyweighting',
					'post_content' => 'Nothing here.',
					'post_status'  => 'publish',
					'meta_input'   => [
						'_weighting_tests' => 1,
					],
				]
			);`,
		);

		/**
		 * Sync.
		 */
		cy.wpCli('wp elasticpress sync --yes').its('stdout').should('contain', 'Success: Done!');
		cy.wait(500); // eslint-disable-line cypress/no-unnecessary-waiting

		/**
		 * Search for the test posts. Both should be returned.
		 */
		cy.visit('/?s=findbyweighting');
		cy.get('.entry-title').contains('test weighting content').should('exist');
		cy.get('.entry-title').contains('test weighting title findbyweighting').should('exist');

		/**
		 * Adjust the weighting of the title.
		 */
		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('.components-panel__header')
			.contains('Posts')
			.closest('.components-panel')
			.find('fieldset')
			.contains('Title')
			.closest('fieldset')
			.find('input[type="number"]')
			.clearThenType(20);

		/**
		 * Save weighting settings.
		 */
		cy.intercept('/wp-json/elasticpress/v1/weighting*').as('apiRequest');
		cy.contains('button', 'Save changes').click();
		cy.wait('@apiRequest');

		/**
		 * Sync.
		 */
		cy.wpCli('wp elasticpress sync --yes').its('stdout').should('contain', 'Success: Done!');
		cy.wait(500); // eslint-disable-line cypress/no-unnecessary-waiting

		/**
		 * Search for the posts again. The post with the search term in the
		 * title should be returned first.
		 */
		cy.visit('/?s=findbyweighting');
		cy.get('.entry-title')
			.first()
			.should('contain.text', 'test weighting title findbyweighting');
		cy.get('.entry-title').last().should('contain.text', 'test weighting content');
	});

	/**
	 * Test that searching meta fields works as expected.
	 */
	it('Can add, weight and search meta fields', () => {
		/**
		 * Create a post with a custom field with a specific value and a post
		 * with the value as content for weighting comparison purposes.
		 */
		cy.wpCliEval(
			`wp_insert_post(
				[
					'post_title'   => 'Test meta weighting, post meta',
					'post_content' => '',
					'post_status'  => 'publish',
					'meta_input'   => [
						'_weighting_tests' => 1,
						'_my_custom_field' => 'abc123',
					],
				]
			);

			wp_insert_post(
				[
					'post_title'   => 'Test meta weighting, post content',
					'post_content' => 'abc123',
					'post_status'  => 'publish',
					'meta_input'   => [
						'_weighting_tests' => 1,
					],
				]
			);`,
		);

		/**
		 * Sync.
		 */
		cy.wpCli('wp elasticpress sync --yes').its('stdout').should('contain', 'Success: Done!');
		cy.wait(500); // eslint-disable-line cypress/no-unnecessary-waiting

		/**
		 * Only the post with the value in its content should appear.
		 */
		cy.visit('/?s=abc123');
		cy.get('.entry-title').contains('Test meta weighting, post content').should('exist');
		cy.get('.entry-title').contains('Test meta weighting, post meta').should('not.exist');

		/**
		 * Add the custom field to the posts index.
		 */
		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('.components-panel__header')
			.contains('Posts')
			.closest('.components-panel')
			.as('panel')
			.find('fieldset')
			.contains('Content')
			.closest('fieldset')
			.find('input[type="number"]')
			.clearThenType(100);
		cy.get('@panel').find('button').contains('Metadata').click();
		cy.get('@panel').find('input[type="text"]').clearThenType('_my_custom_field');
		cy.get('@panel').find('button').contains('Add').click();
		cy.get('@panel')
			.find('fieldset')
			.contains('_my_custom_field')
			.closest('fieldset')
			.find('input[type="checkbox"]')
			.check();

		/**
		 * Save weighting settings.
		 */
		cy.intercept('/wp-json/elasticpress/v1/weighting*').as('apiRequest');
		cy.contains('button', 'Save changes').click();
		cy.wait('@apiRequest');

		/**
		 * Sync.
		 */
		cy.wpCli('wp elasticpress sync --yes').its('stdout').should('contain', 'Success: Done!');
		cy.wait(500); // eslint-disable-line cypress/no-unnecessary-waiting

		/**
		 * Both results should be returned, but the post with the value in its
		 * content should be returned first.
		 */
		cy.visit('/?s=abc123');
		cy.get('.entry-title').first().should('contain.text', 'Test meta weighting, post content');
		cy.get('.entry-title').last().should('contain.text', 'Test meta weighting, post meta');

		/**
		 * Update the weighting so the meta field is weighted higher.
		 */
		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('.components-panel__body-title').contains('Metadata').should('exist');
		cy.get('.components-panel__header')
			.contains('Posts')
			.closest('.components-panel')
			.as('panel')
			.find('fieldset')
			.contains('Content')
			.closest('fieldset')
			.find('input[type="number"]')
			.clearThenType(1);
		cy.get('@panel')
			.find('fieldset')
			.contains('_my_custom_field')
			.closest('fieldset')
			.find('input[type="number"]')
			.clearThenType(100);

		/**
		 * Save weighting settings.
		 */
		cy.intercept('/wp-json/elasticpress/v1/weighting*').as('apiRequest');
		cy.contains('button', 'Save changes').click();
		cy.wait('@apiRequest');

		/**
		 * The post with the value in its content should be now be returned
		 * first.
		 */
		cy.visit('/?s=abc123');
		cy.get('.entry-title').first().should('contain.text', 'Test meta weighting, post meta');
		cy.get('.entry-title').last().should('contain.text', 'Test meta weighting, post content');

		/**
		 * Enable automatic indexing of meta management and sync.
		 */
		cy.activatePlugin('auto-meta-mode', 'wpCli');
		cy.wpCli('wp elasticpress sync --yes').its('stdout').should('contain', 'Success: Done!');
		cy.wait(500); // eslint-disable-line cypress/no-unnecessary-waiting

		/**
		 * Weighting settings for custom fields should not be available when
		 * using automatic management.
		 */
		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('.components-panel__body-title').contains('Metadata').should('not.exist');

		/**
		 * With automatic meta management the post with a value in a public key
		 * should be returned, but the post with the value in a protected key
		 * should not be.
		 */
		cy.visit('/?s=abc123');
		cy.get('.entry-title').contains('Test meta weighting, post content').should('exist');
		cy.get('.entry-title').contains('Test meta weighting, post meta').should('exist');
	});
});
