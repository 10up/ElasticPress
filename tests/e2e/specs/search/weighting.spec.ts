import { test, expect } from '../../fixtures';
import {
	wpCli,
	activatePlugin,
	goToAdminPage,
	clearThenType,
	updateWeighting,
	wpCliEval,
} from '../../utils';

test.describe('Post Search Feature - Weighting Functionality', () => {
	test.beforeAll(async () => {
		await wpCliEval(`
			$posts = new WP_Query(
				[
					'post_type'    => 'post',
					'meta_key'     => '_weighting_tests',
					'meta_value'   => '1',
					'ep_integrate' => false,
				]
			);
			foreach ( $posts as $post ) {
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
		await wpCli(
			`eval "wp_insert_post([ 'post_title' => 'supercustomtitle', 'post_content' => '', 'post_status' => 'publish', 'meta_input' => [ '_weighting_tests' => 1 ] ]);"`,
		);
		const result = await wpCli('wp elasticpress sync --yes');
		expect(result.toString()).toContain('Done!');

		await loggedInPage.waitForTimeout(500);
		await loggedInPage.goto('/?s=supercustomtitle');
		await expect(loggedInPage.locator('.entry-title')).toContainText('supercustomtitle');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-weighting');
		await loggedInPage
			.locator('.components-panel__header:has-text("Posts")')
			.closest('.components-panel')
			.locator('fieldset:has-text("Title") input[type="checkbox"]')
			.uncheck();
		await loggedInPage.route('/wp-json/elasticpress/v1/weighting*', (route) =>
			route.continue(),
		);
		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
		await loggedInPage.waitForTimeout(500);
		await wpCli('wp elasticpress sync --yes');
		await loggedInPage.waitForTimeout(500);
		await loggedInPage.goto('/?s=supercustomtitle');
		await expect(loggedInPage.locator('.entry-title')).not.toBeVisible();
	});

	test('Can increase post_title weighting and influence search results', async ({
		loggedInPage,
	}) => {
		await wpCli(
			`eval "wp_insert_post([ 'post_title' => 'test weighting content', 'post_content' => 'findbyweighting findbyweighting findbyweighting', 'post_status' => 'publish', 'meta_input' => [ '_weighting_tests' => 1 ] ]); wp_insert_post([ 'post_title' => 'test weighting title findbyweighting', 'post_content' => 'Nothing here.', 'post_status' => 'publish', 'meta_input' => [ '_weighting_tests' => 1 ] ]);"`,
		);
		await wpCli('wp elasticpress sync --yes');
		await loggedInPage.waitForTimeout(500);
		await loggedInPage.goto('/?s=findbyweighting');
		await expect(loggedInPage.locator('.entry-title')).toContainText('test weighting content');
		await expect(loggedInPage.locator('.entry-title')).toContainText(
			'test weighting title findbyweighting',
		);

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-weighting');
		await clearThenType(
			loggedInPage,
			'.components-panel__header:has-text("Posts") ~ .components-panel fieldset:has-text("Title") input[type="number"]',
			'20',
		);
		await loggedInPage.route('/wp-json/elasticpress/v1/weighting*', (route) =>
			route.continue(),
		);
		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
		await loggedInPage.waitForTimeout(500);
		await wpCli('wp elasticpress sync --yes');
		await loggedInPage.waitForTimeout(500);
		await loggedInPage.goto('/?s=findbyweighting');
		const firstTitle = await loggedInPage.locator('.entry-title').first().textContent();
		const lastTitle = await loggedInPage.locator('.entry-title').last().textContent();
		expect(firstTitle).toContain('test weighting title findbyweighting');
		expect(lastTitle).toContain('test weighting content');
	});

	test('Can add, weight and search meta fields', async ({ loggedInPage }) => {
		await wpCli(
			`eval "wp_insert_post([ 'post_title' => 'Test meta weighting, post meta', 'post_content' => '', 'post_status' => 'publish', 'meta_input' => [ '_weighting_tests' => 1, '_my_custom_field' => 'abc123' ] ]); wp_insert_post([ 'post_title' => 'Test meta weighting, post content', 'post_content' => 'abc123', 'post_status' => 'publish', 'meta_input' => [ '_weighting_tests' => 1 ] ]);"`,
		);
		await wpCli('wp elasticpress sync --yes');
		await loggedInPage.waitForTimeout(500);
		await loggedInPage.goto('/?s=abc123');
		await expect(loggedInPage.locator('.entry-title')).toContainText(
			'Test meta weighting, post content',
		);
		await expect(loggedInPage.locator('.entry-title')).not.toContainText(
			'Test meta weighting, post meta',
		);

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-weighting');
		await clearThenType(
			loggedInPage,
			'.components-panel__header:has-text("Posts") ~ .components-panel fieldset:has-text("Content") input[type="number"]',
			'100',
		);
		await loggedInPage.getByRole('button', { name: 'Metadata' }).click();
		await clearThenType(loggedInPage, 'input[type="text"]', '_my_custom_field');
		await loggedInPage.getByRole('button', { name: 'Add' }).click();
		await loggedInPage
			.locator('fieldset:has-text("_my_custom_field") input[type="checkbox"]')
			.check();
		await loggedInPage.route('/wp-json/elasticpress/v1/weighting*', (route) =>
			route.continue(),
		);
		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
		await loggedInPage.waitForTimeout(1000);
		await wpCli('wp elasticpress sync --yes');
		await loggedInPage.waitForTimeout(1000);
		await loggedInPage.goto('/?s=abc123');
		const firstMetaTitle = await loggedInPage.locator('.entry-title').first().textContent();
		const lastMetaTitle = await loggedInPage.locator('.entry-title').last().textContent();
		expect(firstMetaTitle).toContain('Test meta weighting, post content');
		expect(lastMetaTitle).toContain('Test meta weighting, post meta');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-weighting');
		await clearThenType(loggedInPage, 'fieldset:has-text("Content") input[type="number"]', '1');
		await loggedInPage.getByRole('button', { name: 'Metadata' }).click();
		await clearThenType(
			loggedInPage,
			'fieldset:has-text("_my_custom_field") input[type="number"]',
			'100',
		);
		await loggedInPage.route('/wp-json/elasticpress/v1/weighting*', (route) =>
			route.continue(),
		);
		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
		await loggedInPage.waitForTimeout(500);
		await loggedInPage.goto('/?s=abc123');
		const firstMetaTitle2 = await loggedInPage.locator('.entry-title').first().textContent();
		const lastMetaTitle2 = await loggedInPage.locator('.entry-title').last().textContent();
		expect(firstMetaTitle2).toContain('Test meta weighting, post meta');
		expect(lastMetaTitle2).toContain('Test meta weighting, post content');

		await activatePlugin(loggedInPage, 'auto-meta-mode', 'wpCli');
		await wpCli('wp elasticpress sync --yes');
		await loggedInPage.waitForTimeout(500);
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
