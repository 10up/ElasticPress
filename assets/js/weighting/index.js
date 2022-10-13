/**
 * WordPress dependencies.
 */
import { render, WPElement, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { cloneDeep, isEqual } from 'lodash';

/**
 * Internal Dependencies.
 */
import Actions from './components/actions';
import PostType from './components/post-type';

/**
 * Window dependencies.
 */

/**
 * Weighting settings app.
 *
 * @param {object} props Component props.
 * @param {object} props.weightableFields Weightable fields, indexed by post type.
 * @param {object} props.weightingConfiguration Weighting configuration, indexed by post type.
 * @returns {WPElement} Element.
 */
const App = ({ weightableFields, weightingConfiguration }) => {
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
	 * Handle resetting all settings.
	 *
	 * @returns {void}
	 */
	const onReset = () => {
		setCurrentData({ ...savedData });
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
			{Object.entries(weightableFields).map(([postType, { groups, label }]) => (
				<PostType
					groups={groups}
					key={postType}
					label={label}
					onChange={(values) => {
						onChangePostType(postType, values);
					}}
					originalValues={savedData[postType]}
					values={currentData[postType]}
				/>
			))}
			<Actions isBusy={isBusy} isChanged={isChanged} onReset={onReset} onSubmit={onSubmit} />
		</form>
	);
};

/**
 * Initialize.
 *
 * @returns {void}
 */
const init = () => {
	const { weightableFields, weightingConfiguration } = window.epWeighting;

	render(
		<App weightingConfiguration={weightingConfiguration} weightableFields={weightableFields} />,
		document.getElementById('ep-weighting-screen'),
	);
};

init();
