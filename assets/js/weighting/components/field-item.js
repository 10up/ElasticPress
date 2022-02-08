/**
 * Wordpress Dependencies.
 */
import { RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

/**
 * Internal Dependecies.
 */
import UndoChanges from './undo-changes';

const FieldItem = (props) => {
	const {
		postType,
		attribute,
		fieldType,
		onChangeHandler,
		getCurrentFormData,
		currentIndex,
		undoHandler,
		isAttributeChanged,
	} = props;

	return (
		<fieldset className="field-item">
			<legend>{attribute.label}</legend>

			<p className="indexable">
				<label htmlFor={`${postType.name}-${attribute.name}-indexable`}>
					<input
						type="checkbox"
						id={`${postType.name}-${attribute.name}-indexable`}
						name="indexable"
						onChange={(event) =>
							onChangeHandler(event, postType.name, fieldType, attribute.name)
						}
						checked={
							getCurrentFormData(postType.name)[fieldType][currentIndex].indexable
						}
					/>
					<span>{__('Index', 'elsaticpress')}</span>
				</label>
			</p>

			{getCurrentFormData(postType.name)[fieldType][currentIndex].indexable && (
				<p className="searchable">
					<label htmlFor={`${postType.name}-${attribute.name}-searchable`}>
						<input
							type="checkbox"
							id={`${postType.name}-${attribute.name}-searchable`}
							name="searchable"
							onChange={(event) =>
								onChangeHandler(event, postType.name, fieldType, attribute.name)
							}
							checked={
								getCurrentFormData(postType.name)[fieldType][currentIndex]
									.searchable
							}
						/>
						<span>{__('Searchable', 'elsaticpress')}</span>
					</label>
				</p>
			)}

			{getCurrentFormData(postType.name)[fieldType][currentIndex].searchable &&
				getCurrentFormData(postType.name)[fieldType][currentIndex].indexable && (
					<p className="weighting">
						<RangeControl
							label={`${__('Weight', 'elasticpress')}: ${
								getCurrentFormData(postType.name)[fieldType][currentIndex].weight
							}`}
							showTooltip={false}
							value={
								getCurrentFormData(postType.name)[fieldType][currentIndex].weight
							}
							onChange={(value) =>
								onChangeHandler(value, postType.name, fieldType, attribute.name)
							}
							min={1}
							max={100}
						/>
					</p>
				)}

			{isAttributeChanged && <UndoChanges undoHandler={undoHandler} undoProps={props} />}
		</fieldset>
	);
};

FieldItem.propTypes = {
	postType: PropTypes.object.isRequired,
	attribute: PropTypes.object.isRequired,
	fieldType: PropTypes.string.isRequired,
	onChangeHandler: PropTypes.func.isRequired,
	getCurrentFormData: PropTypes.func.isRequired,
	currentIndex: PropTypes.number.isRequired,
	undoHandler: PropTypes.func.isRequired,
	isAttributeChanged: PropTypes.bool.isRequired,
};

export default FieldItem;
