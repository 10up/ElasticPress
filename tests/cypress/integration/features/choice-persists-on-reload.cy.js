describe('Feature Selection Persistence', () => {
	/**
	 * @constant {string} TAB_CONTAINER - Selector for the feature tabs container.
	 */
	const tabContainerSelector = '#ep-dashboard .components-tab-panel__tabs';

	/**
	 * @type {string} savedTabId - ID of the last clicked tab.
	 */
	let savedTabId;

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

	/**
	 * Verifies that the tabs are clickable and change their content.
	 */
	it('has clickable tabs that change their content', () => {
		// eslint-disable-next-line cypress/unsafe-to-chain-command
		cy.get('@tabsContainer')
			.find('button#tab-panel-0-did-you-mean')
			.click()
			.invoke('attr', 'id')
			.then((id) => {
				savedTabId = id;
				const viewId = `${id}-view`;
				const selector = `#${CSS.escape(viewId)}`;
				cy.get(selector).should('have.attr', 'data-open', 'true');
			});
	});

	/**
	 * Verifies that the last selected feature is retained on page reload.
	 */
	it('retains its last selected feature on page reload', () => {
		cy.reload();
		const selector = `#${CSS.escape(savedTabId)}`;
		cy.get(selector).should('have.attr', 'data-active-item', 'true');
	});
});
