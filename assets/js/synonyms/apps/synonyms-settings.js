/**
 * WordPress dependencies.
 */
import { Button, Panel, PanelBody, PanelHeader, TabPanel } from '@wordpress/components';
import { WPElement } from '@wordpress/element';

import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSynonymsSettings } from '../provider';
import Tab from '../components/shared/tab';
import Hyponyms from '../components/hyponyms';
import Replacements from '../components/replacements';
import SolrEditor from '../components/editors/SolrEditor';
import Synonyms from '../components/synonyms';

/**
 * Synonyms editor component.
 *
 * @returns {WPElement} Synonyms editor component.
 */
const SynonymsEditor = () => {
	const { hyponyms, isSolr, replacements, synonyms, switchEditor } = useSynonymsSettings();

	/**
	 * Handle clicking the editor switch button.
	 *
	 * @returns {void}
	 */
	const onClick = () => {
		switchEditor();
	};

	/**
	 * Handles submitting the form.
	 *
	 * @returns {void}
	 */
	const onSubmit = () => {
		return null;
	};

	/**
	 * Tabs.
	 */
	const tabs = [
		{
			name: 'synonyms',
			title: (
				<Tab isInvalid={synonyms.some((s) => !s.valid)}>
					{sprintf(__('Synonyms (%d)', 'elasticpress'), synonyms.length)}
				</Tab>
			),
		},
		{
			name: 'hyponyms',
			title: (
				<Tab isInvalid={hyponyms.some((s) => !s.valid)}>
					{sprintf(__('Hyponyms (%d)', 'elasticpress'), hyponyms.length)}
				</Tab>
			),
		},
		{
			name: 'replacements',
			title: (
				<Tab isInvalid={replacements.some((s) => !s.valid)}>
					{sprintf(__('Replacements (%d)', 'elasticpress'), replacements.length)}
				</Tab>
			),
		},
	];

	return (
		<>
			<button onClick={onClick} type="button">
				{isSolr
					? __('Switch to Visual Editor', 'elasticpress')
					: __('Switch to Advanced Text Editor', 'elasticpress')}
			</button>

			<p>
				{__(
					'Synonyms enable more flexible search results that show relevant results even without an exact match. Synonyms can be defined as a sets where all words are synonyms for each other, or as alternatives where searches for the primary word will also match the rest, but no vice versa.',
					'elasticpress',
				)}
			</p>

			{!isSolr ? (
				<Panel className="ep-synonyms-panel">
					<PanelBody>
						<TabPanel tabs={tabs}>
							{({ name }) => {
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
						</TabPanel>
					</PanelBody>
				</Panel>
			) : (
				<Panel>
					<PanelHeader>
						<h2>{__('Advanced Synonym Editor', 'elasticpress')}</h2>
					</PanelHeader>
					<PanelBody>
						<p>
							{__(
								"When you add Sets and Alternatives above, we reduce them to SolrSynonyms which Elasticsearch can understand. If you are an advanced user, you can edit synonyms directly using Solr synonym formatting. This is beneficial if you want to import a large dictionary of synonyms, or want to export this site's synonyms for use on another site.",
								'elasticpress',
							)}
						</p>
						<SolrEditor />
					</PanelBody>
				</Panel>
			)}

			<Button onClick={onSubmit} type="button" variant="primary">
				{__('Save changes', 'elasticpress')}
			</Button>
		</>
	);
};

export default SynonymsEditor;
