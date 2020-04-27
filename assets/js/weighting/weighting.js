import React, { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import SearchResults from './search-results';

apiFetch.use( apiFetch.createRootURLMiddleware( window.epWeighting.restApiRoot ) );

/**
 * Main weighting React component
 */
const Weighting = () => {
	const [searchTerm, setSearchTerm] = useState( '' );

	const [defaultLoading, setDefaultLoading] = useState( false );
	const [resultsFromDefaultValues, setResultsFromDefaultValues] = useState( [] );

	const [defaultWeightSettings, setDefaultWeightSettings] = useState( {} );

	const [testLoading, setTestLoading] = useState( false );
	const [resultsFromTestValues, setResultsFromTestValues] = useState( [] );

	/**
	 * Fires only on componentDidMount with empty []
	 */
	useEffect( () => {
		// captureUpdatedtWeightingValues();
		const defaultSettings = window.epWeighting.postTypes;
		setDefaultWeightSettings( defaultSettings );

		captureUpdatedtWeightingValues();
	}, [] );


	/**
	 * Get the current values of the weights in the UI
	 */
	const captureUpdatedtWeightingValues = () => {
		const weightForm = document.forms;
		const form = Array.from( weightForm ).find( form => 'weight-settings' == form.id ).elements;
		// console.log( form );

		const values = [].map.call( form, el => {
			return {
				name: el.name,
				value: el.value
			};
		} )
			.filter( value => value.name.startsWith( 'weighting' ) && 'undefined' !== name.value );

		console.log( values );

		// return formattedValues;
	};



	/**
	 * Handle the form submit, for when the user changes
	 * the search term
	 */
	const handleSubmit = e => {
		e.preventDefault();
		console.log( 'submitted!' );
		// const defaultSearch = searchWithCurrentWeightingSettings();
		const updatedSearch = searchWithUpdatedWeightingSettings();

		Promise.all( [
			// defaultSearch,
			updatedSearch
		] );
	};

	/**
	 * Search with the current weighting settings
	 */
	const searchWithCurrentWeightingSettings = async () => {
		try {
			setDefaultLoading( true );
			const results = await apiFetch( {
				path: `/elasticpress/v1/weighting_search?s=${searchTerm}`
			} );
			setResultsFromDefaultValues( results );

		} catch ( error ) {
			console.error( 'There was a problem fetching the results.', error );
		} finally {
			setDefaultLoading( false );
		}
	};


	/**
	 * Search with the current weighting settings
	 */
	const searchWithUpdatedWeightingSettings = async () => {
		// first thing's first
		const values = captureUpdatedtWeightingValues();

		// then...
		try {
			setTestLoading( true );
			const results = await apiFetch( {
				path: `/elasticpress/v1/weighting_test?s=${searchTerm}`,
				method: 'POST',
				data: {
					settings: JSON.stringify( values )
				}
			} );
			console.log( results );
			setResultsFromTestValues( results );

		} catch ( error ) {
			console.error( 'There was a problem fetching the test results.', error );
		} finally {
			setTestLoading( false );
		}
	};

	/**
	 * Search with the test weighting settings
	 */
	// const searchWithTestWeighingSettings = () => {

	// };

	return (
		<div>
			<form onSubmit={handleSubmit}>
				<label class="search-label">
					<span>Searh Term</span>
					<input
						type="text"
						placeholder="Search term..."
						value={searchTerm}
						onChange={e => setSearchTerm( e.target.value )}
					/>
				</label>
				<input type="submit" value="Search" className="button button-primary" />
			</form>
			<div className="preview-posts">
				<div className="preview-posts__results">
					<h4>Current Results:</h4>
					{!defaultLoading && resultsFromDefaultValues && (
						<ul>
							<SearchResults results={resultsFromDefaultValues} />
						</ul>
					)}
					{defaultLoading && (
						<p>Loading...</p>
					)}
				</div>
				<div className="preview-posts__results">
					<h4>Test Results:</h4>
					{!testLoading && resultsFromTestValues && (
						<ul>
							<SearchResults results={resultsFromTestValues} />
						</ul>
					)}
					{testLoading && (
						<p>Loading...</p>
					)}
				</div>
			</div>
		</div>
	);
};

export default Weighting;
