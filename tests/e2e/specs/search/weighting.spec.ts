import { test, expect } from '../../fixtures';
import { wpCli, activatePlugin, goToAdminPage, updateWeighting, wpCliEval } from '../../utils';

test.describe('Post Search Feature - Weighting Functionality', () => {
	const sync = async (page) => {
		await wpCli('wp elasticpress sync --yes');
		await page.waitForTimeout(2000);
	};

	test.beforeAll(async () => {
		await wpCliEval(`
			$posts = new \\WP_Query(
				[
					'post_type'    => 'post',
					'meta_key'     => '_weighting_tests',
					'meta_value'   => '1',
					'ep_integrate' => false,
				]
			);
			foreach ( $posts->posts as $post ) {
				wp_delete_post( $post->ID, true );
			}
		`);
	});

	test.beforeEach(async () => {
		await wpCli('wp plugin deactivate auto-meta-mode');
		updateWeighting();
	});

	test("Can't find a post by title if title is not marked as searchable", async ({
		loggedInPage,
	}) => {
		await wpCliEval(`
			wp_insert_post([
				'post_title'   => 'supercustomtitle',
				'post_content' => '',
				'post_status'  => 'publish',
				'meta_input'   => [
					'_weighting_tests' => 1,
				],
			]);
		`);
		const result = await wpCli('wp elasticpress sync --yes');
		expect(result.toString()).toContain('Done!');

		await loggedInPage.goto('/?s=supercustomtitle');
		await expect(
			loggedInPage.locator('.entry-title', { hasText: 'supercustomtitle' }),
		).toBeVisible();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-weighting');
		await loggedInPage
			.locator(
				'.components-panel:has(.components-panel__header:has-text("Posts")) fieldset:has-text("Title") input[type="checkbox"]',
			)
			.uncheck();

		const weightingResponsePromise = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/weighting*',
		);
		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
		await weightingResponsePromise;

		await sync(loggedInPage);
		await loggedInPage.goto('/?s=supercustomtitle');
		await expect(loggedInPage.locator('.entry-title')).not.toBeVisible();
	});

	test('Can increase post_title weighting and influence search results', async ({
		loggedInPage,
	}) => {
		await wpCliEval(`
			wp_insert_post([
				'post_title'   => 'test weighting content',
				'post_content' => 'findbyweighting findbyweighting findbyweighting',
				'post_status'  => 'publish',
				'meta_input'   => [
					'_weighting_tests' => 1,
				],
			]);
			wp_insert_post([
				'post_title'   => 'test weighting title findbyweighting',
				'post_content' => 'Nothing here.',
				'post_status'  => 'publish',
				'meta_input'   => [
					'_weighting_tests' => 1,
				],
			]);
		`);
		await sync(loggedInPage);
		await loggedInPage.goto('/?s=findbyweighting');
		await expect(
			loggedInPage.locator('.entry-title', { hasText: 'test weighting content' }),
		).toBeVisible();
		await expect(
			loggedInPage.locator('.entry-title', {
				hasText: 'test weighting title findbyweighting',
			}),
		).toBeVisible();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-weighting');
		await loggedInPage
			.locator(
				'.components-panel:has(.components-panel__header:has-text("Posts")) fieldset:has-text("Title") input[type="number"]',
			)
			.fill('20');

		const weightingResponsePromise = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/weighting*',
		);
		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
		await weightingResponsePromise;

		await sync(loggedInPage);
		await loggedInPage.goto('/?s=findbyweighting');
		const firstTitle = await loggedInPage.locator('.entry-title').first().textContent();
		const lastTitle = await loggedInPage.locator('.entry-title').last().textContent();
		expect(firstTitle).toContain('test weighting title findbyweighting');
		expect(lastTitle).toContain('test weighting content');
	});

	test('Can add, weight and search meta fields', async ({ loggedInPage }) => {
		await wpCliEval(`
			wp_insert_post([
				'post_title'   => 'Test meta weighting, post meta',
				'post_content' => '',
				'post_status'  => 'publish',
				'meta_input'   => [
					'_weighting_tests' => 1,
					'_my_custom_field' => 'abc123',
				],
			]);
			wp_insert_post([
				'post_title'   => 'Test meta weighting, post content', 
				'post_content' => 'abc123',
				'post_status'  => 'publish',
				'meta_input'   => [
					'_weighting_tests' => 1,
				],
			]);
		`);
		await sync(loggedInPage);
		await loggedInPage.goto('/?s=abc123');
		await expect(loggedInPage.locator('.entry-title')).toContainText(
			'Test meta weighting, post content',
		);
		await expect(loggedInPage.locator('.entry-title')).not.toContainText(
			'Test meta weighting, post meta',
		);

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-weighting');
		const postPanel = loggedInPage.locator('.components-panel', {
			has: loggedInPage.locator('.components-panel__header', { hasText: 'Posts' }),
		});
		await postPanel.locator('fieldset:has-text("Content") input[type="number"]').fill('100');
		await postPanel.getByRole('button', { name: 'Metadata' }).click();
		const metaDataPanel = postPanel.locator('.components-panel__body', {
			has: loggedInPage.getByRole('button', { name: 'Metadata' }),
		});
		await metaDataPanel.locator('input[type="text"]').fill('_my_custom_field');
		await metaDataPanel.getByRole('button', { name: 'Add' }).click();
		await metaDataPanel
			.locator('fieldset:has-text("_my_custom_field") input[type="checkbox"]')
			.check();

		const weightingResponsePromise = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/weighting*',
		);
		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
		await weightingResponsePromise;

		await sync(loggedInPage);
		await loggedInPage.goto('/?s=abc123');
		const firstMetaTitle = await loggedInPage.locator('.entry-title').first().textContent();
		const lastMetaTitle = await loggedInPage.locator('.entry-title').last().textContent();
		expect(firstMetaTitle).toContain('Test meta weighting, post content');
		expect(lastMetaTitle).toContain('Test meta weighting, post meta');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-weighting');
		await postPanel.locator('fieldset:has-text("Content") input[type="number"]').fill('1');
		await postPanel.getByRole('button', { name: 'Metadata' }).click();
		await metaDataPanel
			.locator('fieldset:has-text("_my_custom_field") input[type="number"]')
			.fill('100');

		const weightingResponsePromise2 = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/weighting*',
		);
		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
		await weightingResponsePromise2;

		await loggedInPage.goto('/?s=abc123');
		const firstMetaTitle2 = await loggedInPage.locator('.entry-title').first().textContent();
		const lastMetaTitle2 = await loggedInPage.locator('.entry-title').last().textContent();
		expect(firstMetaTitle2).toContain('Test meta weighting, post meta');
		expect(lastMetaTitle2).toContain('Test meta weighting, post content');

		await activatePlugin(loggedInPage, 'auto-meta-mode', 'wpCli');
		await sync(loggedInPage);
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-weighting');
		await expect(
			loggedInPage.locator('.components-panel__body-title:has-text("Metadata")'),
		).not.toBeVisible();
		await loggedInPage.goto('/?s=abc123');
		await expect(loggedInPage.locator('.entry-title')).toContainText(
			'Test meta weighting, post content',
		);
		await expect(loggedInPage.locator('.entry-title')).not.toContainText(
			'Test meta weighting, post meta',
		);
	});
});
