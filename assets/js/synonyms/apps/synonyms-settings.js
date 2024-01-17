/**
 * WordPress dependencies.
 */
import { Button, Panel, PanelBody, PanelHeader, TabPanel } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSettingsScreen } from '../../settings-screen';
import { useSynonymsSettings } from '../provider';
import GroupTab from '../components/common/group-tab';
import SolrEditor from '../components/editors/solr-editor';
import Hyponyms from '../components/groups/hyponyms';
import Replacements from '../components/groups/replacements';
import Synonyms from '../components/groups/synonyms';

/**
 * Synonyms settings app.
 *
 * @returns {WPElement} App element.
 */
export default () => {
	const { ActionSlot, createNotice } = useSettingsScreen();
	const { isBusy, hyponyms, isSolr, replacements, save, synonyms, switchEditor, syncUrl } =
		useSynonymsSettings();

	/**
	 * Handle clicking the editor switch button.
	 *
	 * @returns {void}
	 */
	const onClick = () => {
		switchEditor();
	};

	/**
	 * Submit event.
	 *
	 * @param {Event} event Submit event.
	 */
	const onSubmit = async (event) => {
		event.preventDefault();

		try {
			await save();
			createNotice('success', __('Synonym settings saved.', 'elasticpress'));
		} catch (e) {
			if (e.code === 'error-update-index') {
				createNotice(
					'error',
					__(
						'Could not update index with synonyms. Make sure your data is synced.',
						'elasticpress',
					),
					{
						actions: [
							{
								url: syncUrl,
								label: __('Sync', 'elasticpress'),
							},
						],
					},
				);
			} else {
				createNotice(
					'error',
					__('Something went wrong. Please try again.', 'elasticpress'),
				);
			}
		}
	};

	/**
	 * Tabs.
	 *
	 * @type {Array}
	 */
	const tabs = [
		{
			name: 'synonyms',
			title: (
				<GroupTab isValid={!synonyms.some((s) => !s.valid)}>
					{sprintf(__('Synonyms (%d)', 'elasticpress'), synonyms.length)}
				</GroupTab>
			),
		},
		{
			name: 'hyponyms',
			title: (
				<GroupTab isValid={!hyponyms.some((s) => !s.valid)}>
					{sprintf(__('Hyponyms (%d)', 'elasticpress'), hyponyms.length)}
				</GroupTab>
			),
		},
		{
			name: 'replacements',
			title: (
				<GroupTab isValid={!replacements.some((s) => !s.valid)}>
					{sprintf(__('Replacements (%d)', 'elasticpress'), replacements.length)}
				</GroupTab>
			),
		},
	];

	return (
		<>
			<ActionSlot>
				<Button onClick={onClick} size="small" type="button" variant="secondary">
					{isSolr
						? __('Switch to visual editor', 'elasticpress')
						: __('Switch to advanced text editor', 'elasticpress')}
				</Button>
			</ActionSlot>
			<p>
				{__(
					'Synonym rules enable a more flexible search experience that returns relevant results even without an exact match. Rules can be defined as synonyms, for terms with similar meanings; hyponyms, for terms with a hierarchical relationship; or replacements, for corrections and substitutions.',
					'elasticpress',
				)}
			</p>
			{!isSolr ? (
				<Panel className="ep-synonyms-panel">
					<TabPanel tabs={tabs}>
						{({ name }) => (
							<PanelBody>
								{() => {
									switch (name) {
										case 'hyponyms':
											return <Hyponyms />;
										case 'replacements':
											return <Replacements />;
										case 'synonyms':
										default:
											return <Synonyms />;
									}
								}}
							</PanelBody>
						)}
					</TabPanel>
				</Panel>
			) : (
				<Panel className="ep-synonyms-panel">
					<PanelHeader>
						<h2>{__('Advanced Synonyms Editor', 'elasticpress')}</h2>
					</PanelHeader>
					<PanelBody>
						<p>
							{__(
								'ElasticPress uses the Solr format to define your synonym rules for Elasticsearch. Advanced users can use the field below to edit the synonym rules in this format directly. This can also be used to import a large dictionary of synonyms, or to export your synonyms for use on another site.',
								'elasticpress',
							)}
						</p>
						<SolrEditor />
					</PanelBody>
				</Panel>
			)}
			<Button
				disabled={isBusy}
				isBusy={isBusy}
				onClick={onSubmit}
				type="button"
				variant="primary"
			>
				{__('Save changes', 'elasticpress')}
			</Button>
		</>
	);
};
