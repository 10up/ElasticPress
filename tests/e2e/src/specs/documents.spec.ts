import { execFileSync } from 'child_process';

import { test, expect, Page } from '../fixtures';
import { wpCli, maybeDisableFeature, goToAdminPage, getPluginRootDir } from '../utils';

test.describe('Documents Feature', { tag: '@group1' }, () => {
	async function enableDocumentsFeature(page: Page) {
		await goToAdminPage(page, 'admin.php?page=elasticpress');

		// Wait for the API request to complete
		const responsePromise = page.waitForResponse('**/wp-json/elasticpress/v1/features*');

		await page.getByRole('button', { name: 'Indexing Options' }).click();
		await page.getByRole('button', { name: 'Documents' }).click();
		await page.getByLabel('Enable').click();
		await page.getByRole('button', { name: 'Save changes' }).click();

		await responsePromise;
	}

	async function uploadFile(page: Page, fileName: string) {
		await goToAdminPage(page, 'media-new.php?browser-uploader');

		// Create a file input and upload the file
		const fileChooserPromise = page.waitForEvent('filechooser');
		await page.locator('#async-upload').click();
		const fileChooser = await fileChooserPromise;
		await fileChooser.setFiles(`${getPluginRootDir()}/tests/e2e/src/fixtures/${fileName}`);

		await page.locator('#html-upload').click();

		// Give Elasticsearch some time to process the file
		await page.waitForTimeout(2000);
	}

	test.beforeAll(async () => {
		await wpCli('elasticpress sync --setup --yes');
		const command = `${getPluginRootDir()}/bin/wp-env-cli`;
		const args = ['tests-wordpress', `sudo chmod -R 777 /var/www/html/wp-content/uploads`];
		execFileSync(command, args);
	});

	test.beforeEach(async () => {
		await maybeDisableFeature('documents');
	});

	test('Can search .pdf', async ({ loggedInPage }) => {
		await enableDocumentsFeature(loggedInPage);

		// Check if the file is searchable right after the upload
		await uploadFile(loggedInPage, 'pdf-file.pdf');
		await loggedInPage.goto('/?s=dummy+pdf');
		await expect(loggedInPage.locator('body')).toContainText('pdf-file');

		// Check if the file is still searchable after a reindex
		await wpCli('elasticpress sync --setup --yes --show-errors');

		// Give Elasticsearch some time to process
		await loggedInPage.waitForTimeout(1000);

		await loggedInPage.goto('/?s=dummy+pdf');
		await expect(loggedInPage.locator('.hentry').first()).toContainText('pdf-file');
	});

	test('Can search .pptx, .txt, and .csv files', async ({ loggedInPage }) => {
		await enableDocumentsFeature(loggedInPage);

		await uploadFile(loggedInPage, 'pptx-file.pptx');

		await loggedInPage.goto('/?s=dummy+slide');
		await expect(loggedInPage.locator('.hentry').first()).toContainText('pptx-file');

		await wpCli('config set ALLOW_UNFILTERED_UPLOADS true --raw');

		await uploadFile(loggedInPage, 'txt-file.txt');
		await uploadFile(loggedInPage, 'csv-file.csv');

		await loggedInPage.goto('/?s=Curabitur+interdum+id+turpis+ac+viverra');
		await expect(loggedInPage.locator('.hentry').first()).toContainText('txt-file');

		await loggedInPage.goto('/?s=Winchester');
		await expect(loggedInPage.locator('.hentry').first()).toContainText('csv-file');

		await wpCli('config set ALLOW_UNFILTERED_UPLOADS false --raw');
	});
});
