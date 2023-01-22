/**
 * WordPress dependencies.
 */
import { Fragment, useRef, useState, WPElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { termCount } from '../../config';
import Checkbox from './checkbox';
import SmallButton from './small-button';

/**
 * Checkbox list component.
 *
 * @typedef {object} Option
 * @property {number} count Number associated with option.
 * @property {string} id Option ID.
 * @property {string} label Option label.
 * @property {number} order Option order.
 * @property {string} parent Parent option value.
 * @property {any} value Option value.
 *
 * @param {object} props Component props.
 * @param {boolean} props.disabled Whether the checkboxes should be disabled.
 * @param {string} props.locale BCP 47 language tag. Used for sorting.
 * @param {boolean} props.label List label.
 * @param {Function} props.onChange Checkbox change event callback function.
 * @param {Option[]} props.options Checkbox options.
 * @param {string} props.selected Selected values.
 * @param {string} props.sortBy How to sort options.
 * @returns {WPElement} A React element.
 */
export default ({ disabled, label, locale, options, onChange, selected, sortBy }) => {
	/**
	 * Outermost list element.
	 */
	const listEl = useRef(null);

	/**
	 * Whether all items are displayed and a setter.
	 */
	const [showAll, setShowAll] = useState(false);

	/**
	 * Reducer to group options by parent.
	 *
	 * @param {object} items         Options grouped by parent.
	 * @param {Option} option        Option details.
	 * @param {string} option.parent Option parent value.
	 * @returns {object} Options grouped by parent.
	 */
	const reduceOptionsByParent = (items, { parent, ...option }) => {
		// eslint-disable-next-line eqeqeq
		if (parent != false) {
			items[parent] = items[parent] || [];
			items[parent].push(option);
		}

		return items;
	};

	/**
	 * Options grouped by parent value.
	 */
	const childOptions = options.reduce(reduceOptionsByParent, {});

	/**
	 * Reducer to group top level options. Top level options are options
	 * with a parent of '0' or without a parent.
	 *
	 * @param {Array}  items         Options without a parent.
	 * @param {Option} option        Option details.
	 * @param {string} option.parent Option parent value.
	 * @returns {object} Options without a parent.
	 */
	const reduceTopLevelOptions = (items, { parent, ...option }) => {
		// eslint-disable-next-line eqeqeq
		if (parent == false || parent === '0') {
			items.push(option);
		}

		return items;
	};

	/**
	 * Top level options.
	 */
	const topLevelOptions = options.reduce(reduceTopLevelOptions, []);

	/**
	 * How many options should be displayed by default.
	 */
	const optionsLimit = options.length > 8 ? 5 : 8;

	/**
	 * How many options have been displayed.
	 *
	 * Incremented each time an item is displayed. Used to limit the number of
	 * items displayed by default until the show more button is pressed.
	 */
	let optionsShown = 0;

	/**
	 * Handle checkbox change event.
	 *
	 * @param {Event} event Change event.
	 */
	const onCheck = (event) => {
		const { checked, value } = event.target;

		let values = checked ? [...selected, value] : selected.filter((v) => v !== value);

		/* Only send selected values that are in the available options. */
		values = values.filter((v) => options.some((o) => o.value === v));

		onChange(values);
	};

	/**
	 * Render an option.
	 *
	 * @param {Option} option Option.
	 * @returns {WPElement} Render function.
	 */
	const displayOption = ({ count, id, label, value }) => {
		const children = childOptions[value];
		/**
		 * Check for term count option.
		 */
		const counter = termCount === '1' ? count : '';

		if (!showAll && optionsShown >= optionsLimit) {
			return <Fragment key={value} />;
		}

		const option = (
			<li className="ep-search-options-list__item" key={value}>
				<Checkbox
					checked={selected.includes(value)}
					count={counter}
					disabled={disabled}
					id={id}
					label={label}
					onChange={onCheck}
					value={value}
				/>

				{children && (showAll || optionsShown < optionsLimit) && (
					<ul className="ep-search-options-list ep-search-options-list__sub-menu">
						{
							/* eslint-disable-next-line no-use-before-define */
							displayOptions(children)
						}
					</ul>
				)}
			</li>
		);

		optionsShown++;

		return option;
	};

	/**
	 * Sort option callback.
	 *
	 * @param {Option} a First option to compare.
	 * @param {Option} b second option to compare.
	 * @returns {number} Comparison number.
	 */
	const sortOptions = (a, b) => {
		let comparison = 0;

		if (sortBy === 'count') {
			comparison = b.count - a.count;
		}

		if (sortBy === 'name' || comparison === 0) {
			comparison = a.label.localeCompare(b.label, locale);
		}

		return comparison;
	};

	/**
	 * Render a list of options.
	 *
	 * @param {Option[]} options Options to display.
	 * @returns {WPElement[]} Array of elements.
	 */
	const displayOptions = (options) => {
		return options.splice(0).sort(sortOptions).map(displayOption);
	};

	/**
	 * Handle clicking the show more/fewer button.
	 *
	 * @returns {void}
	 */
	const onToggleShowAll = () => {
		setShowAll(!showAll);

		listEl.current.focus();
	};

	return (
		<>
			{options.length > 0 && (
				<ul
					aria-label={label}
					className="ep-search-options-list"
					ref={listEl}
					tabIndex="-1"
				>
					{
						/* Display top level options and their children. */
						displayOptions(topLevelOptions)
					}
					{
						/* Display remaining orphaned options. */
						Object.values(childOptions).map(displayOptions)
					}
				</ul>
			)}

			{options.length > optionsLimit && (
				<SmallButton aria-expanded={showAll} disabled={disabled} onClick={onToggleShowAll}>
					{showAll
						? __('Show fewer options', 'elasticpress')
						: sprintf(
								/* translators: %d: Number of additional options available. */
								_n(
									'Show %d more option',
									'Show %d more options',
									options.length - optionsLimit,
									'elasticpress',
								),
								options.length - optionsLimit,
						  )}
				</SmallButton>
			)}
		</>
	);
};
