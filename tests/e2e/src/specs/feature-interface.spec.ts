import { test, expect } from '../fixtures.js';
import {
	activatePlugin,
	goToAdminPage,
	wpCli,
	maybeDisableFeature,
	maybeEnableFeature,
	setDefaultFeatureSettings,
	deactivatePlugin,
} from '../utils.js';

/**
 * Test suite for the feature selection interface in ElasticPress settings.
 *
 * @module FeatureInterface
 */
test.describe('Features Interface', { tag: '@group1' }, () => {
	test('Renders group tabs, persists across reloads, and supports field dependency', async ({
		loggedInPage,
	}) => {
		// Visit the ElasticPress settings page in the WordPress admin
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		// Ensure the settings form is visible
		await expect(loggedInPage.locator('.ep-settings-page form')).toBeVisible();

		// Find and click the "Live Search" feature group tab button
		const liveSearchButton = loggedInPage.locator('button[id*="Live Search"]');
		await expect(liveSearchButton).toBeVisible();
		await expect(liveSearchButton).not.toBeDisabled();
		await liveSearchButton.click();

		// Assert that the "Live Search" panel is open and visible
		const panelSelector = 'div[id*="Live Search-view"]:has(.is-opened)';
		await expect(loggedInPage.locator(panelSelector)).toBeVisible();

		// Verifies that the group and feature tabs are clickable and persist their selections
		// Click the "Live Search" button again to ensure it's active
		await liveSearchButton.click();

		// Click the autosuggest feature tab
		const autosuggestButton = loggedInPage.locator('button[id*="autosuggest"]');
		await autosuggestButton.click();

		// Verify the autosuggest feature is active
		await expect(loggedInPage.locator('div[id*="autosuggest-view"]')).toBeVisible();

		// Reload the page to test persistence
		await loggedInPage.reload();
		await loggedInPage.waitForLoadState('domcontentloaded');

		// Wait for UI to load completely
		await expect(loggedInPage.locator('.ep-settings-page form')).toBeVisible();

		// Verify group selection persisted
		await expect(loggedInPage.locator('div[id*="Live Search-view"]')).toBeVisible();

		// Verify feature selection persisted
		await expect(loggedInPage.locator('div[id*="autosuggest-view"]')).toBeVisible();

		// Wait for epDashboard and features to be available
		await loggedInPage.waitForFunction(() => {
			return !!(window as any).epDashboard && !!(window as any).epDashboard.features;
		});

		// Push new fields into settingsSchema
		await loggedInPage.evaluate(() => {
			(window as any).epDashboard.features[0].settingsSchema.push({
				default: '1',
				key: 'test_field',
				label: 'Testing Field 1',
				options: [
					{ label: 'Option A', value: '0' },
					{ label: 'Option B', value: '1' },
				],
				type: 'radio',
			});
			(window as any).epDashboard.features[0].settingsSchema.push({
				default: '1',
				key: 'test_field_2',
				label: 'Testing Field 2',
				options: [
					{ label: 'Option A', value: '0' },
					{ label: 'Option B', value: '1' },
				],
				type: 'radio',
				requires_fields: {
					conditions: {
						test_field: '0',
					},
				},
			});
			// A list of values: the field shows when test_field has any of them.
			(window as any).epDashboard.features[0].settingsSchema.push({
				default: '1',
				key: 'test_field_3',
				label: 'Testing Field 3',
				type: 'text',
				requires_fields: {
					conditions: {
						test_field: ['0', '2'],
					},
				},
			});
		});

		// Toggle Re-Render
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Core Search' }).click();

		await expect(
			loggedInPage.locator('.ep-dashboard-control', {
				hasText: 'Testing Field 2',
			}),
		).not.toBeVisible();
		await expect(
			loggedInPage.locator('.ep-dashboard-control', {
				hasText: 'Testing Field 3',
			}),
		).not.toBeVisible();

		const testField = loggedInPage.locator('.ep-dashboard-control', {
			hasText: 'Testing Field 1',
		});

		// inside of testField, find the input with the label "Option A" and click it
		await testField.locator('input[value="0"]').click();

		// now, Testing Field 2 should be visible
		await expect(
			loggedInPage.locator('.ep-dashboard-control', {
				hasText: 'Testing Field 2',
			}),
		).toBeVisible();

		// and Testing Field 3, as '0' is one of its values
		await expect(
			loggedInPage.locator('.ep-dashboard-control', {
				hasText: 'Testing Field 3',
			}),
		).toBeVisible();
	});

	test('Supports collapsible field groups with a badge', async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await expect(loggedInPage.locator('.ep-settings-page form')).toBeVisible();
		await loggedInPage.waitForFunction(() => {
			return !!(window as any).epDashboard && !!(window as any).epDashboard.features;
		});

		// Add three groups to Post Search, each with one field
		await loggedInPage.evaluate(() => {
			const search = (window as any).epDashboard.features.find(
				(feature: any) => feature.slug === 'search',
			);

			search.fieldGroups.test_closed_group = {
				label: 'Test Closed Group',
				collapsible: true,
				initialOpen: false,
				badge: 'Not set up',
			};
			search.fieldGroups.test_open_group = {
				label: 'Test Open Group',
				collapsible: true,
			};
			search.fieldGroups.test_static_group = {
				label: 'Test Static Group',
				badge: 'Beta',
			};

			['closed', 'open', 'static'].forEach((group) => {
				search.settingsSchema.push({
					default: '',
					key: `test_${group}_field`,
					label: `Test ${group} field`,
					type: 'text',
					field_group_slug: `test_${group}_group`,
				});
			});
		});

		// Toggle Re-Render
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Core Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Post Search' }).click();

		// A closed group shows its label and badge, but not its fields
		const closedToggle = loggedInPage.getByRole('button', { name: /Test Closed Group/ });
		await expect(closedToggle).toHaveAttribute('aria-expanded', 'false');
		await expect(closedToggle.locator('.ep-field-group__badge')).toHaveText('Not set up');
		await expect(loggedInPage.getByLabel('Test closed field')).not.toBeVisible();

		// Its header opens and closes it
		await closedToggle.click();
		await expect(closedToggle).toHaveAttribute('aria-expanded', 'true');
		await expect(loggedInPage.getByLabel('Test closed field')).toBeVisible();
		const bodyId = await closedToggle.getAttribute('aria-controls');
		await expect(loggedInPage.locator(`[id="${bodyId}"]`)).toContainText('Test closed field');

		await closedToggle.click();
		await expect(closedToggle).toHaveAttribute('aria-expanded', 'false');
		await expect(loggedInPage.getByLabel('Test closed field')).not.toBeVisible();

		// A collapsible group is open by default
		const openToggle = loggedInPage.getByRole('button', { name: 'Test Open Group' });
		await expect(openToggle).toHaveAttribute('aria-expanded', 'true');
		await expect(loggedInPage.getByLabel('Test open field')).toBeVisible();

		// A group that is not collapsible has no toggle, and can still show a badge
		await expect(loggedInPage.getByRole('button', { name: /Test Static Group/ })).toHaveCount(
			0,
		);
		const staticGroup = loggedInPage.locator('.ep-field-group', {
			hasText: 'Test Static Group',
		});
		await expect(staticGroup.locator('.ep-field-group__badge')).toHaveText('Beta');
		await expect(loggedInPage.getByLabel('Test static field')).toBeVisible();

		// Existing groups are not collapsible
		await expect(
			loggedInPage.getByRole('button', { name: 'Highlighting Options' }),
		).toHaveCount(0);
		await expect(loggedInPage.getByText('Highlighting Options')).toBeVisible();
	});

	test('Supports the combobox field type and custom controls', async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await expect(loggedInPage.locator('.ep-settings-page form')).toBeVisible();
		await loggedInPage.waitForFunction(() => {
			return !!(window as any).epDashboard && !!(window as any).epDashboard.features;
		});

		await loggedInPage.evaluate(() => {
			const { addFilter } = (window as any).wp.hooks;
			const { createElement } = (window as any).wp.element;

			// Render the `test_custom` field type with a plugin component
			addFilter(
				'ep.Features.Control',
				'ep-e2e/custom-control',
				(component: any, type: string) =>
					type === 'test_custom'
						? ({ label, onChange, value, values }: any) =>
								createElement(
									'div',
									{ className: 'test-custom-control' },
									createElement('label', { htmlFor: 'test-custom-input' }, label),
									createElement('input', {
										id: 'test-custom-input',
										onChange: (event: any) => onChange(event.target.value),
										value,
									}),
									createElement(
										'span',
										{ className: 'test-custom-combobox-value' },
										values.test_combobox_field,
									),
								)
						: component,
			);

			const search = (window as any).epDashboard.features.find(
				(feature: any) => feature.slug === 'search',
			);

			search.settingsSchema.push({
				default: '',
				key: 'test_combobox_field',
				label: 'Test combobox field',
				options: [
					{ label: 'Option A', value: 'a' },
					{ label: 'Option B', value: 'b' },
				],
				type: 'combobox',
			});
			search.settingsSchema.push({
				default: 'initial',
				key: 'test_custom_field',
				label: 'Test custom field',
				type: 'test_custom',
			});
		});

		// Toggle Re-Render
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Core Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Post Search' }).click();

		// The combobox filters its options as you type
		const combobox = loggedInPage.getByRole('combobox', { name: 'Test combobox field' });
		const suggestions = loggedInPage.getByRole('listbox').getByRole('option');
		await combobox.fill('Option');
		await expect(suggestions).toHaveCount(2);
		await combobox.fill('B');
		await expect(suggestions).toHaveCount(1);
		await suggestions.filter({ hasText: 'Option B' }).click();
		await expect(combobox).toHaveValue('Option B');

		// The custom control renders with its value, and gets the other values before saving
		const customInput = loggedInPage.getByLabel('Test custom field');
		await expect(customInput).toHaveValue('initial');
		await expect(loggedInPage.locator('.test-custom-combobox-value')).toHaveText('b');

		// Its changes update the setting
		await customInput.fill('changed');
		await expect(customInput).toHaveValue('changed');
	});

	test.describe('Multiple Required Features', () => {
		test.afterAll(async () => {
			await wpCli('plugin deactivate multiple-required-features');
		});

		test('supports multiple required features', async ({ loggedInPage }) => {
			await wpCli('plugin activate multiple-required-features');
			await maybeDisableFeature('facets');
			await maybeDisableFeature('documents');

			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

			await loggedInPage.getByRole('button', { name: 'Core Search' }).click();
			await loggedInPage.getByRole('button', { name: 'Related Posts' }).click();

			await expect(
				loggedInPage.locator('.components-notice.is-error').filter({
					hasText:
						'The Filters and Documents features must be enabled to use this feature.',
				}),
			).toBeVisible();
		});
	});

	test('Temporarily disable a feature', async ({ loggedInPage }) => {
		// Regular workflow: Autosuggest is enabled
		await deactivatePlugin(loggedInPage, 'temporarily-disable-autosuggest', 'wpCli');
		await setDefaultFeatureSettings();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Autosuggest' }).click();

		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).toBeEnabled();
		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).toBeChecked();

		// Temporarily disable Autosuggest
		await activatePlugin(loggedInPage, 'temporarily-disable-autosuggest', 'wpCli');
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Autosuggest' }).click();

		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).toBeDisabled();
		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).not.toBeChecked();
		await expect(
			loggedInPage.getByRole('checkbox', { name: 'Trigger Google Analytics' }),
		).toBeDisabled();

		// If the test plugin is disabled, Autosuggest should be enabled again
		await deactivatePlugin(loggedInPage, 'temporarily-disable-autosuggest', 'wpCli');
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Autosuggest' }).click();

		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).toBeEnabled();
		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).toBeChecked();
		await expect(
			loggedInPage.getByRole('checkbox', { name: 'Trigger Google Analytics' }),
		).not.toBeDisabled();
	});

	test.describe('Settings Schema Updates on Save', () => {
		test.afterEach(async ({ loggedInPage }) => {
			await deactivatePlugin(loggedInPage, 'simulate-setting-dependency', 'wpCli');
			await setDefaultFeatureSettings();
		});

		test.afterAll(async () => {
			await wpCli('plugin deactivate simulate-setting-dependency', true);
			await setDefaultFeatureSettings();
		});

		test('Feature settings schema updates without page refresh when dependency changes', async ({
			loggedInPage,
		}) => {
			await activatePlugin(loggedInPage, 'simulate-setting-dependency', 'wpCli');
			await maybeEnableFeature('test_conditional_settings');

			// Start with Post Search disabled so conditional options are hidden
			await maybeDisableFeature('search');

			// Navigate to the settings page
			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
			await expect(loggedInPage.locator('.ep-settings-page form')).toBeVisible();
			await loggedInPage.waitForFunction(() => {
				return !!(window as any).epDashboard && !!(window as any).epDashboard.features;
			});

			// Navigate to the Test Conditional Settings panel
			await loggedInPage.getByRole('button', { name: 'Core Search' }).click();
			await loggedInPage.getByRole('button', { name: 'Test Conditional Settings' }).click();
			await expect(
				loggedInPage.locator('div[id*="test_conditional_settings-view"]'),
			).toBeVisible();
			await loggedInPage.waitForTimeout(500);

			// Verify only the base options are visible (Post Search is not active)
			await expect(loggedInPage.getByLabel('Basic')).toBeVisible();
			await expect(loggedInPage.getByLabel('Standard')).toBeVisible();
			await expect(
				loggedInPage.getByLabel('Advanced (requires Post Search)'),
			).not.toBeVisible();
			await expect(
				loggedInPage.getByLabel('Expert (requires Post Search)'),
			).not.toBeVisible();

			// Navigate to Post Search and enable it
			await loggedInPage.getByRole('button', { name: 'Post Search' }).click();
			await expect(loggedInPage.locator('div[id*="search-view"]')).toBeVisible();
			await loggedInPage.waitForTimeout(500);
			await loggedInPage.getByRole('checkbox', { name: 'Enable' }).setChecked(true);

			// Save and wait for the save operation to complete.
			const saveButton = loggedInPage.getByRole('button', { name: 'Save changes' });
			const saveSnackbar = loggedInPage
				.locator('.components-snackbar')
				.filter({ hasText: 'Feature settings saved' });
			const saveAndConfirm = async () => {
				await saveButton.click();
				await expect(saveSnackbar.last()).toBeVisible({ timeout: 10000 });
				await saveSnackbar.evaluateAll((nodes) => {
					nodes.forEach((node) => (node as HTMLElement).click());
				});
				await expect(saveSnackbar).toHaveCount(0, { timeout: 5000 });
			};
			await saveAndConfirm();

			// Navigate back to the Test Conditional Settings (no page refresh)
			await loggedInPage.getByRole('button', { name: 'Test Conditional Settings' }).click();
			await expect(
				loggedInPage.locator('div[id*="test_conditional_settings-view"]'),
			).toBeVisible();
			await loggedInPage.waitForTimeout(500);

			// Verify the conditional options now appear
			await expect(loggedInPage.getByLabel('Basic')).toBeVisible();
			await expect(loggedInPage.getByLabel('Standard')).toBeVisible();
			await expect(loggedInPage.getByLabel('Advanced (requires Post Search)')).toBeVisible();
			await expect(loggedInPage.getByLabel('Expert (requires Post Search)')).toBeVisible();

			// Select a conditional option that only exists while Post Search is active
			await loggedInPage.getByLabel('Advanced (requires Post Search)').check();
			await saveAndConfirm();

			// Now disable Post Search and verify the options disappear
			await loggedInPage.getByRole('button', { name: 'Post Search' }).click();
			await expect(loggedInPage.locator('div[id*="search-view"]')).toBeVisible();
			await loggedInPage.waitForTimeout(500);
			await loggedInPage.getByRole('checkbox', { name: 'Enable' }).setChecked(false);
			await saveAndConfirm();

			// Navigate back to the Test Conditional Settings (no page refresh)
			await loggedInPage.getByRole('button', { name: 'Test Conditional Settings' }).click();
			await expect(
				loggedInPage.locator('div[id*="test_conditional_settings-view"]'),
			).toBeVisible();
			await loggedInPage.waitForTimeout(500);

			// Verify the conditional options are gone again (schema refresh)
			await expect(loggedInPage.getByLabel('Basic')).toBeVisible();
			await expect(loggedInPage.getByLabel('Standard')).toBeVisible();
			await expect(
				loggedInPage.getByLabel('Advanced (requires Post Search)'),
			).not.toBeVisible();
			await expect(
				loggedInPage.getByLabel('Expert (requires Post Search)'),
			).not.toBeVisible();

			// Verify the previously-selected `advanced` value was normalized
			await expect(loggedInPage.getByLabel('Basic')).toBeChecked();
			await expect(loggedInPage.getByLabel('Standard')).not.toBeChecked();
		});
	});
});
