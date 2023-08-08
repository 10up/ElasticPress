/**
 * WordPress dependencies.
 */
import { SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Key selection component.
 *
 * @param {object} props Component props.
 * @param {Function} props.onChange Change handler.
 * @param {string} props.value Selected facet.
 * @returns {WPElement} Component element.
 */
export default ({ onChange, value }) => {
	/**
	 * Filterable meta keys from ElasticPress data store.
	 */
	const taxonomies = useSelect((select) => select('elasticpress').getTaxonomies());

	/**
	 * Key options.
	 */
	const options = useMemo(() => {
		return [
			{
				label: __('Select taxonomy', 'elasticpress'),
				value: '',
			},
			...Object.entries(taxonomies).map(([slug, taxonomy]) => ({
				label: `${taxonomy.label} (${slug})`,
				value: slug,
			})),
		];
	}, [taxonomies]);

	return (
		<SelectControl
			disabled={options.length <= 1}
			help=""
			label={__('Filter by', 'elasticpress')}
			onChange={onChange}
			options={options}
			value={value}
		/>
	);
};
