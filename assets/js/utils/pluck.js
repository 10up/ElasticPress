/**
 * Array pluck
 *
 * @param array
 * @param key
 *
 * @returns array
 */
export function pluck( array, key ) {
	return array.map( o => o[ key ] );
}
