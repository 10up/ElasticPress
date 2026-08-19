/// <reference types="node" />

import { execFile } from 'child_process';
import { promisify } from 'util';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

import { FrameLocator, Page, Locator } from '@playwright/test';
import { writeFileSync, unlinkSync } from 'fs';
import * as path from 'path';

export function isEpIo(): boolean {
	return process.env.EP_IS_EPIO === '1';
}

export function getPluginRootDir(): string {
	const __filename = fileURLToPath(import.meta.url);
	const __dirname = dirname(__filename);
	return path.resolve(__dirname, '../../..');
}

export function getPluginDir(): string {
	return process.cwd().split('/').pop() ?? 'elasticpress';
}

export function getPluginSlug(slug: string) {
	return slug === 'elasticpress' ? getPluginDir() : slug;
}

export const defaultFeatures = {
	search: {
		active: 1,
		highlight_enabled: '1',
		highlight_excerpt: '1',
		highlight_tag: 'mark',
	},
	related_posts: {
		active: true,
	},
	facets: {
		active: true,
	},
	searchordering: {
		active: true,
	},
	autosuggest: {
		active: true,
	},
	woocommerce: {
		active: false,
	},
	protected_content: {
		active: false,
	},
	acf_repeater: {
		active: true,
	},
};

/**
 * Login to WordPress admin
 * @param page Playwright page object
 * @param username Optional username, defaults to 'admin'
 * @param password Optional password, defaults to 'password'
 */
export async function login(page: Page, username = 'admin', password = 'password') {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', username);
	await page.fill('#user_pass', password);
	await page.click('#wp-submit');
	await page.waitForLoadState('networkidle');
}

/**
 * Logout from WordPress admin
 * @param page Playwright page object
 */
export async function logout(page: Page) {
	await page.goto('/wp-admin');
	const adminBar = await page.locator('#wpadminbar');
	if (await adminBar.isVisible()) {
		await page.hover('#wp-admin-bar-my-account');
		await page.click('#wp-admin-bar-logout > a');
	}
	await page.goto('/wp-admin');
}

/**
 * Navigate to a specific admin page
 * @param page Playwright page object
 * @param path Admin page path (e.g. 'options-general.php')
 */
export async function goToAdminPage(page: Page, path: string) {
	await page.goto(`/wp-admin/${path}`);
	await page.waitForLoadState('domcontentloaded');
	if (page.frames().length > 0) {
		await Promise.all(page.frames().map((frame) => frame.waitForLoadState('domcontentloaded')));
	}
}

const execFileAsync = promisify(execFile);

// Default maxBuffer is 1MB. WP 6.2 + WooCommerce 6.4 on PHP 8.2 emits enough
// deprecations during `wp elasticpress sync` to exceed that, kill wp-env-cli,
// and leave `docker compose exec` running so the test never finishes.
const WP_CLI_OPTIONS = {
	encoding: 'utf8' as const,
	maxBuffer: 50 * 1024 * 1024,
	timeout: 180000,
};

export async function wpCli(command: string, ignoreFailures = false) {
	const escapedCommand = command.replace(/^wp /, '');
	const cli = `${getPluginRootDir()}/bin/wp-env-cli`;
	const args = ['wordpress', `wp --allow-root ${escapedCommand}`];

	try {
		const { stdout } = await execFileAsync(cli, args, WP_CLI_OPTIONS);
		return stdout;
	} catch (err: any) {
		if (err.killed || err.code === 'ERR_CHILD_PROCESS_STDIO_MAXBUFFER') {
			throw err;
		}
		const output = err.stdout?.toString() || '';
		if (ignoreFailures) {
			return output;
		}
		throw new Error(
			`wpCli "${escapedCommand}" failed (code ${err.code}): ${
				output || err.stderr?.toString() || err.message
			}`,
		);
	}
}

