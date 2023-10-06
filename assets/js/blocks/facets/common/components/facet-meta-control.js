/**
 * WordPress dependencies.
 */
import { SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { createInterpolateElement, useMemo, WPElement } from '@wordpress/element';
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
	const metaKeys = useSelect((select) => select('elasticpress').getMetaKeys());

	/**
	 * Key options.
	 */
	const options = useMemo(() => {
		return [
			{
				label: __('Select key', 'elasticpress'),
				value: '',
			},
			...metaKeys.map((metaKey) => ({
				label: metaKey,
				value: metaKey,
			})),
		];
	}, [metaKeys]);

	return (
		<SelectControl
			disabled={options.length <= 1}
			help={createInterpolateElement(
				__(
					'This is the list of metadata fields indexed in Elasticsearch. If your desired field does not appear in this list please try to <a>sync your content</a>',
					'elasticpress',
				),
				{ a: <a href={window.epBlocks.syncUrl} /> }, // eslint-disable-line jsx-a11y/anchor-has-content, jsx-a11y/control-has-associated-label
			)}
			label={__('Filter by', 'elasticpress')}
			onChange={onChange}
			options={options}
			value={value}
		/>
	);
};
