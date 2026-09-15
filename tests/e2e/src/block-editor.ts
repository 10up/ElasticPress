import { expect, Page, Locator } from '@playwright/test';

/**
 * Locate a child of a block, including WP 7 ServerSideRender iframes.
 *
 * Gutenberg may wrap the preview in an iframe, so a direct descendant
 * locator misses the rendered markup.
 *
 * @param block Block locator
 * @param selector Child selector
 * @returns Locator for the child
 */
export async function blockInner(block: Locator, selector: string): Promise<Locator> {
	const iframe = block.locator('iframe').first();
	const iframeAttached = await iframe
		.waitFor({ state: 'attached', timeout: 3000 })
		.then(() => true)
		.catch(() => false);

	if (iframeAttached) {
		const inner = iframe.contentFrame().locator(selector);
		try {
			await inner.first().waitFor({ state: 'attached', timeout: 8000 });
			return inner;
		} catch {
			// Iframe is present but the selector lives on the block wrapper
			// (empty-state controls, client-rendered previews).
		}
	}

	return block.locator(selector);
}

/**
 * Open the block settings sidebar
 * @param page Playwright page object
 */
export async function openBlockSettingsSidebar(page: Page) {
	const body = await page.locator('body');
	const isWidgetsPage = await body.evaluate((el) => el.classList.contains('widgets-php'));
	const settingsButton = isWidgetsPage
		? page.locator('.edit-widgets-header__actions button[aria-label="Settings"]')
		: page.locator(
				`.edit-post-header__settings button[aria-label="Settings"],
				.editor-header__settings button[aria-label="Settings"]`,
			);

	// The widgets sidebar can already be open on the "Widget Areas" tab.
	// Clicking Settings in that state closes it instead of showing the inspector.
	const isPressed = (await settingsButton.getAttribute('aria-pressed')) === 'true';
	if (!isPressed) {
		await settingsButton.click();
	}

	const sidebar = isWidgetsPage
		? page.locator('.edit-widgets-sidebar')
		: page.locator('.interface-complementary-area, .editor-sidebar, .edit-post-sidebar');

	await sidebar
		.getByRole('tab', { name: 'Block' })
		.or(
			sidebar
				.locator(
					'.edit-widgets-sidebar__panel-tab, .edit-post-sidebar__panel-tab, .editor-sidebar__panel-tabs button',
				)
				.filter({ hasText: /^Block$/ }),
		)
		.click();
	await expect(page.locator('.block-editor-block-inspector')).toBeVisible({ timeout: 10000 });
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

	await page
		.locator(
			'.edit-widgets-header-toolbar__inserter-toggle, .edit-post-header-toolbar__inserter-toggle,.editor-document-tools__inserter-toggle',
		)
		.click();
}

/**
 * Close the block inserter
 * @param page Playwright page object
 */