export async function wpCliEval(command: string) {
	const fileName = (Math.random() + 1).toString(36).substring(7);
	const pluginRootDir = getPluginRootDir();
	const fullFilePath = `${pluginRootDir}/${fileName}`;
	const escapedCommand = command.replace(/^<\?php /, '');

	// Write the PHP code to a temporary file
	writeFileSync(fullFilePath, `<?php ${escapedCommand}`);

	// Execute the PHP code using wp-cli
	const result = await wpCli(
		`eval-file wp-content/plugins/${pluginRootDir.split('/').pop()}/${fileName}`,
		true,
	);

	// Clean up the temporary file
	unlinkSync(fullFilePath);

	return result;
}

/**
 * Activate a WordPress plugin
 * @param page Playwright page object
 * @param slugs Plugin slug to activate
 * @param method Activation method ('dashboard' or 'wpCli')
 * @param mode Site mode ('singleSite' or 'network')
 */
export async function activatePlugin(
	page: Page,
	slugs: string,
	method: 'dashboard' | 'wpCli' = 'dashboard',
	mode: 'singleSite' | 'network' = 'singleSite',
) {
	if (method === 'dashboard') {
		const path = mode === 'network' ? 'network/plugins.php' : 'plugins.php';
		await goToAdminPage(page, path);

		const activateButton = page.locator(`#activate-${slugs}`);
		if (await activateButton.isVisible()) {
			await activateButton.click();
			await page.waitForLoadState('networkidle');
		}
		return;
	}

	const sanitizedSlugs = slugs
		.split(' ')
		.map((slug) => getPluginSlug(slug))
		.join(' ');
	let command = `wp plugin activate ${sanitizedSlugs}`;
	if (mode === 'network') {
		command += ' --network';
	}
	await wpCli(command);
}

/**
 * Deactivate a WordPress plugin
 * @param page Playwright page object
 * @param slugs Plugin slug to deactivate
 * @param method Deactivation method ('dashboard' or 'wpCli')
 * @param mode Site mode ('singleSite' or 'network')
 */
export async function deactivatePlugin(
	page: Page,
	slugs: string,
	method: 'dashboard' | 'wpCli' = 'dashboard',
	mode: 'singleSite' | 'network' = 'singleSite',
) {
	if (method === 'dashboard') {
		const path = mode === 'network' ? 'network/plugins.php' : 'plugins.php';
		await goToAdminPage(page, path);

		const deactivateButton = page.locator(`#deactivate-${slugs}`);
		if (await deactivateButton.isVisible()) {
			await deactivateButton.click();
			await page.waitForLoadState('networkidle');
		}
		return;
	}

	const sanitizedSlugs = slugs
		.split(' ')
		.map((slug) => getPluginSlug(slug))
		.join(' ');
	let command = `wp plugin deactivate ${sanitizedSlugs}`;
	if (mode === 'network') {
		command += ' --network';
	}
	await wpCli(command);
}

/**
 * Create a new term in WordPress
 * @param page Playwright page object
 * @param data Term data including taxonomy, name, and optional parent
 * @param data.taxonomy Taxonomy name (defaults to 'category')
 * @param data.name Term name (defaults to 'Test taxonomy')
 * @param data.parent Parent term name (optional)
 */
export async function createTerm(
	page: Page,
	data: { taxonomy?: string; name?: string; parent?: string },
) {
	const { taxonomy, name, parent } = {
		name: 'Test taxonomy',
		taxonomy: 'category',
		parent: null,
		...data,
	};

	await goToAdminPage(page, `edit-tags.php?taxonomy=${taxonomy}`);

	if (parent !== null) {
		await page.selectOption('#parent', parent);
	}

	await page.fill('#tag-name', name);
	await page.keyboard.press('Enter');
	await page.waitForLoadState('networkidle');

	// Wait for Elasticsearch to process the new post
	await page.waitForTimeout(2000);
}

/**
 * Clear and type text into an input field
 * @param page Playwright page object
 * @param selector Element selector
 * @param text Text to type
 */
export async function clearThenType(page: Page, selector: string, text: string) {
	await page.fill(selector, 'x');
	await page.fill(selector, '');
	await page.fill(selector, text);
}

export async function getEditorFrame(page: Page): Promise<Page | FrameLocator> {
	const iframedEditor = page.locator('iframe[name="editor-canvas"]');
	const inlineEditor = page.locator('.edit-post-visual-editor');

	await Promise.race([
		iframedEditor.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {}),
		inlineEditor
			.first()
			.waitFor({ state: 'visible', timeout: 10000 })
			.catch(() => {}),
	]);

	return (await iframedEditor.count()) > 0 ? iframedEditor.contentFrame() : page;
}

