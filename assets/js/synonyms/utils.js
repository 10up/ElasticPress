import { v4 as uuidv4 } from 'uuid';

/**
 * Generate universally unique identifier.
 */
const uuid = () => {
	return uuidv4();
};

/**
 * Map entry
 * @param {Array} synonyms
 */
const mapEntry = ( synonyms = [], id = '' ) => {
	return {
		id: id.length ? id : uuidv4(),
		synonyms
	};
};

/**
 * Reduce state to Solr spec.
 * @param {Object} state
 */
const reduceStateToSolr = ( { sets, alternatives } ) => {
	const synonyms = [];

	// Handle sets.
	synonyms.push( '# Defined sets ( equivalent synonyms).' );
	synonyms.push( ...sets.map( ( {synonyms} ) => synonyms.map( ( { value } ) => value ).join( ', ' ) ) );

	// Handle alternatives.
	synonyms.push( '\r' );
	synonyms.push( '# Defined alternatives (explicit mappings).' );
	synonyms.push(
		...alternatives.map( alternative => (
			alternative.synonyms.find( item => item.primary && item.value.length )
				? alternative.synonyms.find( item => item.primary ).value
					.concat( ' => ' )
					.concat(
						alternative.synonyms
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
								mapEntry( [
									formatToken( parts[0].trim(), true ),
									...parts[1].split( ',' ).map( token => formatToken( token.trim() ) )
								] )
							]
						} );
					}

					return( {
						...newState,
						sets: [
							...newState.sets,
							mapEntry( [
								...line.split( ',' ).map( token => formatToken( token.trim() ) )
							] )
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
	uuid,
	mapEntry,
};
