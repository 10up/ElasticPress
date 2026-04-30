import { test, expect } from '../fixtures.js';
import {
	activatePlugin,
	deactivatePlugin,
	getEditorFrame,
	goToAdminPage,
	maybeEnableFeature,
} from '../utils.js';
import {
	closeBlockInserter,
	getBlocksList,
	insertBlock,
	openBlockInserter,
} from '../block-editor.js';

test.describe('Did You Mean Feature', { tag: '@group2' }, () => {
	test.beforeAll(async () => {
		await maybeEnableFeature('did-you-mean');
	});

	test.beforeEach(async ({ loggedInPage }) => {
		await activatePlugin(loggedInPage, 'enable-did-you-mean-block', 'wpCli');
	});

	test.afterEach(async ({ loggedInPage }) => {
		await deactivatePlugin(loggedInPage, 'enable-did-you-mean-block', 'wpCli');
	});

	/**
	 * The block is gated behind the `ep_did_you_mean_enabled_in_editor` filter
	 * in the post editor (it is intended for the FSE Site Editor). The test
	 * plugin flips that filter so we can exercise the inserter and the
	 * placeholder rendering without spinning up a block theme.
	 */
	test('Can insert the Did You Mean block and see its placeholder', async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, 'post-new.php');

		await openBlockInserter(loggedInPage);
		await expect(await getBlocksList(loggedInPage)).toContainText('Did You Mean');
		await insertBlock(loggedInPage, 'Did You Mean');
		await closeBlockInserter(loggedInPage);

		const editorFrame = await getEditorFrame(loggedInPage);
		const block = editorFrame.locator('.wp-block.wp-block-elasticpress-did-you-mean').first();

		await expect(block).toBeVisible();
		await expect(block).toContainText('Did you mean Hello?');
	});
});
