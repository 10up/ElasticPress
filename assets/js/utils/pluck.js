/**
 * Array pluck
 *
 * @param {Array} array - source array
 * @param {string} key - the item in the source array to retreive
 *
 * @returns {Array} new array containing the data specified in the key
 */
function pluck( array, key ) {
	return array.map( ( o ) => o[key] );
}

export default { pluck };