export async function maybeOpenEditorSettings(page: Page) {
	const selector =
		process.env.WP_VERSION === '6.2'
			? '.interface-interface-skeleton__sidebar .interface-complementary-area'
			: '.interface-interface-skeleton__sidebar .interface-complementary-area__fill';
	const editorSettings = page.locator(selector);

	try {
		if (process.env.WP_VERSION !== '6.2') {
			await editorSettings.waitFor({ state: 'visible', timeout: 5 });
		}
	} catch (error) {
		// Do nothing
	}

	const isEditorSettingsVisible = await editorSettings.isVisible();
	if (!isEditorSettingsVisible) {
		await page
			.locator('.edit-post-header, .edit-widgets-header')
			.locator('button[aria-label="Settings"]')
			.click();
	}
}

export async function maybeOpenSettingsTab(page: Page, tabName: string) {
	await maybeOpenEditorSettings(page);

	let tab: Locator;
	let isTabActive: string | boolean | null;
	if (process.env.WP_VERSION === '6.2') {
		tab = page.locator('.edit-post-sidebar__panel-tab, .edit-widgets-sidebar__panel-tab', {
			hasText: tabName,
		});
		const postTabClasses = await tab.getAttribute('class');
		isTabActive = postTabClasses?.includes('is-active') ?? false;
	} else {
		tab = page.getByRole('tab', { name: tabName });
		isTabActive = (await tab.getAttribute('aria-selected')) === 'true';
	}
	if (!isTabActive) {
		await tab.click();
	}
}

/**
 * Set post password
 * @param page Playwright page object
 * @param password Password to set
 * @param save Whether to save the post after setting the password
 * @param goToPost Whether to go to the post after setting the password
 */
export async function setPostPassword(
	page: Page,
	password: string,
	save = false,
	goToPost = false,
) {
	await maybeOpenEditorSettings(page);
	await maybeOpenSettingsTab(page, 'Post');

	if (process.env.WP_VERSION === '6.2') {
		await page.locator('.edit-post-post-visibility__toggle').click();
		await page
			.getByRole('radio', { name: password !== '' ? 'Password protected' : 'Public' })
			.click();

		if (password !== '') {
			await page.fill(
				'.editor-post-visibility__dialog-password-input, .editor-post-visibility__password-input',
				password,
			);
		}
	} else {
		const statusDropdown = page.locator('.components-dropdown.editor-post-status');
		if ((await statusDropdown.count()) > 0) {
			await statusDropdown.click({ force: true });
		} else {
			await page.getByRole('button', { name: /Change status/ }).click({ force: true });
		}

		const passwordCheckbox = page.locator(
			'.editor-change-status__password-fieldset input[type="checkbox"]',
		);
		const passwordCheckboxIsChecked = await passwordCheckbox.isChecked();
		if (
			(passwordCheckboxIsChecked && password === '') ||
			(!passwordCheckboxIsChecked && password !== '')
		) {
			await passwordCheckbox.setChecked(password !== '', { force: true });
		}

		if (password !== '') {
			await page.locator('.editor-change-status__password-input input').fill(password);
		}
	}

	if (save) {
		await page.locator('.editor-post-publish-button__button').click({ force: true });
		await page.waitForSelector('.components-snackbar');

		// Wait for Elasticsearch to process the post
		await page.waitForTimeout(2000);
	}

	if (goToPost) {
		const postHref = (await page.locator('#wp-admin-bar-view a').getAttribute('href')) ?? '';
		await page.goto(postHref);
	}
}

/**
 * Create and publish a new post
 * @param page Playwright page object
 * @param postData Post data including title, content, password, and status
 * @param postData.title Post title
 * @param postData.content Post content
 * @param postData.password Post password
 * @param postData.status Post status ('draft' or 'publish')
 * @param viewPost Whether to view the post after publishing
 * @param rawContent Whether to use raw content instead of block editor
 */
