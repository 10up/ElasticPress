/**
 * Wordpress Dependecies.
 */
import { useState } from '@wordpress/element';
import AsyncSelect from 'react-select/async';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

/**
 * Internal Dependencies.
 */
import FieldItem from './field-item';
import MetaItem from './meta-Item';
import { sortListByOrder } from '../utils';

const FieldGroup = ({
	postType,
	onChangeHandler,
	getCurrentFormData,
	formData,
	setFormData,
	undoHandler,
	savedData,
}) => {
	const [selectedValue, setSelectedValue] = useState(null);

	// handle selection
	const handleChange = (value) => {
		let currentFormData = getCurrentFormData(postType.name);
		if (currentFormData.metas.findIndex((metaItem) => metaItem.name === value.name) === -1) {
			currentFormData = {
				...currentFormData,
				metas: [...currentFormData.metas, value],
			};
		}
		const excludedFormData = formData.filter((item) => item.name !== postType.name);
		const newFormData = [...excludedFormData, currentFormData];
		setFormData(sortListByOrder(newFormData));
		setSelectedValue(value);
	};

	// load options using API call
	const loadOptions = (inputValue) => {
		return fetch(
			`https://jsonplaceholder.typicode.com/posts?search=${inputValue}$post_type=${postType.name}`,
		)
			.then((res) => res.json())
			.then((result) => {
				const newResult = [];
				result.forEach((item) => {
					newResult.push({
						name: item.title,
						searchable: false,
						weight: 10,
					});
				});
				return newResult;
			});
	};

	const removeMetaItem = (metaName) => {
		const newCurrentFormData = {
			...getCurrentFormData(postType.name),
			metas: getCurrentFormData(postType.name).metas.filter((item) => item.name !== metaName),
		};

		const excludedFormData = formData.filter((item) => item.name !== postType.name);
		const newFormData = [...excludedFormData, newCurrentFormData];
		setFormData(sortListByOrder(newFormData));
	};

	const currentOriginalData = savedData.find((item) => item.name === postType.name);

	return (
		<>
			<div className="field-group">
				<h3>{__('Attributes', 'elasticpress')}</h3>
				<div className="fields">
					{postType.attributes.map((attribute, index) => {
						const fieldType = 'attributes';
						const isAttributeChanged =
							JSON.stringify(attribute) !==
							JSON.stringify(currentOriginalData[fieldType][index]);
						return (
							<FieldItem
								key={`${postType.name}-${attribute.name}`}
								attribute={attribute}
								fieldType={fieldType}
								postType={postType}
								onChangeHandler={onChangeHandler}
								getCurrentFormData={getCurrentFormData}
								currentIndex={index}
								undoHandler={undoHandler}
								isAttributeChanged={isAttributeChanged}
							/>
						);
					})}
				</div>
			</div>
			<div className="field-group">
				<h3>{__('Taxonomies', 'elasticpress')}</h3>
				<div className="fields">
					{postType.taxonomies.map((taxonomy, index) => {
						const fieldType = 'taxonomies';
						const isAttributeChanged =
							JSON.stringify(taxonomy) !==
							JSON.stringify(currentOriginalData[fieldType][index]);

						return (
							<FieldItem
								key={`${postType.name}-${taxonomy.name}`}
								attribute={taxonomy}
								fieldType={fieldType}
								postType={postType}
								onChangeHandler={onChangeHandler}
								getCurrentFormData={getCurrentFormData}
								currentIndex={index}
								undoHandler={undoHandler}
								isAttributeChanged={isAttributeChanged}
							/>
						);
					})}
				</div>
			</div>
			<div className="field-group">
				<h3>{__('Meta to be indexed', 'elasticpress')}</h3>
				<div className="fields">
					{getCurrentFormData(postType.name) &&
						getCurrentFormData(postType.name).metas &&
						getCurrentFormData(postType.name).metas.map((meta, index) => {
							const fieldType = 'metas';
							const isAttributeChanged =
								JSON.stringify(meta) !==
								JSON.stringify(currentOriginalData[fieldType][index]);
							return (
								<MetaItem
									key={`${postType.name}-${meta.name}`}
									attribute={meta}
									fieldType={fieldType}
									postType={postType}
									onChangeHandler={onChangeHandler}
									getCurrentFormData={getCurrentFormData}
									currentIndex={index}
									removeMetaItem={removeMetaItem}
									undoHandler={undoHandler}
									isAttributeChanged={isAttributeChanged}
								/>
							);
						})}
					<fieldset className="add-meta-key-wrap">
						<legend>{__('Add Meta Key:', 'elasticpress')} </legend>
						<div className="add-meta-key">
							<AsyncSelect
								cacheOptions
								defaultOptions
								value={selectedValue}
								getOptionLabel={(e) => e.name}
								getOptionValue={(e) => e.name}
								loadOptions={loadOptions}
								// onInputChange={handleInputChange}
								onChange={handleChange}
							/>
						</div>
					</fieldset>
				</div>
			</div>
		</>
	);
};

FieldGroup.propTypes = {
	postType: PropTypes.object.isRequired,
	onChangeHandler: PropTypes.func.isRequired,
	getCurrentFormData: PropTypes.func.isRequired,
	formData: PropTypes.array.isRequired,
	setFormData: PropTypes.func.isRequired,
	undoHandler: PropTypes.func.isRequired,
	savedData: PropTypes.array.isRequired,
};

export default FieldGroup;
