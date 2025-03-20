/**
 * WordPress dependencies.
 */
import { useLayoutEffect, useState, WPElement } from '@wordpress/element';
import { _x, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useApiSearch } from '../../../api-search';
import { currencyCode } from '../../config';
import { formatPrice } from '../../utilities';
import Panel from '../common/panel';
import RangeSlider from '../common/range-slider';
import { ActiveConstraint } from '../tools/active-constraints';

/**
 * Price range facet.
 *
 * @param {object}  props               Props.
 * @param {boolean} props.defaultIsOpen Whether the panel is open by default.
 * @param {string}  props.label         Facet label.
 * @returns {WPElement} Component element.
 */
export default ({ defaultIsOpen, label }) => {
	const {
		aggregations: {
			price_range: {
				max_price: { value: maxAgg = null } = {},
				min_price: { value: minAgg = null } = {},
			} = {},
		},
		args: { max_price: maxArg = null, min_price: minArg = null },
		isLoading,
		search,
	} = useApiSearch();

	/**
	 * Minimum and maximum possible values.
	 */
	const max = Math.ceil(maxAgg);
	const min = Math.floor(minAgg);

	/**
	 * Current minimum and maximum values.
	 */
	const [currentMaxValue, setCurrentMaxValue] = useState(0);
	const [currentMinValue, setCurrentMinValue] = useState(0);

	/**
	 * Current minimum and maximum prices, formatted.
	 */
	const currentMaxPrice = formatPrice(currentMaxValue, {
		maximumFractionDigits: 0,
		currency: currencyCode,
	});

	const currentMinPrice = formatPrice(currentMinValue, {
		maximumFractionDigits: 0,
		currency: currencyCode,
	});

	/**
	 * Applied minimum and maximum values.
	 */
	const maxValue = maxArg || max;
	const minValue = minArg || min;

	/**
	 * Applied minimum and maximum prices, formatted.
	 */
	const maxPrice = formatPrice(maxValue, { maximumFractionDigits: 0, currency: currencyCode });
	const minPrice = formatPrice(minValue, { maximumFractionDigits: 0, currency: currencyCode });

	/**
	 * Handle completed slider change.
	 *
	 * @param {number[]} values Lowest and highest values.
	 */
	const onAfterChange = (values) => {
		const [min_price, max_price] = values;

		search({ min_price, max_price });
	};

	/**
	 * Handle slider changes as they're made.
	 *
	 * @param {number[]} values Lowest and highest values.
	 */
	const onChange = ([min, max]) => {
		setCurrentMinValue(min);
		setCurrentMaxValue(max);
	};

	/**
	 * Handle clearing the filter.
	 */
	const onClear = () => {
		search({ max_price: null, min_price: null });
	};

	/**
	 * Effects.
	 */
	useLayoutEffect(() => {
		const currentMaxValue = Math.min(max, maxValue);
		const currentMinValue = Math.max(min, minValue);

		setCurrentMaxValue(currentMaxValue);
		setCurrentMinValue(currentMinValue);
	}, [min, max, minValue, maxValue]);

	return (
		maxAgg !== null &&
		minAgg !== null && (
			<Panel defaultIsOpen={defaultIsOpen} label={label}>
				{(isOpen) => (
					<>
						<div className="ep-search-price-facet">
							<div className="ep-search-price-facet__slider">
								{isOpen && (
									<RangeSlider
										disabled={isLoading}
										max={max}
										min={min}
										onAfterChange={onAfterChange}
										onChange={onChange}
										value={[currentMinValue, currentMaxValue]}
									/>
								)}
							</div>

							<div className="ep-search-price-facet__values">
								{currentMinPrice} — {currentMaxPrice}
							</div>
						</div>

						{maxArg !== null && minArg !== null && (
							<ActiveConstraint
								label={sprintf(
									/* translators: %1$s: Minimum price. %2$s: Maximum price. */
									_x('%1$s — %2$s', 'Price range', 'elasticpress'),
									minPrice,
									maxPrice,
								)}
								onClick={onClear}
							/>
						)}
					</>
				)}
			</Panel>
		)
	);
};
