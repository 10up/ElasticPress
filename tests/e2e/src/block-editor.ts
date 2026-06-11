import { expect, Page, Locator } from '@playwright/test';

/**
 * Open the block settings sidebar
 * @param page Playwright page object
 */
export async function openBlockSettingsSidebar(page: Page) {
	const body = await page.locator('body');
	const isWidgetsPage = await body.evaluate((el) => el.classList.contains('widgets-php'));

	if (isWidgetsPage) {
		await page.locator('.edit-widgets-header__actions button[aria-label="Settings"]').click();
		await page
			.locator('.edit-widgets-sidebar__panel-tab,.edit-widgets-sidebar__panel-tabs button')
			.filter({ hasText: 'Block' })
			.click();
	} else {
		await page
			.locator(
				`.edit-post-header__settings button[aria-label="Settings"],
			.editor-header__settings button[aria-label="Settings"]`,
			)
			.click();
		await page
			.locator(
				`.edit-post-sidebar__panel-tab,
			.edit-post-sidebar__panel-tabs button,
			.editor-sidebar__panel-tabs button:has-text('Block')`,
			)
			.filter({ hasText: 'Block' })
			.click();
	}
}

/**
 * Dismiss the first-run "Welcome" guide modal if it is present.
 *
 * On WordPress 7.0 the block editors (widgets, post) show a "Welcome" guide the first time they
 * load. Its modal overlay (`.components-modal__screen-overlay`) intercepts pointer events, so
 * controls underneath cannot be clicked until the guide is closed. The guide's Close control is a
 * bare `button[aria-label="Close"]` directly inside the overlay (not inside a header/container).
 * @param page Playwright page object
 */
export async function dismissWelcomeGuide(page: Page) {
	const overlay = page.locator('.components-modal__screen-overlay');
	if (
		!(await overlay
			.first()
			.isVisible()
			.catch(() => false))
	) {
		return;
	}

	const closeButton = overlay.locator('button[aria-label="Close"]').first();
	if (await closeButton.isVisible().catch(() => false)) {
		await closeButton.click().catch(() => {});
	} else {
		// Fallback: the guide modal also closes on Escape.
		await page.keyboard.press('Escape');
	}

	await overlay
		.first()
		.waitFor({ state: 'hidden', timeout: 5000 })
		.catch(() => {});
}

/**
 * Open the block inserter
 * @param page Playwright page object
 */
export async function openBlockInserter(page: Page) {
	const isInserterOpen = await page
		.locator(
			'.block-editor-inserter__panel-content, .edit-widgets-layout__inserter-panel-content',
		)
		.first()
		.isVisible();

	if (isInserterOpen) {
		return;
	}

	const toggle = page.locator(
		'.edit-widgets-header-toolbar__inserter-toggle, .edit-post-header-toolbar__inserter-toggle,.editor-document-tools__inserter-toggle',
	);

	// On a first WP 7.0 editor load the "Welcome" guide modal can intercept this click. Try the
	// click first (no cost when there is no guide); if it is blocked, dismiss the guide and retry.
	try {
		await toggle.click({ timeout: 8000 });
	} catch {
		await dismissWelcomeGuide(page);
		await toggle.click();
	}
}

/**
 * Close the block inserter
 * @param page Playwright page object
 */
export async function closeBlockInserter(page: Page) {
	const body = await page.locator('body');
	const isWidgetsPage = await body.evaluate((el) => el.classList.contains('widgets-php'));

	if (isWidgetsPage) {
		await page.locator('.edit-widgets-header-toolbar__inserter-toggle').click();
	} else {
		await page
			.locator(
				'.edit-post-header-toolbar__inserter-toggle,.editor-document-tools__inserter-toggle',
			)
			.click();
	}
}

/**
 * Get the blocks list
 * @param page Playwright page object
 * @returns Locator for the blocks list
 */
export async function getBlocksList(page: Page) {
	return page.locator('.block-editor-inserter__block-list');
}

/**
 * Insert a block
 * @param page Playwright page object
 * @param blockName Name of the block to insert
 */
export async function insertBlock(page: Page, blockName: string) {
	await openBlockInserter(page);
	await page.locator('.block-editor-inserter__search input[type="search"]').fill(blockName);
	await page
		.locator('.block-editor-block-types-list__item')
		.filter({ hasText: blockName })
		.first()
		.click({ force: true });
}

/**
 * Check if a block supports colors
 * @param page Playwright page object
 * @param element Locator for the block's element
 * @param isEdit Whether to edit the block's colors
 * @returns Promise that resolves when the check is complete
 */
export async function supportsBlockColors(page: Page, element: Locator, isEdit = false) {
	if (isEdit) {
		await page.locator('.block-editor-block-inspector button[aria-label="Styles"]').click();
		await page
			.locator('.block-editor-block-inspector button')
			.filter({ hasText: 'Background' })
			.click();

		await page
			.locator(
				`.block-editor-color-gradient-control button[aria-label="Black"],
			.block-editor-color-gradient-control__panel button[aria-label="Color: Black"]`,
			)
			.click();

		await page.locator('.block-editor-block-inspector button[aria-label="Settings"]').click();
	}

	await expect(element).toHaveCSS('background-color', 'rgb(0, 0, 0)');
}

