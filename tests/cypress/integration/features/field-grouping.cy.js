/**
 * Test suite for verifying conditional feature visibility
 */
describe('Feature Visibility Tests', () => {
	/**
	 * Test case to verify a conditional feature is hidden until its requirement is met
	 *
	 * This test verifies the visibility behavior of a conditional feature
	 * dependent on a specific requirement selection.
	 *
	 * Testing steps:
	 * 1. Navigate to the Core Search section
	 * 2. Navigate to the Post Search feature
	 * 3. Verify the conditional feature is initially hidden
	 * 4. Verify the requirement option exists and is checked by default
	 * 5. Toggle the requirement option off and verify the feature appears
	 * 6. Toggle the requirement option on again and verify the feature disappears
	 */
	it('should hide conditional feature until specific requirement is selected', () => {
		/**
		 * Log in to WordPress admin.
		 */
		cy.login();

		/**
		 * Navigate to the ElasticPress dashboard page
		 */
		cy.visit('/wp-admin/admin.php?page=elasticpress');

		/**
		 * Navigate to the correct feature group and subfeature
		 */
		cy.contains('button', 'Core Search').click();
		cy.contains('button', 'Post Search').click();

		// eslint-disable-next-line cypress/unsafe-to-chain-command
		cy.contains('.components-radio-control__option', "Don't weight results by date")
			.find('input')
			.click()
			.then(() => {
				/**
				 * Test data for the conditional feature
				 * @type {object}
				 * @property {string} slug - The feature's internal identifier
				 * @property {string} text - The feature's display text
				 */
				const conditionalFeature = {
					slug: 'highlight_enabled',
					text: 'Enable to wrap search terms',
				};

				/**
				 * Test data for the requirement that controls visibility
				 * @type {object}
				 * @property {string} text - The requirement's display text
				 */
				const requirements = {
					text: 'Weight results by date',
				};

				/**
				 * Step 1: Verify that the conditional feature is not initially visible
				 */
				cy.contains('.ep-dashboard-control', conditionalFeature.text).should('not.exist');

				/**
				 * Step 2: Verify that the requirement option exists in the UI
				 * Step 3: Verify that the requirement is selected by default
				 */
				cy.contains('.components-radio-control__option', requirements.text)
					.should('exist')
					.within(() => {
						cy.get('input[type="radio"]').should('have.attr', 'checked');
					});

				/**
				 * Additional verification steps
				 */

				/**
				 * Toggle the requirement to see how it affects the conditional feature
				 */
				cy.contains('.components-radio-control__option', requirements.text)
					.find('input')
					.click();

				/**
				 * Verify the conditional feature becomes visible when requirement is toggled
				 */
				cy.contains('.ep-dashboard-control', conditionalFeature.text).should('be.visible');

				/**
				 * Toggle the wrong requirement to verify the opposite behavior
				 */
				cy.contains('.components-radio-control__option', "Don't weight results by date")
					.find('input')
					.click();

				/**
				 * Verify the conditional feature is hidden again after toggling
				 */
				cy.contains('.ep-dashboard-control', conditionalFeature.text).should('not.exist');
			});
	});
});
