/**
 * WordPress dependencies.
 */
import { WPElement, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { cloneDeep, isEqual } from 'lodash';

/**
 * Internal Dependencies.
 */
import Actions from './weighting/actions';
import PostType from './weighting/post-type';

/**
 * Weighting settings app.
 *
 * @param {object} props Component props.
 * @param {object} props.weightableFields Weightable fields, indexed by post type.
 * @param {object} props.weightingConfiguration Weighting configuration, indexed by post type.
 * @returns {WPElement} Element.
 */
export default ({ weightableFields, weightingConfiguration }) => {
	const [currentData, setCurrentData] = useState({ ...weightingConfiguration });
	const [savedData, setSavedData] = useState({ ...weightingConfiguration });
	const [isBusy, setIsBusy] = useState(false);

	/**
	 * Is the current data different to the saved data.
	 */
	const isChanged = useMemo(() => !isEqual(currentData, savedData), [currentData, savedData]);

	/**
	 * Handle data change.
	 *
	 * @param {string} postType Updated post type.
	 * @param {Array} values Updated data.
	 * @returns {void}
	 */
	const onChangePostType = (postType, values) => {
		setCurrentData({ ...currentData, [postType]: values });
	};

	/**
	 * Handle resetting all settings.
	 *
	 * @returns {void}
	 */
	const onReset = () => {
		setCurrentData({ ...savedData });
	};

	/**
	 * Handle for submission.
	 *
	 * @param {Event} event Submit event.
	 * @returns {void}
	 */
	const onSubmit = (event) => {
		event.preventDefault();

		const savedData = cloneDeep(currentData);

		setIsBusy(true);

		setTimeout(() => {
			setSavedData(savedData);
			setIsBusy(false);
		}, 1000);
	};

	/**
	 * Render.
	 */
	return (
		<form className="weighting-settings" onSubmit={onSubmit}>
			<h1 className="page-title">{__('Manage Search Fields & Weighting', 'elasticpress')}</h1>
			<div className="page-description">
				<p>
					{__(
						'Adding more weight to an item will mean it will have more presence during searches. Add more weight to the items that are more important and need more prominence during searches. For example, adding more weight to the title attribute will cause search matches on the post title to apear mor prominently.',
						'elasticpress',
					)}
				</p>
				<p>
					{__(
						'Important: If you enable or disable indexing for a field, you will need to refresh your index after saving your settings',
						'elasticpress',
					)}
				</p>
			</div>
			{Object.entries(weightableFields).map(([postType, { groups, label }]) => {
				const originalValues = savedData[postType] || {};
				const values = currentData[postType] || {};

				return (
					<PostType
						groups={groups}
						key={postType}
						label={label}
						onChange={(values) => {
							onChangePostType(postType, values);
						}}
						originalValues={originalValues}
						values={values}
					/>
				);
			})}
			<Actions isBusy={isBusy} isChanged={isChanged} onReset={onReset} onSubmit={onSubmit} />
		</form>
	);
};
