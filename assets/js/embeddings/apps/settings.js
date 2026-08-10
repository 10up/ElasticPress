/**
 * WordPress dependencies
 */
import { Button, Flex, TabPanel, Panel, PanelBody, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useVectorEmbeddingSettings } from '../provider';
import { useSettingsScreen } from '../../settings-screen';
import PostType from '../components/pages/post-type';
import Indexing from '../components/pages/indexing';

export default () => {
	const { currentSettings, save } = useVectorEmbeddingSettings();
	const { postTypeConfig: postTypes, embeddingsFiltered } = currentSettings;
	const { createNotice } = useSettingsScreen();
	const [currentTab, setCurrentTab] = useState(0); // eslint-disable-line
	const is2columns = window.innerWidth > 782;

	const tabs = postTypes.map((postType) => ({
		title: postType.label,
		name: postType.key,
		postType,
		Component: PostType,
	}));

	tabs.push({
		title: __('Indexing', 'elasticpress'),
		name: 'indexing',
		postType: {},
		Component: Indexing,
	});

	/**
	 * Submit event.
	 *
	 * @param {Event} event Submit event.
	 */
	const onSubmit = async (event) => {
		event.preventDefault();

		try {
			await save();
			createNotice('success', __('Settings saved.', 'elasticpress'));
		} catch (e) {
			createNotice('error', __('Something went wrong. Please try again.', 'elasticpress'));
		}
	};

	return (
		<form className="ep-vector-embedding-settings__post-types-list">
			<p>
				{__(
					'Configure vector embedding settings for each post type. These settings control which posts and what content will be indexed with vector embedding data.',
					'elasticpress',
				)}
			</p>

			<Panel>
				<PanelBody>
					{embeddingsFiltered && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'This configuration is currently filtered via the `ep_embeddings_is_embeddable` filter. Changes made here will not be applied.',
								'elasticpress',
							)}
						</Notice>
					)}
					<TabPanel
						className="ep-vector-embedding-settings__tabs"
						activeClass="ep-vector-embedding-settings__tabs__tab--active"
						onSelect={(val) => setCurrentTab(val)}
						initialTabName={postTypes[0].key}
						orientation={is2columns ? 'vertical' : 'horizontal'}
						tabs={tabs}
					>
						{({ postType, Component }) => {
							return <Component key={postType.key} postType={postType} />;
						}}
					</TabPanel>

					<Flex justify="flex-end" style={{ marginTop: '20px' }}>
						<Button
							className="ep-vector-embeddings-panel__save"
							variant="primary"
							onClick={onSubmit}
						>
							{__('Save settings', 'elasticpress')}
						</Button>
					</Flex>
				</PanelBody>
			</Panel>
		</form>
	);
};
