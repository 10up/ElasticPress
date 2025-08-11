import { test, expect, Page } from '../fixtures.js';
import { goToAdminPage, getPluginRootDir } from '../utils.js';

test.describe('ACF Repeater Field Compatibility Feature', { tag: '@paidPlugins' }, () => {
	const setupFieldGroup = async (loggedInPage: Page) => {
		// Import ACF field group
		await goToAdminPage(loggedInPage, 'edit.php?post_type=acf-field-group&page=acf-tools');

		// Create a file input and upload the JSON file
		const fileChooserPromise = loggedInPage.waitForEvent('filechooser');
		await loggedInPage.locator('#acf_import_file').click();
		const fileChooser = await fileChooserPromise;
		await fileChooser.setFiles(
			`${getPluginRootDir()}/tests/e2e/src/fixtures/acf-repeater-field-test.json`,
		);

		// Click the Import JSON button
		await loggedInPage.getByRole('button', { name: 'Import JSON' }).click();
	};

	test('Can index an ACF Repeater Field', async ({ loggedInPage }) => {
		await setupFieldGroup(loggedInPage);

		// Check ElasticPress controls in the ACF group edit screen
		await goToAdminPage(loggedInPage, 'edit.php?post_type=acf-field-group');
		await loggedInPage.locator('a[aria-label="Edit “Repeater Test”"]').dispatchEvent('click');

		// Check that we have the expected repeater fields
		await loggedInPage.locator('.edit-field').first().click();
		await expect(
			loggedInPage.locator('.acf-field-setting-ep_acf_repeater_index_field').first(),
		).toBeVisible();
		await expect(loggedInPage.locator('.acf-field-object-repeater')).toHaveCount(3);
		await expect(
			loggedInPage.locator('.acf-field-setting-ep_acf_repeater_index_field'),
		).toHaveCount(2); // We have 3 repeaters, but nested repeats do not get a toggle

		// Enable the first repeater field for indexing
		const firstRepeaterToggle = loggedInPage
			.locator('.acf-field-setting-ep_acf_repeater_index_field')
			.first()
			.locator('input[type="checkbox"]');

		const isChecked = await firstRepeaterToggle.isChecked();
		if (!isChecked) {
			await firstRepeaterToggle.check({ force: true });
		}

		await loggedInPage.locator('button.acf-publish').click();

		// Save the example post, so the repeater field is indexed
		await goToAdminPage(loggedInPage, 'edit.php?s=Post+with+ACF+Repeater+Field');
		await loggedInPage.locator('span.edit a').dispatchEvent('click');

		// Close welcome guide if it appears
		const welcomeGuide = loggedInPage.locator(
			'.edit-post-welcome-guide .components-modal__header button',
		);
		if (await welcomeGuide.isVisible()) {
			await welcomeGuide.click();
		}

		await loggedInPage.locator('.editor-post-publish-button__button').click();
		await loggedInPage.waitForTimeout(2000);

		// Make the field searchable
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-weighting');

		const postBox = loggedInPage
			.locator('.ep-weighting-post-type')
			.filter({ hasText: 'Posts' });
		const metadataToggle = postBox
			.locator('button.components-panel__body-toggle')
			.filter({ hasText: 'Metadata' });

		// Expand metadata section if collapsed
		const isExpanded = await metadataToggle.getAttribute('aria-expanded');
		if (isExpanded !== 'true') {
			await metadataToggle.click();
		}

		// Enable the repeater field for search
		await expect(
			loggedInPage
				.locator('.ep-weighting-field__name')
				.filter({ hasText: 'repeater_test_1' }),
		).toBeVisible();
		await loggedInPage
			.locator('.ep-weighting-field__name')
			.filter({ hasText: 'repeater_test_1' })
			.locator('xpath=..')
			.locator('input[type="checkbox"]')
			.check();

		await loggedInPage.locator('button[type="submit"]').click();

		// Search using the field
		await loggedInPage.goto('/?s=Grandchild%201.1');
		await expect(
			loggedInPage
				.locator('.site-content article h2')
				.filter({ hasText: 'Post with ACF Repeater Field' }),
		).toBeVisible();
		await loggedInPage.locator('.site-content article a').first().click();

		// Check debug bar for ElasticPress data
		await loggedInPage.locator('#wpadminbar li#wp-admin-bar-debug-bar').click();
		await loggedInPage.locator('#debug-menu-link-EP_Debug_Bar_ElasticPress').click();
		await loggedInPage
			.locator('a')
			.filter({ hasText: 'Reload and retrieve raw ES document' })
			.click();

		await loggedInPage.locator('#wpadminbar li#wp-admin-bar-debug-bar').click();
		await loggedInPage.locator('#debug-menu-link-EP_Debug_Bar_ElasticPress').click();

		// Verify the indexed data contains the expected repeater field content
		await expect(loggedInPage.locator('.query-results').first()).toContainText(
			'[{\\"child_1\\":\\"Repeater Child 1\\",\\"child_repeater\\":[{\\"grandchild_1\\":\\"Grandchild 1\\"},{\\"grandchild_1\\":\\"Grandchild 2\\"}]},{\\"child_1\\":\\"Repeater Child 2\\",\\"child_repeater\\":[{\\"grandchild_1\\":\\"Grandchild 1.1\\"}]}]',
		);

		// Verify that the second repeater field is not indexed (as expected)
		await expect(loggedInPage.locator('.query-results').first()).not.toContainText(
			'Repeater Test 2 Textarea',
		);
	});
});
