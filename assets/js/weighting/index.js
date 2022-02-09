/**
 * WordPress dependencies.
 */
import { render, WPElement, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies.
 */
import FieldGroup from './components/field-group';
import { dummyData } from './dummyData';
import { sortListByOrder } from './utils';

/**
 * component.
 *
 * @return {WPElement} Element.
 */
const App = () => {
	const [formData, setFormData] = useState([]);
	const [savedData, setSavedData] = useState([]);
	const [isSaving, setIsSaving] = useState(false);

	const hasChanges = JSON.stringify(savedData) !== JSON.stringify(formData);

	/**
	 * Fetch api response on component mount and set instead of dummy data.
	 */
	useEffect(() => {
		setTimeout(() => {
			setSavedData(dummyData);
			setFormData(dummyData);
		}, 200);
	}, []);

	/**
	 * Get currently editing form type
	 *
	 * @param {string} postType type of the wp post.
	 * @return {Object} currently editing form.
	 */
	const getCurrentFormData = (postType) => formData.find((item) => item.name === postType);

	/**
	 * Get currently editing form type
	 *
	 * @param {string} postType type of the wp post.
	 * @return {Object} currently editing form.
	 */
	const getSavedFormData = (postType) => savedData.find((item) => item.name === postType);

	const undoHandler = (props) => {
		const { currentIndex, fieldType, postType, attribute } = props;
		let currentFormData = getCurrentFormData(postType.name);
		const savedFormData = getSavedFormData(postType.name);

		currentFormData = {
			...currentFormData,
			[fieldType]: currentFormData[fieldType].map((item) => {
				let newItem = item;
				if (item.name === attribute.name) {
					newItem = savedFormData[fieldType][currentIndex];
				}
				return newItem;
			}),
		};

		const excludeOldCurrentFormData = formData.filter((item) => item.name !== postType.name);

		const newFormData = [...excludeOldCurrentFormData, currentFormData];

		setFormData(sortListByOrder(newFormData));
	};

	/**
	 * Handle input changes.
	 *
	 * @param {Object} event react synthetic event
	 * @param {string} postType wp post type
	 * @param {string} type type of the field
	 * @param {string} attributeName field attribute name
	 */
	const onChangeHandler = (event, postType, type, attributeName) => {
		let currentFormData = getCurrentFormData(postType);

		if (type === 'root') {
			currentFormData = { ...currentFormData, indexable: !currentFormData.indexable };
		} else {
			currentFormData = {
				...currentFormData,
				[type]: currentFormData[type].map((item) => {
					let newItem = item;
					if (typeof event === 'number' && item.name === attributeName) {
						newItem = { ...newItem, weight: event };
					} else if (item.name === attributeName) {
						newItem = { ...newItem, [event.target.name]: !newItem[event.target.name] };
					}
					return newItem;
				}),
			};
		}

		const excludeOldCurrentFormData = formData.filter((item) => item.name !== postType);

		const newFormData = [...excludeOldCurrentFormData, currentFormData];

		setFormData(sortListByOrder(newFormData));
	};

	/**
	 * Reset all changes of the form.
	 */
	const resetAllChanges = () => {
		setFormData(sortListByOrder(dummyData));
	};

	/**
	 * Reset current form changes.
	 *
	 * @param {string} postType name of the post type.
	 */
	const resetCurrentFormChanges = (postType) => {
		const savedFormData = getSavedFormData(postType);
		const excludeOldCurrentFormData = formData.filter((item) => item.name !== postType);

		const newFormData = [...excludeOldCurrentFormData, savedFormData];

		setFormData(sortListByOrder(newFormData));
	};

	/**
	 * Check if current has changes.
	 *
	 * @param {string} postType name of the post type.
	 */
	const hasCurrentFormChanges = (postType) =>
		JSON.stringify(getCurrentFormData(postType)) !== JSON.stringify(getSavedFormData(postType));

	/**
	 * Handle for submission.
	 *
	 * @param {Object} event react synthetic event.
	 */
	const handleSubmit = (event) => {
		event.preventDefault();
		setIsSaving(true);

		// do api request to save formData
		setTimeout(() => {
			console.log({ formData });
		}, 500);

		setIsSaving(false);
	};

	return (
		<form className="weighting-settings metabox-holder">
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
			{formData.map((postType) => (
				<div className="postbox" key={postType.name}>
					<div className="postbox__header">
						<h2 className="hndle">{postType.label}</h2>
						<fieldset>
							<label htmlFor={`postbox-${postType.name}`}>
								<input
									type="checkbox"
									id={`postbox-${postType.name}`}
									onChange={(event) =>
										onChangeHandler(event, postType.name, 'root')
									}
									checked={getCurrentFormData(postType.name).indexable}
								/>
								<span>{__('Index', 'elasticpress')}</span>
							</label>
						</fieldset>

						{hasCurrentFormChanges(postType.name) && (
							<button
								type="button"
								className="undo-changes"
								onClick={() => resetCurrentFormChanges(postType.name)}
							>
								{__('Undo all changes', 'elasticpress')}
							</button>
						)}
					</div>

					{postType.indexable && (
						<FieldGroup
							postType={postType}
							onChangeHandler={onChangeHandler}
							getCurrentFormData={getCurrentFormData}
							formData={formData}
							savedData={savedData}
							setFormData={setFormData}
							undoHandler={undoHandler}
						/>
					)}
				</div>
			))}

			<div className="submit__button postbox">
				<button
					type="button"
					className="button button-primary"
					onClick={handleSubmit}
					disabled={!hasChanges}
				>
					{isSaving ? __('Saving…', 'elasticpress') : __('Save changes', 'elasticpress')}
				</button>

				<span className="note">
					{hasChanges
						? __('Please re-sync your data after saving.', 'elasticpress')
						: __('You have nothing to save.', 'elasticpress')}
				</span>

				<button
					type="button"
					className="button button-primary reset-all-changes-button"
					onClick={resetAllChanges}
					disabled={!hasChanges}
				>
					{__('Reset all changes', 'elasticpress')}
				</button>
			</div>
		</form>
	);
};

render(<App />, document.getElementById('ep-weighting-screen'));
