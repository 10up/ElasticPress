/**
 * Simple throttling function for waiting a set amount of time after the last keypress
 * So we don't overload the server with too many requests at once
 *
 * @param fn
 * @param delay
 * @returns {Function}
 */
export const debounce = ( fn, delay ) => {
	let timer = null;

	// don't use a fat arrow in order to preserve the proper context
	return function() {
		const context = this,
			args = arguments;

		window.clearTimeout( timer );

		timer = window.setTimeout( () => {
			fn.apply( context, args );
		}, delay );
	};
};


/**
 * Helper function to escape input to be treated as a literal string with a RegEx
 *
 * @param string
 * @returns string
 */
export const escapeRegExp = string => string.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );


/**
 * Helper function to escape input to be treated as a literal string with a RegEx
 *
 * @param string - string
 * @param term - string
 * @param replacement - string
 * @returns string
 */
export const replaceGlobally = ( string, term, replacement ) => {
	return string.replace( new RegExp( escapeRegExp( term ), 'g' ), replacement );
};


/**
 * Escapes double quotes for specific data-attr
 *
 * @param str
 * @returns string The escaped string
 */
export const escapeDoubleQuotes = str => str.replace( /\\([\s\S])|(")/g, '&quot;' );


/**
 * Finds parent node with the provided class param
 *
 * @param {*} el
 * @param {*} cls
 * @returns element
 */
export const findAncestor = ( el, cls ) => {
	while ( ( el = el.parentElement ) && !el.classList.contains( cls ) );
	return el;
};


/**
 * Array pluck
 *
 * @param array
 * @param key
 *
 * @returns array
 */
export const pluck = ( array, key ) => {
	return array.map( o => o[ key ] );
};
