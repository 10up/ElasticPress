/**
 * WordPress dependencies.
 */
import { RawHTML, WPElement } from '@wordpress/element';

/**
 * Field value component.
 *
 * @param {object} props Component props.
 * @param {any} props.value Value to render.
 * @returns {WPElement} Value component.
 */
export default ({ value }) => {
	if (typeof value === 'object') {
		const json = JSON.stringify(value, null, 2);

		return <pre>{json}</pre>;
	}

	if (typeof value === 'string') {
		if (value.indexOf('{') === 0) {
			try {
				const data = JSON.parse(value);
				const json = JSON.stringify(data, null, 2);

				return <pre>{json}</pre>;
			} catch (e) {
				return <pre>{value}</pre>;
			}
		}

		return <RawHTML>{value}</RawHTML>;
	}

	return value.toString();
};
