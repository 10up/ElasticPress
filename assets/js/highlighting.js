/* eslint-disable camelcase */
// import jQuery from 'jquery';
import { epas } from 'window';

/**
 * init
 *
 * assingns the selected color as a css variable
 */
function init() {
	epas.highlightColor = '#FF0';

	document.documentElement.style
		.setProperty( '--highlight-color', epas.highlightColor );
}

// init();
