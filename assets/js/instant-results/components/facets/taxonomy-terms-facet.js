/**
 * WordPress dependencies.
 */
import { useCallback, useMemo, WPElement } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useApiSearch } from '../../../api-search';
import { facets, postTypeLabels } from '../../config';
import CheckboxList from '../common/checkbox-list';
import Panel from '../common/panel';
import { ActiveConstraint } from '../tools/active-constraints';

/**
 * Taxonomy filter component.
 *
 * @param {object} props Components props.
 * @param {boolean} props.defaultIsOpen Whether the panel is open by default.
 * @param {string} props.label Facet label.
 * @param {string} props.name Facet name.
 * @param {Array} props.postTypes Facet post types.
 * @returns {WPElement} Component element.
 */
export default ({ defaultIsOpen, label, postTypes, name }) => {
	const {
		aggregations: { [name]: { [name]: { buckets = [] } = {} } = {} } = {},
		args: { [name]: selectedTerms = [] },
		isLoading,
		search,
	} = useApiSearch();

	/**
	 * A unique label for the facet. Adds additional context to the label if
	 * another facet with the same label is being used.
	 */
	const uniqueLabel = useMemo(() => {
		const isNotUnique = facets.some((facet) => facet.label === label && facet.name !== name);
		const typeLabels = postTypes.map((postType) => postTypeLabels[postType].plural);
		const typeSeparator = __(', ', 'elasticpress');

		return isNotUnique
			? sprintf(
					/* translators: %1$s: Facet label. $2$s: Facet post types. */
					__('%1$s (%2$s)', 'elasticpress'),
					label,
					typeLabels.join(typeSeparator),
			  )
			: label;
	}, [label, postTypes, name]);

	/**
	 * Create list of filter options from aggregation buckets.
	 *
	 * @param {Array}  options    List of options.
	 * @param {object} bucket     Aggregation bucket.
	 * @param {string} bucket.key Aggregation key.
	 * @returns {Array} Array of options.
	 */
	const reduceOptions = useCallback(
		(options, { doc_count, key }) => {
			const { name: label, parent, term_id, term_order } = JSON.parse(key);

			options.push({
				checked: selectedTerms.includes(term_id),
				count: doc_count,
				id: `ep-search-${name}-${term_id}`,
				label: decodeEntities(label),
				parent: parent.toString(),
				order: term_order,
				value: term_id.toString(),
			});

			return options;
		},
		[selectedTerms, name],
	);

	/**
	 * Reduce buckets to options.
	 */
	const options = useMemo(() => buckets.reduce(reduceOptions, []), [buckets, reduceOptions]);

	/**
	 * Reduce options to labels.
	 *
	 * @param {object} labels     List of options.
	 * @param {object} bucket     Aggregation bucket.
	 * @param {string} bucket.key Aggregation key.
	 * @returns {object} Options and their labels.
	 */
	const reduceLabels = useCallback((labels, { label, value }) => {
		labels[value] = label;

		return labels;
	}, []);

	/**
	 * Reduce buckets to labels.
	 */
	const labels = options.reduce(reduceLabels, {});

	/**
	 * Handle checkbox change event.
	 *
	 * @param {string[]} terms Selected terms.
	 */
	const onChange = (terms) => {
		search({ [name]: terms });
	};

	/**
	 * Handle clearing a term.
	 *
	 * @param {string} term Term being cleared.
	 */
	const onClear = (term) => {
		const terms = [...selectedTerms];

		terms.splice(terms.indexOf(term), 1);

		search({ [name]: terms });
	};

	return (
		options.length > 0 && (
			<Panel defaultIsOpen={defaultIsOpen} label={uniqueLabel}>
				{(isOpen) => (
					<>
						{isOpen && (
							<CheckboxList
								disabled={isLoading}
								label={sprintf(
									/* translators: %s: Taxonomy name. */
									__('Select %s', 'elasticpress'),
									label,
								)}
								options={options}
								onChange={onChange}
								selected={selectedTerms}
							/>
						)}

						{selectedTerms.map(
							(value) =>
								labels?.[value] && (
									<ActiveConstraint
										key={value}
										label={labels[value]}
										onClick={() => onClear(value)}
									/>
								),
						)}
					</>
				)}
			</Panel>
		)
	);
};
