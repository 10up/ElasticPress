/**
 * Simple throttling function for waiting a set amount of time after the last keypress
 * So we don't overload the server with too many requests at once
 *
 * @param func
 * @param wait
 * @returns {Function}
 */
export function debounce( func, wait ) {
	let timeout;

	return function() {
		const context = this, args = arguments;

		/**
		 *
		 */
		const later = function() {
			timeout = null;
			func.apply( context, args );
		};

		clearTimeout( timeout );

		timeout = setTimeout( later, wait );
	};
}
