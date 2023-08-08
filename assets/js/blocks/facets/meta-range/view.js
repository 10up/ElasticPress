/**
 * WordPress dependencies.
 */
import { render, useLayoutEffect, useMemo, useState, WPElement } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies.
 */
import RangeFilter from '../common/components/range-filter';

/**
 * App component.
 *
 * Accepts input elements as properties. These elements are used to derive the
 * current, minimum, and maximum values, and are updated with new values as the
 * range slider is used.
 *
 * @param {object} props Components props.
 * @param {Element} props.max Maximum value input element.
 * @param {Element} props.min Minimum value input element.
 * @returns {WPElement}
 */
const App = ({ max, min }) => {
	/**
	 * Minimum and maximum possible values.
	 */
	const maxAgg = Math.ceil(max.max);
	const minAgg = Math.floor(min.min);

	/**
	 * Selected minimum and maximum values.
	 */
	const defaultTo = max.value ? parseInt(max.value, 10) : maxAgg;
	const defaultFrom = min.value ? parseInt(min.value, 10) : minAgg;

	/**
	 * Current minimum and maximum values.
	 */
	const [to, setTo] = useState(defaultTo);
	const [from, setFrom] = useState(defaultFrom);

	/**
	 * Prefix and suffix.
	 */
	const prefix = useMemo(() => min.dataset.prefix, [min]);
	const suffix = useMemo(() => min.dataset.suffix, [min]);

	/**
	 * Clear URL.
	 */
	const clearUrl = useMemo(() => (min.value !== '' ? min.form.action : null), [min]);

	/**
	 * Handle change.
	 *
	 * @param {Array} value Value range.
	 * @param {number} value.0 Minimum value.
	 * @param {number} value.1 Mximum value.
	 * @returns {void}
	 */
	const onChange = ([from, to]) => {
		setTo(to);
		setFrom(from);
	};

	/**
	 * Effects.
	 */
	useLayoutEffect(() => {
		max.value = Math.min(maxAgg, to);
		min.value = Math.max(minAgg, from);
	}, [from, to, min, max, minAgg, maxAgg]);

	/**
	 * Render.
	 */
	return (
		<RangeFilter
			clearUrl={clearUrl}
			max={maxAgg}
			min={minAgg}
			prefix={prefix}
			suffix={suffix}
			onChange={onChange}
			value={[from, to]}
		/>
	);
};

/**
 * Initialize.
 *
 * @returns {void}
 */
const init = () => {
	const blocks = document.querySelectorAll('.ep-facet-meta-range');

	blocks.forEach((block) => {
		const [min, max] = block.querySelectorAll('input[type="hidden"]');
		const el = document.createElement('div');

		block.insertAdjacentElement('afterbegin', el);

		render(<App min={min} max={max} />, el);
	});
};

domReady(init);
