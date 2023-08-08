/**
 * WordPress dependencies.
 */
import { SelectControl } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __, _x } from '@wordpress/i18n';

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
			label: _x(
				'A → Z',
				'label for ordering posts by title in ascending order',
				'elasticpress',
			),
			value: 'name/asc',
		},
		{
			label: _x(
				'Z → A',
				'label for ordering posts by title in descending order',
				'elasticpress',
			),
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
