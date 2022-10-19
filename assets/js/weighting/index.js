/**
 * WordPress dependencies.
 */
import { render } from '@wordpress/element';

/**
 * Internal Dependencies.
 */
import Weighting from './components/weighting';

/**
 * Initialize.
 *
 * @returns {void}
 */
const init = () => {
	const { weightableFields, weightingConfiguration } = window.epWeighting;

	render(
		<Weighting
			weightingConfiguration={weightingConfiguration}
			weightableFields={weightableFields}
		/>,
		document.getElementById('ep-weighting-screen'),
	);
};

init();