export async function publishPost(
	page: Page,
	postData: { title?: string; content?: string; password?: string; status?: string },
	viewPost = false,
	rawContent = false,
) {
	const newPostData = { title: 'Test Post', content: 'Test content.', ...postData };

	await goToAdminPage(page, 'post-new.php');
	const editorFrame = await getEditorFrame(page);

	const isInCodeEditorMode = await editorFrame.locator('.editor-post-text-editor').isVisible();
	const changeMode = async (page: Page) => {
		const apiResponsePromise = page.waitForResponse('**/wp-json/wp/v2/users/me*');
		await page.keyboard.press('Control+Shift+Alt+M');
		await apiResponsePromise; // Wait for WP to save the preference
	};

	if (rawContent) {
		if (!isInCodeEditorMode) {
			await changeMode(page);
		}
		if (process.env.WP_VERSION === '6.2') {
			await page.locator('.wp-block-post-title').fill(newPostData.title);
		} else {
			await page.locator('.wp-block-post-title textarea').fill(newPostData.title);
		}
		await page.locator('.editor-post-text-editor').fill(newPostData.content);

		// Return to visual editor
		await changeMode(page);
	} else {
		// Return to visual editor
		if (isInCodeEditorMode) {
			await changeMode(page);
		}
		await editorFrame
			.locator('h1.editor-post-title__input, #post-title-0')
			.fill(newPostData.title);
		await editorFrame
			.locator('.block-editor-default-block-appender__content')
			.pressSequentially(newPostData.content);
	}

	if (newPostData.password && newPostData.password !== '') {
		await setPostPassword(page, newPostData.password);
	}

	if (newPostData.status && newPostData.status === 'draft') {
		await page.locator('.editor-post-save-draft').click();
		await page.waitForSelector('.editor-post-saved-state');
	} else {
		await page.locator('.editor-post-publish-panel__toggle').click();
		await page.locator('.editor-post-publish-button').click();
		await page.waitForSelector('.components-snackbar');

		if (viewPost) {
			const postHref =
				(await page
					.locator('.post-publish-panel__postpublish-buttons a:has-text("View Post")')
					.getAttribute('href')) ?? '';
			await page.goto(postHref);
		}
	}

	// Wait for Elasticsearch to process the new post
	await page.waitForTimeout(2000);
}

/**
 * Update feature settings
 * @param featureName Name of the feature to update
 * @param newValues New values for the feature
 */
export async function updateFeatures(featureName: string, newValues: any) {
	await wpCliEval(
		`
		$features = get_option( 'ep_feature_settings', [] );
		$new_feature_value = json_decode( '${JSON.stringify(newValues)}', true );
		$features['${featureName}'] = $new_feature_value;

		update_option( 'ep_feature_settings', $features );
		`,
	);
}

/**
 * Update weighting settings
 * @param newWeightingValues New weighting values
 */
export async function updateWeighting(newWeightingValues: any = null) {
	const defaultWeighting = {
		post: {
			post_title: { weight: 1, enabled: true },
			post_content: { weight: 1, enabled: true },
			post_excerpt: { weight: 1, enabled: true },
			author_name: { weight: 0, enabled: false },
		},
		page: {
			post_title: { weight: 1, enabled: true },
			post_content: { weight: 1, enabled: true },
			post_excerpt: { weight: 1, enabled: true },
			author_name: { weight: 0, enabled: false },
		},
	};

	const escapedWeighting = newWeightingValues
		? JSON.stringify(newWeightingValues)
		: JSON.stringify(defaultWeighting);

	await wpCli(
		`eval "\\$weighting = json_decode( '${escapedWeighting.replace(/"/g, '\\"')}', true ); print_r( \\$weighting ); update_option( 'elasticpress_weighting', \\$weighting );"`,
	);
}

/**
 * Enable a feature if not already enabled
 * @param featureName Name of the feature to enable
 */
export async function maybeEnableFeature(featureName: string) {
	await wpCli(`elasticpress activate-feature ${featureName}`, true);
}

/**
 * Disable a feature if not already disabled
 * @param featureName Name of the feature to disable
 */
export async function maybeDisableFeature(featureName: string) {
	await wpCli(`elasticpress deactivate-feature ${featureName}`, true);
}

