/**
 * WordPress dependencies.
 */
import { FormTokenField } from '@wordpress/components';
import { useMemo, useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { facets } from '../config';

/**
 * Facet selector component.
 *
 * @param {object} props              Props.
 * @param {string} props.defaultValue Default value.
 * @returns {WPElement} Element.
 */
export default ({ defaultValue, ...props }) => {
	const defaultValues = defaultValue.split(',');
	const [selectedFacets, setSelectedFacets] = useState(defaultValues);

	/**
	 * Get the label for a facet from the facet key.
	 *
	 * @param {string} key Facet key.
	 * @returns {string} Facet label.
	 */
	const getLabelFromKey = (key) => {
		return facets[key]?.label;
	};

	/**
	 * Get the key for a facet from the facet label.
	 *
	 * @param {string} label Facet label.
	 * @returns {string} Facet key.
	 */
	const getKeyFromLabel = (label) => {
		return Object.keys(facets).find((key) => {
			return label === facets[key].label;
		});
	};

	/**
	 * Suggestions for the token field.
	 */
	const suggestions = useMemo(() => Object.keys(facets).map(getLabelFromKey).filter(Boolean), []);

	/**
	 * Values for the token field.
	 */
	const value = useMemo(
		() => selectedFacets.map(getLabelFromKey).filter(Boolean),
		[selectedFacets],
	);

	/**
	 * Handle change to token field.
	 *
	 * @param {Array} tokens Selected tokens.
	 */
	const onChange = (tokens) => {
		setSelectedFacets(tokens.map(getKeyFromLabel).filter(Boolean));
	};

	return (
		<>
			<FormTokenField
				__experimentalExpandOnFocus
				__experimentalShowHowTo={false}
				label={__('Select filters', 'elasticpress')}
				onChange={onChange}
				suggestions={suggestions}
				value={value}
			/>
			<input type="hidden" value={selectedFacets.join(',')} {...props} />
		</>
	);
};
