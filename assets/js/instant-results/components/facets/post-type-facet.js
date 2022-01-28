/**
 * WordPress dependencies.
 */
import { useCallback, useContext, useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { postTypeLabels } from '../../config';
import Context from '../../context';
import Panel from '../common/panel';
import CheckboxList from '../common/checkbox-list';
import { ActiveContraint } from '../tools/active-constraints';

/**
 * Post type facet component.
 *
 * @param {Object}  props               Props.
 * @param {boolean} props.defaultIsOpen Whether the panel is open by default.
 * @param {string}  props.label         Facet label.
 * @return {WPElement} Component element.
 */
export default ({ defaultIsOpen, label }) => {
	const {
		state: {
			isLoading,
			filters: { post_type: selectedPostTypes = [] },
			postTypesAggregation: { post_types: { buckets = [] } = {} } = {},
		},
		dispatch,
	} = useContext(Context);

	/**
	 * Create list of filter options from aggregation buckets.
	 *
	 * @param {Array}  options    List of options.
	 * @param {Object} bucket     Aggregation bucket.
	 * @param {string} bucket.key Aggregation key.
	 * @param {number} index      Bucket index.
	 * @return {Array} Array of options.
	 */
	const reduceOptions = useCallback(
		(options, { key }, index) => {
			if (!Object.prototype.hasOwnProperty.call(postTypeLabels, key)) {
				return options;
			}

			options.push({
				checked: selectedPostTypes.includes(key),
				id: `ep-search-post-type-${key}`,
				label: postTypeLabels[key].singular,
				order: index,
				value: key,
			});

			return options;
		},
		[selectedPostTypes],
	);

	/**
	 * Reduce buckets to options.
	 */
	const options = useMemo(() => buckets.reduce(reduceOptions, []), [buckets, reduceOptions]);

	/**
	 * Handle checkbox change event.
	 *
	 * @param {string[]} postTypes Selected post types.
	 */
	const onChange = (postTypes) => {
		dispatch({ type: 'APPLY_FILTERS', payload: { post_type: postTypes } });
	};

	/**
	 * Handle clearing a post type.
	 *
	 * @param {string} postType Post type being cleared.
	 */
	const onClear = (postType) => {
		const postTypes = [...selectedPostTypes];
		const index = postTypes.indexOf(postType);

		postTypes.splice(index, 1);

		dispatch({ type: 'APPLY_FILTERS', payload: { post_type: postTypes } });
	};

	return (
		options.length > 0 && (
			<Panel defaultIsOpen={defaultIsOpen} label={label}>
				{() => (
					<>
						<CheckboxList
							disabled={isLoading}
							label={__('Select content type', 'elasticpress')}
							options={options}
							onChange={onChange}
							selected={selectedPostTypes}
						/>

						{selectedPostTypes.map((value) => (
							<ActiveContraint
								key={value}
								label={postTypeLabels[value].singular}
								onClick={() => onClear(value)}
							/>
						))}
					</>
				)}
			</Panel>
		)
	);
};
