/**
 * WordPress dependencies.
 */
import { useCallback, useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useApiSearch } from '../../../api-search';
import { postTypeLabels } from '../../config';
import CheckboxList from '../common/checkbox-list';
import Panel from '../common/panel';
import { ActiveConstraint } from '../tools/active-constraints';

/**
 * Post type facet component.
 *
 * @param {object}  props               Props.
 * @param {boolean} props.defaultIsOpen Whether the panel is open by default.
 * @param {string}  props.label         Facet label.
 * @returns {WPElement} Component element.
 */
export default ({ defaultIsOpen, label }) => {
	const {
		aggregations: { post_type: { post_type: { buckets = [] } = {} } = {} },
		args: { post_type: selectedPostTypes = [] },
		isLoading,
		search,
	} = useApiSearch();

	/**
	 * Create list of filter options from aggregation buckets.
	 *
	 * @param {Array}  options    List of options.
	 * @param {object} bucket     Aggregation bucket.
	 * @param {string} bucket.key Aggregation key.
	 * @param {number} index      Bucket index.
	 * @returns {Array} Array of options.
	 */
	const reduceOptions = useCallback(
		(options, { doc_count, key }, index) => {
			if (!Object.prototype.hasOwnProperty.call(postTypeLabels, key)) {
				return options;
			}

			options.push({
				checked: selectedPostTypes.includes(key),
				count: doc_count,
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
		search({ post_type: postTypes });
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

		search({ post_type: postTypes });
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
							<ActiveConstraint
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