/**
 * Create a classic widget
 * @param page Playwright page object
 * @param widgetId Widget ID
 * @param settings Widget settings
 */
export async function createClassicWidget(
	page: Page,
	widgetId: string,
	settings: Array<{ name: string; type: string; value: string }>,
) {
	await goToAdminPage(page, 'widgets.php');

	// Add widget to first widget area
	await page.click(`#widget-list [id$="${widgetId}-__i__"] h3`);
	await page.click(`#widget-list [id$="${widgetId}-__i__"] .widgets-chooser-add`);

	// Set widget settings and save
	const widget = page.locator(`#widgets-right .widget[id*="${widgetId}"]`).last();

	for await (const { name, type, value } of settings) {
		const control = widget.locator(`[name*="[${name}]"]`);

		switch (type) {
			case 'select':
				await control.selectOption(value);
				break;
			case 'checkbox':
			case 'radio':
				await control.and(page.locator(`[value="${value}"]`)).check();
				break;
			default:
				await control.fill(value);
				break;
		}
	}

	const saveWidgetResponse = page.waitForResponse('**/wp-admin/admin-ajax.php*');
	await widget.locator('input[type="submit"]').click();
	await saveWidgetResponse;
}

/**
 * Empty all widgets
 */
export async function emptyWidgets() {
	await wpCli('widget reset --all');
	const inactiveWidgets = (await wpCli('widget list wp_inactive_widgets --format=ids', true))
		?.toString()
		.trim();
	if (inactiveWidgets) {
		await wpCli(`widget delete ${inactiveWidgets}`);
	}
}

/**
 * Create a user
 * @param page Playwright page object
 * @param userData User data
 * @param userData.username Username
 * @param userData.password Password
 * @param userData.email Email address
 * @param userData.role User role
 * @param userData.login Whether to login after creating user
 */
export async function createUser(
	page: Page,
	userData: {
		username?: string;
		password?: string;
		email?: string;
		role?: string;
		login?: boolean;
	},
) {
	const newUserData = {
		username: 'testuser',
		password: 'password',
		email: 'testuser@example.com',
		role: 'subscriber',
		login: false,
		...userData,
	};

	// Delete the user if exists
	await wpCli(`wp user delete ${newUserData.username} --yes --network`, true);

	// Create the user
	await wpCli(
		`wp user create ${newUserData.username} ${newUserData.email} --user_pass=${newUserData.password} --role=${newUserData.role}`,
	);

	if (newUserData.login) {
		await login(page, newUserData.username, newUserData.password);
	}
}

/**
 * Set per index cycle
 * @param number Number of items per index cycle
 */
export async function setPerIndexCycle(number = 350) {
	await wpCli(`option set ep_bulk_setting ${number}`);
}

/**
 * Refresh index
 * @param indexable Indexable to refresh
 */
export async function refreshIndex(indexable: string) {
	const output = await wpCliEval(
		`
		$index = \\ElasticPress\\Indexables::factory()->get( '${indexable}' )->get_index_name();
		$output = WP_CLI::runcommand( "elasticpress request {$index}/_refresh --method=POST", [ 'return' => 'all', 'exit_error' => false ] );
		print_r( $output );
		`,
	);
	if (process.env.PWDEBUG === '1') {
		// eslint-disable-next-line no-console
		console.log(output.toString());
	}
}

export function getSyncTimeout(): number {
	return parseInt(process.env?.EP_INDEX_TIMEOUT || '30000', 10);
}

