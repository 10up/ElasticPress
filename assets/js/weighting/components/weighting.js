/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { WPElement, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isEqual } from 'lodash';

/**
 * Internal Dependencies.
 */
import Actions from './weighting/actions';
import MetaMode from './weighting/meta-mode';
import PostType from './weighting/post-type';

/**
 * Weighting settings app.
 *
 * @param {object} props Component props.
 * @param {string} props.apiUrl API URL.
 * @param {'auto'|'manual'} props.metaMode Metadata management mode.
 * @param {object} props.weightableFields Weightable fields, indexed by post type.
 * @param {object} props.weightingConfiguration Weighting configuration, indexed by post type.
 * @returns {WPElement} Element.
 */
export default ({ apiUrl, metaMode, weightableFields, weightingConfiguration }) => {
	const [currentMetaMode, setCurrentMetaMode] = useState(metaMode);
	const [currentWeightingConfiguration, setCurrentWeightingConfiguration] = useState({
		...weightingConfiguration,
	});

	const [savedMetaMode, setSavedMetaMode] = useState(metaMode);
	const [savedWeightingConfiguration, setSavedWeightingConfiguration] = useState({
		...weightingConfiguration,
	});

	const [isBusy, setIsBusy] = useState(false);

	/**
	 * Is the current data different to the saved data.
	 */
	const isChanged = useMemo(
		() =>
			!(
				currentMetaMode === savedMetaMode &&
				isEqual(currentWeightingConfiguration, savedWeightingConfiguration)
			),
		[
			currentWeightingConfiguration,
			currentMetaMode,
			savedWeightingConfiguration,
			savedMetaMode,
		],
	);

	/**
	 * Whether to show weighting for metadata.
	 */
	const showMeta = useMemo(() => currentMetaMode === 'manual', [currentMetaMode]);

	/**
	 * Handle meta mode change.
	 *
	 * @param {boolean} checked Is manual checked?
	 */
	const onChangeMetaMode = (checked) => {
		const metaMode = checked ? 'manual' : 'auto';

		setCurrentMetaMode(metaMode);
	};

	/**
	 * Handle data change.
	 *
	 * @param {string} postType Updated post type.
	 * @param {Array} values Updated data.
	 * @returns {void}
	 */
	const onChangePostType = (postType, values) => {
		setCurrentWeightingConfiguration({ ...currentWeightingConfiguration, [postType]: values });
	};

	/**
	 * Handle resetting all settings.
	 *
	 * @returns {void}
	 */
	const onReset = () => {
		setCurrentMetaMode(savedMetaMode);
		setCurrentWeightingConfiguration({ ...savedWeightingConfiguration });
	};

	/**
	 * Handle for submission.
	 *
	 * @param {Event} event Submit event.
	 * @returns {void}
	 */
	const onSubmit = async (event) => {
		event.preventDefault();

		try {
			setIsBusy(true);

			const response = await apiFetch({
				body: JSON.stringify({
					meta_mode: currentMetaMode,
					weighting_configuration: currentWeightingConfiguration,
				}),
				headers: {
					'Content-Type': 'application/json',
				},
				method: 'POST',
				url: apiUrl,
			});

			const { meta_mode, weighting_configuration } = response.data;

			setSavedWeightingConfiguration(weighting_configuration);
			setSavedMetaMode(meta_mode);
		} finally {
			setIsBusy(false);
		}
	};

	/**
	 * Render.
	 */
	return (
		<form className="ep-weighting-screen" onSubmit={onSubmit}>
			<h1 className="page-title">{__('Manage Search Fields & Weighting', 'elasticpress')}</h1>
			<div className="page-description">
				<p>
					{__(
						'Adding more weight to an item will mean it will have more presence during searches.Add more weight to the items that are more important and need more prominence during searches.',
						'elasticpress',
					)}
				</p>
				<p>
					{__(
						'For example, adding more weight to the title attribute will cause search matches on the post title to appear more prominently.',
						'elasticpress',
					)}
				</p>
			</div>
			<MetaMode checked={showMeta} onChange={onChangeMetaMode} />
			{Object.entries(weightableFields).map(([postType, { groups, label }]) => {
				const originalValues = savedWeightingConfiguration[postType] || {};
				const values = currentWeightingConfiguration[postType] || {};

				return (
					<PostType
						groups={groups}
						key={postType}
						label={label}
						onChange={(values) => {
							onChangePostType(postType, values);
						}}
						originalValues={originalValues}
						showMeta={showMeta}
						values={values}
					/>
				);
			})}
			<Actions isBusy={isBusy} isChanged={isChanged} onReset={onReset} onSubmit={onSubmit} />
		</form>
	);
};
