describe('Documents Feature', () => {
	function enableDocumentsFeature() {
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.intercept('/wp-json/elasticpress/v1/features*').as('apiRequest');

		cy.contains('button', 'Documents').click();
		cy.contains('label', 'Enable').click();
		cy.contains('button', 'Save changes').click();

		cy.wait('@apiRequest');
	}

	function uploadFile(fileName, mimeType) {
		cy.visitAdminPage('media-new.php?browser-uploader');
		cy.fixture(fileName, 'binary')
			.then(Cypress.Blob.binaryStringToBlob)
			.then((fileContent) => {
				cy.get('#async-upload').attachFile({
					fileContent,
					fileName,
					mimeType,
					encoding: 'utf8',
					lastModified: new Date().getTime(),
				});
			});
		cy.get('#html-upload').click();

		/**
		 * Give Elasticsearch some time to process the file.
		 *
		 * @todo instead of waiting for an arbitrary time, we should ensure the file is processed.
		 */
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(2000);
	}

	before(() => {
		cy.wpCli('elasticpress sync --setup --yes');
		cy.exec(
			'npm run env run tests-wordpress "chown -R www-data:www-data /var/www/html/wp-content/uploads"',
		);
	});

	beforeEach(() => {
		cy.maybeDisableFeature('documents');
	});

	it('Can search .pdf', () => {
		cy.login();
		enableDocumentsFeature();

		// Check if the file is searchable right after the upload.
		uploadFile('pdf-file.pdf', 'application/pdf');
		cy.visit('/?s=dummy+pdf');
		cy.get('body').should('contain.text', 'pdf-file');

		// Check if the file is still searchable after a reindex.
		cy.wpCli('elasticpress sync --setup --yes --show-errors').then(() => {
			/**
			 * Give Elasticsearch some time. Apparently, if the visit happens right after the index, it won't find anything.
			 *
			 * @todo instead of waiting for an arbitrary time, we should ensure the file is processed.
			 */
			// eslint-disable-next-line cypress/no-unnecessary-waiting
			cy.wait(1000);

			cy.visit('/?s=dummy+pdf');
			cy.get('.hentry').should('contain.text', 'pdf-file');
		});
	});

	it('Can search .pptx', () => {
		cy.login();
		enableDocumentsFeature();
		uploadFile(
			'pptx-file.pptx',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		);

		cy.visit('/?s=dummy+slide');

		cy.get('.hentry').should('contain.text', 'pptx-file');
	});
});