export async function setDefaultFeatureSettings() {
	const wpCliResponse = await wpCliEval(
		`
		\\ElasticPress\\IndexHelper::factory()->clear_index_meta();

		$features = json_decode( '${JSON.stringify(defaultFeatures)}', true );

		$is_epio = (int) \\ElasticPress\\Utils\\is_epio();

		if ( ! $is_epio ) {
			$host            = \\ElasticPress\\Utils\\get_host();
			$host            = str_replace( '172.17.0.1', 'localhost', $host );
			$host            = str_replace( 'host.docker.internal', 'localhost', $host );
			$index_name      = \\ElasticPress\\Indexables::factory()->get( 'post' )->get_index_name();
			$as_endpoint_url = $host . $index_name . '/_search';

			$features['autosuggest']['endpoint_url'] = $as_endpoint_url;
		}

		update_option( 'ep_feature_settings', $features );
		update_option( 'ep_feature_settings_draft', $features );

		$index_names = \\ElasticPress\\Elasticsearch::factory()->get_index_names( 'active' );
		echo wp_json_encode(
			[
				'indexNames' => $index_names,
				'isEpIo'     => $is_epio,
				'wpVersion'  => get_bloginfo( 'version' ),
			]
		);
		`,
	);
	const raw = wpCliResponse?.toString() ?? '';
	// Slice from the payload key, not the first `{`. PHP deprecation text can
	// contain `${var}` and would otherwise produce invalid JSON.
	const jsonStart = raw.indexOf('{"indexNames"');
	if (jsonStart === -1) {
		throw new Error(`setDefaultFeatureSettings: no JSON in WP-CLI output:\n${raw}`);
	}
	try {
		return JSON.parse(raw.slice(jsonStart));
	} catch (error) {
		throw new Error(
			`setDefaultFeatureSettings: failed to parse WP-CLI output:\n${raw.slice(0, 500)}`,
		);
	}
}

/**
 * Create a post with autosave enabled
 * @param page Playwright page object
 * @param postData Post data
 * @param postData.title Post title
 * @param postData.content Post content
 */
export async function createAutosavePost(
	page: Page,
	postData: { title?: string; content?: string },
) {
	// Activate the shorten-autosave plugin
	await activatePlugin(page, 'shorten-autosave', 'wpCli');

	const newPostData = { title: 'Test Post', content: 'Test content.', ...postData };

	await goToAdminPage(page, 'post-new.php');
	const editorFrame = await getEditorFrame(page);

	await editorFrame.locator('h1.editor-post-title__input, #post-title-0').fill(newPostData.title);
	await editorFrame
		.locator('.block-editor-default-block-appender__content')
		.pressSequentially(newPostData.content);

	// Wait for autosave to complete
	await page.waitForTimeout(5000);

	// Deactivate the shorten-autosave plugin
	await deactivatePlugin(page, 'shorten-autosave', 'wpCli');
}

export async function setCustomPostTypes() {
	const output = await wpCliEval(
		`
		$plugin_file = 'cpt-and-custom-tax.php';
		$activated     = activate_plugin( $plugin_file );
		if ( is_wp_error( $activated ) && ! is_plugin_active( $plugin_file ) ) {
			print_r( "\nError activating cpt-and-custom-tax\n" );
			print_r( $activated );
		}

		// init has already run in this WP-CLI request, so register now.
		if ( ! taxonomy_exists( 'genre' ) ) {
			include_once WP_PLUGIN_DIR . '/' . $plugin_file;
			\\ElasticPress\\Tests\\E2e\\create_post_type();
			\\ElasticPress\\Tests\\E2e\\create_taxonomy();
		}
		flush_rewrite_rules();

		foreach ( [ 'post', 'page', 'movie' ] as $type ) {
			$existing = get_posts(
				[
					'post_type'      => $type,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'ep_integrate'   => false,
				]
			);
			foreach ( $existing as $post ) {
				if ( in_array( $post->post_title, [ 'A new page', 'A new post', 'A new movie' ], true ) ) {
					wp_delete_post( $post->ID, true );
				}
			}
		}

		$page_id = wp_insert_post(
			[
				'post_title'  => 'A new page',
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);
		$post_id = wp_insert_post(
			[
				'post_title'  => 'A new post',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$movie_id = wp_insert_post(
			[
				'post_title'  => 'A new movie',
				'post_type'   => 'movie',
				'post_status' => 'publish',
			]
		);
		wp_set_object_terms( $movie_id, 'action', 'genre' );
		WP_CLI::runcommand( "elasticpress sync --include={$page_id},{$post_id},{$movie_id}", [ 'return' => true ] );
		`,
	);
	if (process.env.PWDEBUG === '1') {
		// eslint-disable-next-line no-console
		console.log(output.toString());
	}

	await refreshIndex('post');
}
