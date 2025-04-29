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
		 * Alias the tabs container. Add a visibility check here
		 * to ensure the main container is ready before tests run.
		 */
		cy.get(tabContainerSelector).should('be.visible').as('tabsContainer');
	});

	it('renders feature group tabs and handles tab switching', () => {
		/**
		 * Test 1: Verifies the tabs container exists, is visible,
		 * and contains at least one feature tab.
		 */
		cy.get('@tabsContainer').should('exist').find('button').should('have.length.at.least', 1);

		/**
		 * Test 2: Clicks the second tab and verifies the corresponding
		 * panel is opened.
		 */

		// Find the second tab button
		cy.get('@tabsContainer').find('button').eq(1).as('secondTab');

		// Wait for the button to be interactable
		cy.get('@secondTab').should('be.visible').and('not.be.disabled').click();

		/**
		 * Get the button ID and verify the corresponding panel
		 */
		cy.get('@secondTab')
			.invoke('attr', 'id')
			.then((buttonId) => {
				/** @type {string} ID of the clicked tab button. */
				// eslint-disable-next-line no-unused-expressions
				expect(buttonId, 'tab button id').to.be.a('string').and.not.be.empty;

				/**
				 * Create the panel ID.
				 * Use Cypress escaping for IDs if they might contain special characters,
				 * although in this case it looks standard.
				 * @constant {string}
				 */
				const panelSelector = `#${buttonId}-view`; // Simplified selector construction

				// *** IMPROVEMENT 2: Refined wait for the panel state ***
				// Cypress will retry the *entire* `should` chain within the timeout.
				// Waiting for existence, visibility, AND the attribute ensures
				// all conditions are met before proceeding.
				cy.get(panelSelector, { timeout: 15000 }) // Increased timeout slightly just in case
					.should('exist') // Wait for the element to be in the DOM
					.and('be.visible') // Wait for it to be visible (no CSS display:none, etc.)
					.and('have.attr', 'data-open', 'true'); // Crucially, wait for the attribute change
			});
	});
});
