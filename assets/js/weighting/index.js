/**
 * WordPress dependencies.
 */
import { render, WPElement, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { cloneDeep, isEqual } from 'lodash';

/**
 * Internal Dependencies.
 */
import { dummyData } from './dummyData';
import PostType from './components/post-type';
import Save from './components/save';

/**
 * component.
 *
 * @returns {WPElement} Element.
 */
const App = () => {
	const [data, setData] = useState(cloneDeep(dummyData));
	const [savedData, setSavedData] = useState(cloneDeep(dummyData));
	const [isBusy, setIsBusy] = useState(false);

	/**
	 * Is the current data different to the saved data.
	 */
	const isChanged = useMemo(() => !isEqual(data, savedData), [data, savedData]);

	/**
	 * Handle data change.
	 *
	 * @param {Array} value Updated data.
	 * @returns {void}
	 */
	const onChange = (value) => {
		setData(value);
	};

	/**
	 * Handle for submission.
	 *
	 * @param {Event} event Submit event.
	 * @returns {void}
	 */
	const onSubmit = (event) => {
		event.preventDefault();

		const savedData = cloneDeep(data);

		setIsBusy(true);
		setSavedData(savedData);
	};

	/**
	 * Handle resetting all settings.
	 *
	 * @returns {void}
	 */
	const onUndo = () => {
		const data = cloneDeep(savedData);

		setData(data);
	};

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

			{data.map((value, index) => (
				<PostType
					key={value.name}
					label={value.label}
					onChange={(value) => {
						const newValue = [...data];

						newValue.splice(index, 1, value);

						onChange(newValue);
					}}
					originalValue={savedData[index]}
					value={value}
				/>
			))}

			<Save isBusy={isBusy} isChanged={isChanged} onReset={onUndo} />
		</form>
	);
};

render(<App />, document.getElementById('ep-weighting-screen'));
