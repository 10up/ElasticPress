/**
 * Reduce state to Solr spec.
 * @param {Object} state
 */
const reduceStateToSolr = ( { sets, alternatives } ) => {
	const synonyms = [];

	// Handle sets.
	synonyms.push( '# Defined sets ( equivalent synonyms).' );
	synonyms.push( ...sets.map( set => set.map( ( { value } ) => value ).join( ', ' ) ) );

	// Handle alternatives.
	synonyms.push( '\r' );
	synonyms.push( '# Defined alternatives (explicit mappings).' );
	synonyms.push(
		...alternatives.map( alternative => (
			alternative.find( item => item.primary && item.value.length )
				? alternative.find( item => item.primary ).value
					.concat( ' => ' )
					.concat(
						alternative
							.filter( i => ! i.primary )
							.map( ( { value } ) => value ).
							join( ', ' )
					)
				: false
		) )
	);

	return synonyms.filter( Boolean ).join( '\n' );
};

/**
 * Reduce Solr text file to State.
 *
 * @param {String} solr
 */
const reduceSolrToState = ( solr, currentState ) => {
	/**
	 * Format token.
	 * @param {String} value The value.
	 * @param {Boolean} primary Whether it's a primary.
	 */
	const formatToken = ( value, primary = false ) => {
		return {
			label: value,
			value,
			primary
		};
	};

	return {
		...currentState,
		...solr
			.split( /\r?\n/ )
			.reduce(
				( newState, line ) => {
					if ( 0 === line.indexOf( '#' ) || ! line.trim().length ) {
						return newState;
					}

					if ( -1 !== line.indexOf( '=>' ) ) {
						const parts = line.split( '=>' );
						return( {
							...newState,
							alternatives: [
								...newState.alternatives,
								[
									formatToken( parts[0].trim(), true ),
									...parts[1].split( ',' ).map( token => formatToken( token.trim() ) )
								]
							]
						} );
					}

					return( {
						...newState,
						sets: [
							...newState.sets,
							[
								...line.split( ',' ).map( token => formatToken( token.trim() ) )
							]
						]
					} );
				},
				{ alternatives: [], sets: [] }
			)
	};
};

export {
	reduceStateToSolr,
	reduceSolrToState,
};