/**
 * Check if a block supports typography
 * @param page Playwright page object
 * @param element Locator for the block's element
 * @param isEdit Whether to edit the block's typography
 * @returns Promise that resolves when the check is complete
 */
export async function supportsBlockTypography(page: Page, element: Locator, isEdit = false) {
	if (isEdit) {
		await page.locator('.block-editor-block-inspector button[aria-label="Styles"]').click();
		await page
			.locator('.block-editor-block-inspector button[aria-label="Typography options"]')
			.click();

		const fontSizeButton = page
			.locator('[aria-label="Typography options"] button, .popover-slot button')
			.filter({ hasText: /Font size|Size/ });
		await fontSizeButton.dispatchEvent('click');
		await fontSizeButton.dispatchEvent('click');
		await fontSizeButton.press('Escape');

		if (process.env.WP_VERSION === '6.2') {
			await page
				.locator('.block-editor-block-inspector button[aria-label="Typography options"]')
				.click();
			const fontSizeButton = page
				.locator(
					'[role="menu"][aria-label="Typography options"] button, .popover-slot button',
				)
				.filter({ hasText: 'Font size' });
			await fontSizeButton.click();
			await fontSizeButton.press('Escape');
			await page.getByRole('button', { name: 'Set custom size' }).click();
			await page
				.locator('.components-font-size-picker__controls input[type="number"]')
				.fill('16');

			await page
				.locator('.block-editor-block-inspector button[aria-label="Typography options"]')
				.click();
			const lineHeightButton = page
				.locator('[aria-label="Typography options"] button, .popover-slot button')
				.filter({ hasText: 'Line height' });
			await lineHeightButton.click();
			await lineHeightButton.press('Escape');
			await page.locator('.components-input-control__input[placeholder="1.5"]').fill('2');
		} else {
			await page
				.locator('.block-editor-block-inspector fieldset.components-font-size-picker')
				.locator('button[role="combobox"], button[aria-label="Font size"]')
				.click();

			await page
				.locator(
					'.block-editor-block-inspector li[role="option"], .block-editor-block-inspector div[role="option"]',
				)
				.filter({ hasText: 'Extra small' })
				.click();

			await page.locator('.block-editor-line-height-control input').fill('2');
			await page
				.locator('.block-editor-block-inspector button[aria-label="Settings"]')
				.click();
		}
	}

	await expect(element).toHaveCSS('font-size', '16px');
	await expect(element).toHaveCSS('line-height', '32px');
}

/**
 * Check if a block supports dimensions
 * @param page Playwright page object
 * @param element Locator for the block's element
 * @param isEdit Whether to edit the block's dimensions
 * @returns Promise that resolves when the check is complete
 */
export async function supportsBlockDimensions(page: Page, element: Locator, isEdit = false) {
	if (isEdit) {
		await page.locator('.block-editor-block-inspector button[aria-label="Styles"]').click();
		await page
			.locator('.block-editor-block-inspector button[aria-label="Dimensions options"]')
			.click();

		const dimensionsPanel = page.locator('.dimensions-block-support-panel');

		const paddingButton = page
			.locator('[aria-label="Dimensions options"] button, .popover-slot button')
			.filter({ hasText: 'Padding' });
		await paddingButton.dispatchEvent('click');
		await paddingButton.press('Escape');

		if (process.env.WP_VERSION === '6.2') {
			await page.locator('.components-button[aria-label="Unlink sides"]').click();

			const inputs = [
				{ label: 'Top padding', value: 10 },
				{ label: 'Right padding', value: 15 },
				{ label: 'Bottom padding', value: 10 },
				{ label: 'Left padding', value: 15 },
			];

			for await (const { label, value } of inputs) {
				const customSizeButton = dimensionsPanel
					.locator('button[aria-label="Set custom size"]')
					.first();
				const input = dimensionsPanel
					.locator(`label:has-text("${label}")`)
					.locator('..')
					.locator('input');

				await customSizeButton.click({ force: true });
				await input.fill(value.toString());
			}

			await dimensionsPanel.click();
		} else {
			// WordPress 7.0 renamed the spacing control's toggle from "Set custom size" to
			// "Set custom value"; match either so this works across supported versions.
			const customSizeButton =
				'button[aria-label="Set custom size"], button[aria-label="Set custom value"]';

			const verticalInputsWrapper = dimensionsPanel
				.locator('.component-spacing-sizes-control, .spacing-sizes-control__wrapper')
				.first();

			await verticalInputsWrapper.locator(customSizeButton).first().click();
			await verticalInputsWrapper.locator('input[type="number"]').fill('10');

			const horizontalInputsWrapper = dimensionsPanel
				.locator('.component-spacing-sizes-control, .spacing-sizes-control__wrapper')
				.nth(1);

			await horizontalInputsWrapper.locator(customSizeButton).first().click();
			await horizontalInputsWrapper.locator('input[type="number"]').fill('15');

			await page
				.locator('.block-editor-block-inspector button[aria-label="Settings"]')
				.click();
		}
	}

	await expect(element).toHaveCSS('padding', '10px 15px');
}
