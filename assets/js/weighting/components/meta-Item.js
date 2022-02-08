/**
 * WordPress dependencies.
 */
import { RangeControl } from '@wordpress/components';
import { closeSmall, Icon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

const MetaItem = ({
	postType,
	attribute,
	fieldType,
	onChangeHandler,
	getCurrentFormData,
	currentIndex,
	removeMetaItem,
}) => {
	return (
		<fieldset className="field-item">
			<span className="remove-meta-item">
				<Icon onClick={() => removeMetaItem(attribute.name)} icon={closeSmall} />
			</span>
			<legend>{attribute.name}</legend>

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
							getCurrentFormData(postType.name)[fieldType][currentIndex].searchable
						}
					/>
					<span>{__('Searchable', 'elasticpress')}</span>
				</label>
			</p>

			{getCurrentFormData(postType.name)[fieldType][currentIndex].searchable && (
				<p className="weighting">
					<RangeControl
						label={`${__('Weight:', 'elasticpress')} ${
							getCurrentFormData(postType.name)[fieldType][currentIndex].weight
						}`}
						showTooltip={false}
						value={getCurrentFormData(postType.name)[fieldType][currentIndex].weight}
						onChange={(value) =>
							onChangeHandler(value, postType.name, fieldType, attribute.name)
						}
						min={1}
						max={100}
					/>
				</p>
			)}
		</fieldset>
	);
};

MetaItem.propTypes = {
	postType: PropTypes.object.isRequired,
	attribute: PropTypes.object.isRequired,
	fieldType: PropTypes.string.isRequired,
	onChangeHandler: PropTypes.func.isRequired,
	getCurrentFormData: PropTypes.func.isRequired,
	currentIndex: PropTypes.number.isRequired,
	removeMetaItem: PropTypes.func.isRequired,
};

export default MetaItem;
