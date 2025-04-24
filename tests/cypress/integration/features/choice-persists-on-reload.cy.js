describe('Feature Selection Persistence', () => {
	/**
	 * @constant {string} OUTER_TAB_CONTAINER - Selector for the group tabs container.
	 */
	const outerTabContainerSelector =
		'#ep-dashboard .ep-dashboard-outer-tabs .components-tab-panel__tabs';

	/**
	 * @constant {string} INNER_TAB_CONTAINER - Selector for the feature tabs container.
	 */
	const innerTabContainerSelector =
		'#ep-dashboard .ep-dashboard-tabs .components-tab-panel__tabs';

	/**
	 * @type {string} savedGroupId - ID of the selected group tab.
	 */
	let savedGroupId;

	/**
	 * @type {string} savedFeatureId - ID of the selected feature tab.
	 */
	let savedFeatureId;

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
		 * Alias the tabs containers for reuse in tests.
		 */
		cy.get(outerTabContainerSelector).as('groupTabsContainer');
		cy.get(innerTabContainerSelector).as('featureTabsContainer');
	});

	/**
	 * Verifies that the group and feature tabs are clickable and persist their selections.
	 */
	it('allows selecting groups and features, and persists selections on reload', () => {
		// First select a group (for example, the second group)
		// eslint-disable-next-line cypress/unsafe-to-chain-command
		cy.get('@groupTabsContainer')
			.find('button')
			.eq(1) // Select the second group
			.click()
			.invoke('attr', 'id')
			.then((id) => {
				savedGroupId = id;

				// Now that we've selected a group, select a feature within it (for example, the second feature)
				// eslint-disable-next-line cypress/unsafe-to-chain-command
				cy.get('@featureTabsContainer')
					.find('button')
					.eq(1) // Select the second feature
					.click()
					.invoke('attr', 'id')
					.then((featureId) => {
						savedFeatureId = featureId;

						// Verify the feature is active
						cy.get(`#${CSS.escape(featureId)}`).should(
							'have.attr',
							'data-active-item',
							'true',
						);

						// Reload the page to test persistence
						cy.reload();

						// Wait for UI to load completely
						cy.get(outerTabContainerSelector).should('be.visible');
						cy.get(innerTabContainerSelector).should('be.visible');

						// Verify group selection persisted
						cy.get(`#${CSS.escape(savedGroupId)}`).should(
							'have.attr',
							'data-active-item',
							'true',
						);

						// Verify feature selection persisted
						cy.get(`#${CSS.escape(savedFeatureId)}`).should(
							'have.attr',
							'data-active-item',
							'true',
						);
					});
			});
	});
});
