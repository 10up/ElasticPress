import { execFileSync } from 'child_process';

import { test, expect, Page } from '../fixtures.js';
import {
	wpCli,
	wpCliEval,
	maybeDisableFeature,
	goToAdminPage,
	getPluginRootDir,
	refreshIndex,
	getSyncTimeout,
} from '../utils.js';

test.describe('Documents Feature', { tag: '@group1' }, () => {
	test.describe.configure({ timeout: 120000 });

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

	async function countAttachments() {
		return Number(
			await wpCli('post list --post_type=attachment --post_status=inherit --format=count'),
		);
	}

	async function uploadFile(page: Page, fileName: string) {
		const attachmentsBefore = await countAttachments();

		await goToAdminPage(page, 'media-new.php?browser-uploader');

		await page
			.locator('#async-upload')
			.setInputFiles(`${getPluginRootDir()}/tests/e2e/src/fixtures/${fileName}`);

		// Submitting the form navigates, so the upload request has to be awaited
		// here: any navigation that follows would cancel it, leaving the file
		// unattached and the searches below without results.
		await Promise.all([
			page.waitForResponse(
				(response) =>
					response.request().isNavigationRequest() &&
					response.request().method() === 'POST',
			),
			page.locator('#html-upload').click(),
		]);
		await page.waitForLoadState('domcontentloaded');

		// WordPress rejects some file types silently, which would otherwise only
		// show up as an empty search result further down.
		await expect
			.poll(countAttachments, { timeout: getSyncTimeout() })
			.toBeGreaterThan(attachmentsBefore);

		await refreshIndex('post');
	}

	test.beforeAll(async () => {
		await wpCli('elasticpress sync --setup --yes');
		const command = `${getPluginRootDir()}/bin/wp-env-cli`;
		const uploadsDir = '/var/www/html/wp-content/uploads';
		execFileSync(command, ['wordpress', `sudo mkdir -p ${uploadsDir}`], {
			timeout: 30000,
			maxBuffer: 10 * 1024 * 1024,
		});
		execFileSync(command, ['wordpress', `sudo chmod -R 777 ${uploadsDir}`], {
			timeout: 30000,
			maxBuffer: 10 * 1024 * 1024,
		});
	});

	test.beforeEach(async () => {
		await maybeDisableFeature('documents');
	});

	test('Can search .pdf', async ({ loggedInPage }) => {
		await enableDocumentsFeature(loggedInPage);

		// Check if the file is searchable right after the upload
		await uploadFile(loggedInPage, 'pdf-file.pdf');
		await loggedInPage.goto('/?s=dummy+pdf');
		await expect(loggedInPage.locator('body')).toContainText('pdf-file', {
			timeout: getSyncTimeout(),
		});

		// Check if the file is still searchable after a reindex
		await wpCli('elasticpress sync --setup --yes --show-errors');
		await refreshIndex('post');

		await loggedInPage.goto('/?s=dummy+pdf');
		await expect(loggedInPage.locator('.hentry').first()).toContainText('pdf-file', {
			timeout: getSyncTimeout(),
		});
	});

	test('Can search .pptx, .txt, and .csv files', async ({ loggedInPage }) => {
		await enableDocumentsFeature(loggedInPage);

		await uploadFile(loggedInPage, 'pptx-file.pptx');

		await loggedInPage.goto('/?s=dummy+slide');
		await expect(loggedInPage.locator('.hentry').first()).toContainText('pptx-file', {
			timeout: getSyncTimeout(),
		});

		// Multisite only accepts the file types listed for the network, and txt and
		// csv are not among them. ALLOW_UNFILTERED_UPLOADS lifts that restriction,
		// but it lives in wp-config.php, which PHP serves from opcache, so the
		// upload can still be rejected. This option applies to the next request.
		const allowedTypes = (await wpCliEval(`echo get_site_option( 'upload_filetypes', '' );`))
			.toString()
			.trim();
		await wpCliEval(`update_site_option( 'upload_filetypes', '${allowedTypes} txt csv' );`);

		await uploadFile(loggedInPage, 'txt-file.txt');
		await uploadFile(loggedInPage, 'csv-file.csv');

		await loggedInPage.goto('/?s=Curabitur+interdum+id+turpis+ac+viverra');
		await expect(loggedInPage.locator('.hentry').first()).toContainText('txt-file', {
			timeout: getSyncTimeout(),
		});

		await loggedInPage.goto('/?s=Winchester');
		await expect(loggedInPage.locator('.hentry').first()).toContainText('csv-file', {
			timeout: getSyncTimeout(),
		});

		await wpCliEval(`update_site_option( 'upload_filetypes', '${allowedTypes}' );`);
	});
});
