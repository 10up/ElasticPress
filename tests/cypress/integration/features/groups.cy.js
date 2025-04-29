describe('Feature Grouping', () => {
	/**
	 * CSS selector for the tabs container within the ElasticPress dashboard.
	 * @constant {string}
	 */
	const tabContainerSelector =
		'#ep-dashboard .ep-dashboard-outer-tabs > .components-tab-panel__tabs';

	beforeEach(() => {
		/**
		 * Log in to WordPress admin.
		 */
		cy.login();

		/**
		 * Navigate to the ElasticPress settings page.
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress');

		/**
		 * Alias the tabs container for reuse in tests.
		 */
		cy.get(tabContainerSelector).as('tabsContainer');
	});

	it('renders feature group tabs and handles tab switching', () => {
		/**
		 * Test 1: Verifies the tabs container exists, is visible,
		 * and contains at least one feature tab.
		 */
		cy.get('@tabsContainer')
			.should('exist')
			.and('be.visible')
			.find('button')
			.should('have.length.at.least', 1);

		/**
		 * Test 2: Clicks the second tab and verifies the corresponding
		 * panel is opened by checking the data-open attribute.
		 */
		// eslint-disable-next-line cypress/unsafe-to-chain-command
		cy.get('@tabsContainer')
			.find('button')
			.eq(1)
			.click()
			.then(($btn) => {
				// eslint-disable-next-line cypress/no-unnecessary-waiting
				cy.wait(1200);
				/** @type {string} ID of the clicked tab button. */
				const buttonId = $btn.attr('id');
				// eslint-disable-next-line no-unused-expressions
				expect(buttonId, 'tab button id').to.be.a('string').and.not.be.empty;

				/**
				 * Create the panel ID and escape any special characters (including spaces).
				 * Uses the built-in CSS.escape() to safely escape the ID value.
				 * @constant {string}
				 */
				const rawPanelId = `${buttonId}-view`;
				const escapedPanelId = CSS.escape(rawPanelId);

				/**
				 * Construct the panel selector using the escaped ID.
				 * @constant {string}
				 */
				const panelSelector = `#${escapedPanelId}`;

				/**
				 * Assert that the panel element has data-open="true".
				 */
				cy.get(panelSelector)
					.should('exist')
					.and('be.visible')
					.should('have.attr', 'data-open', 'true');
			});
	});
});
