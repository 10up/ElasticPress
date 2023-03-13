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
	const { apiUrl, metaMode, syncUrl, weightableFields, weightingConfiguration } =
		window.epWeighting;

	render(
		<Weighting
			apiUrl={apiUrl}
			metaMode={metaMode}
			syncUrl={syncUrl}
			weightingConfiguration={weightingConfiguration}
			weightableFields={weightableFields}
		/>,
		document.getElementById('ep-weighting-screen'),
	);
};

init();
