/**
 * WordPress dependencies.
 */
import { SelectControl } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Facet sorting control component.
 *
 * @param {object} props Component props.
 * @param {Function} props.onChange Change handler.
 * @param {string} props.orderby Order by value.
 * @param {string} props.order Order value.
 * @returns {WPElement}
 */
export default ({ onChange, orderby, order }) => {
	const options = [
		{
			label: __('Highest to lowest count'),
			value: 'count/desc',
		},
		{
			label: __('Lowest to highest count'),
			value: 'count/asc',
		},
		{
			/* translators: label for ordering posts by title in ascending order */
			label: __('A → Z', 'elasticpress'),
			value: 'name/asc',
		},
		{
			/* translators: label for ordering posts by title in descending order */
			label: __('Z → A', 'elasticpress'),
			value: 'name/desc',
		},
	];

	return (
		<SelectControl
			label={__('Order by', 'elasticpress')}
			onChange={(value) => {
				const [orderby, order] = value.split('/');

				onChange({ order, orderby });
			}}
			options={options}
			value={`${orderby}/${order}`}
		/>
	);
};