export async function closeBlockInserter(page: Page) {
	const isInserterOpen = await page
		.locator(
			'.block-editor-inserter__panel-content, .edit-widgets-layout__inserter-panel-content',
		)
		.first()
		.isVisible();

	if (!isInserterOpen) {
		return;
	}

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

		if (process.env.WP_VERSION === '6.2') {
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
		} else {
			// WP 7.0 moved color.background out of the Color panel and the
			// Background panel is image-only, so there is no inspector control.
			await page.evaluate(`
				const clientId = wp.data.select('core/block-editor').getSelectedBlockClientId();
				wp.data.dispatch('core/block-editor').updateBlockAttributes(clientId, {
					backgroundColor: 'black',
				});
			`);
		}

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

		if (process.env.WP_VERSION === '6.2') {
			await page
				.locator('.block-editor-block-inspector button[aria-label="Typography options"]')
				.click();

			const fontSizeButton = page
				.locator('[aria-label="Typography options"] button, .popover-slot button')
				.filter({ hasText: /Font size|Size/ });
			await fontSizeButton.dispatchEvent('click');
			await fontSizeButton.dispatchEvent('click');
			await fontSizeButton.press('Escape');

			await page
				.locator('.block-editor-block-inspector button[aria-label="Typography options"]')
				.click();
			const wp62FontSizeButton = page
				.locator(
					'[role="menu"][aria-label="Typography options"] button, .popover-slot button',
				)
				.filter({ hasText: 'Font size' });
			await wp62FontSizeButton.click();
			await wp62FontSizeButton.press('Escape');
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
			const enableTypographyControl = async (controlName: string | RegExp) => {
				await page
					.locator(
						'.block-editor-block-inspector button[aria-label="Typography options"]',
					)
					.click();
				const menuItem = page.getByRole('menuitemcheckbox', { name: controlName });
				if (
					(await menuItem.isVisible()) &&
					(await menuItem.getAttribute('aria-checked')) !== 'true'
				) {
					await menuItem.click();
				}
				await page.keyboard.press('Escape');
			};

			await enableTypographyControl(/^(Font size|Size)$/);
			await enableTypographyControl('Line height');

			const fontSizeCombobox = page
				.locator('.block-editor-block-inspector fieldset.components-font-size-picker')
				.locator('button[role="combobox"], button[aria-label="Font size"]');

			if (await fontSizeCombobox.isVisible()) {
				await fontSizeCombobox.click();
				await page
					.locator(
						'.block-editor-block-inspector li[role="option"], .block-editor-block-inspector div[role="option"]',
					)
					.filter({ hasText: 'Extra small' })
					.click();
				await page.locator('.block-editor-line-height-control input').fill('2');
			} else {
				await page.evaluate(`
					const { select, dispatch } = wp.data;
					const clientId = select('core/block-editor').getSelectedBlockClientId();
					const attrs = select('core/block-editor').getBlockAttributes(clientId) || {};
					const style = attrs.style || {};
					dispatch('core/block-editor').updateBlockAttributes(clientId, {
						style: {
							...style,
							typography: {
								...(style.typography || {}),
								fontSize: '16px',
								lineHeight: '2',
							},
						},
					});
				`);
			}

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

		const dimensionsPanel = page.locator('.dimensions-block-support-panel');
		const paddingAlreadyVisible = await dimensionsPanel
			.locator('.component-spacing-sizes-control, .spacing-sizes-control__wrapper')
			.first()
			.isVisible();

		// WP 7.0 shows padding by default. Clicking it in the options menu
		// would hide the control the rest of this helper fills.
		if (process.env.WP_VERSION === '6.2' || !paddingAlreadyVisible) {
			await page
				.locator('.block-editor-block-inspector button[aria-label="Dimensions options"]')
				.click();
			const paddingButton = page
				.locator(
					'.popover-slot [role="menuitemcheckbox"], [aria-label="Dimensions options"] button, .popover-slot button',
				)
				.filter({ hasText: 'Padding' });
			await paddingButton.dispatchEvent('click');
			await paddingButton.press('Escape');
		}

		const verticalInputsWrapper = dimensionsPanel
			.locator('.component-spacing-sizes-control, .spacing-sizes-control__wrapper')
			.first();

		await verticalInputsWrapper
			.locator('button[aria-label="Set custom value"], button[aria-label="Set custom size"]')
			.first()
			.click();
		await verticalInputsWrapper.locator('input[type="number"]').fill('10');

		const horizontalInputsWrapper = dimensionsPanel
			.locator('.component-spacing-sizes-control, .spacing-sizes-control__wrapper')
			.nth(1);

		await horizontalInputsWrapper
			.locator('button[aria-label="Set custom value"], button[aria-label="Set custom size"]')
			.first()
			.click();
		await horizontalInputsWrapper.locator('input[type="number"]').fill('15');

		await page.locator('.block-editor-block-inspector button[aria-label="Settings"]').click();
	}

	await expect(element).toHaveCSS('padding', '10px 15px');
}
