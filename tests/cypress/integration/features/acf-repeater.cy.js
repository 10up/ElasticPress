describe('ACF Repeater Field Compatibility Feature', () => {
	before(() => {
		cy.visitAdminPage('edit.php?post_type=acf-field-group&page=acf-tools');
		cy.fixture('acf-repeater-field-test.json', 'utf8').then((fileContent) => {
			cy.log(fileContent);
			cy.get('#acf_import_file').attachFile({
				fileContent,
				fileName: 'acf-repeater-field-test.json',
				mimeType: 'application/json',
				encoding: 'utf8',
				lastModified: new Date().getTime(),
			});
		});
		cy.contains('button', 'Import JSON').click();
	});

	it('Can index an ACF Repeater Field', () => {
		// Check ElasticPress controls in the ACF group edit screen
		cy.visitAdminPage('edit.php?post_type=acf-field-group');
		cy.get('a[aria-label="Edit “Repeater Test”"]').click({ force: true });

		cy.get('.edit-field').click({ multiple: true, force: true });
		cy.get('.acf-field-object-repeater').should('have.length', 3);
		cy.get('.acf-field-setting-ep_acf_repeater_index_field').should('have.length', 2); // We have 3 repeaters, but nested repeats do not get a toggle

		cy.get('.acf-field-setting-ep_acf_repeater_index_field')
			.first()
			.find('input[type="checkbox"]')
			.then(($el) => {
				if (!$el.is(':checked')) {
					cy.wrap($el).check({ force: true });
				}
			});
		cy.get('button.acf-publish').click();

		// Save the example post, so the repeater field is indexed
		cy.visitAdminPage('edit.php?s=Post+with+ACF+Repeater+Field');
		cy.get('span.edit a').click({ force: true });
		cy.get('.editor-post-publish-button__button').click();
		cy.wait(2000); // eslint-disable-line

		// Make the field searchable
		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.contains('h2', 'Posts').closest('.ep-weighting-post-type').as('postBox');
		cy.get('@postBox')
			.contains('button.components-panel__body-toggle', 'Metadata')
			.then(($el) => {
				if (!$el.prop('aria-expanded')) {
					cy.wrap($el).click();
				}
			});
		cy.contains('.ep-weighting-field__name', 'repeater_test_1').should('exist');
		cy.contains('.ep-weighting-field__name', 'repeater_test_1')
			.closest('fieldset')
			.find('input[type="checkbox"]')
			.check();
		cy.get('button[type="submit"]').click();

		// Search using the field
		cy.visit('/?s=Grandchild%201.1');
		cy.contains('.site-content article h2', 'Post with ACF Repeater Field').should('exist');
		cy.get('.site-content article a').first().click();

		cy.get('#wpadminbar li#wp-admin-bar-debug-bar').click();
		cy.get('#debug-menu-link-EP_Debug_Bar_ElasticPress').click();
		cy.contains('a', 'Reload and retrieve raw ES document').click();

		cy.get('#wpadminbar li#wp-admin-bar-debug-bar').click();
		cy.get('#debug-menu-link-EP_Debug_Bar_ElasticPress').click();
		cy.get('.query-results')
			.first()
			.should(
				'contain.text',
				'[{\\"child_1\\":\\"Repeater Child 1\\",\\"child_repeater\\":[{\\"grandchild_1\\":\\"Grandchild 1\\"},{\\"grandchild_1\\":\\"Grandchild 2\\"}]},{\\"child_1\\":\\"Repeater Child 2\\",\\"child_repeater\\":[{\\"grandchild_1\\":\\"Grandchild 1.1\\"}]}]',
			)
			.should('not.contain.text', 'Repeater Test 2 Textarea');
	});
});
