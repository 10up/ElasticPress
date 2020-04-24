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
	const [defaultResults, setDefaultResults] = useState( [] );

	const [testLoading, setTestLoading] = useState( false );
	const [testResults, setTestResults] = useState( [] );

	/**
	 * Fires only on componentDidMount with empty []
	 */
	useEffect( () => {
		captureUpdatedtWeightingValues();
	}, [] );


	/**
	 * Get the current values of the weights in the UI
	 */
	const captureUpdatedtWeightingValues = () => {
		const weightForm = document.forms.find( form => 'weight-settings' == form.id );
		console.log( weightForm );
	};


	/**
	 * Handle the form submit, for when the user changes
	 * the search term
	 */
	const handleSubmit = e => {
		e.preventDefault();
		console.log( 'submitted!' );
		searchWithCurrentWeighingSettings();
	};

	/**
	 * Search with the current weighting settings
	 */
	const searchWithCurrentWeighingSettings = async () => {
		try {
			setDefaultLoading( true );
			const results = await apiFetch( {
				path: `/elasticpress/v1/weighting_search?s=${searchTerm}`,
			} );
			setDefaultResults( results );

		} catch ( error ) {
			console.error( 'There was a problem fetching the posts.', error );
		} finally {
			setDefaultLoading( false );
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
					{!defaultLoading && defaultResults && (
						<ul>
							<SearchResults results={defaultResults} />
						</ul>
					)}
					{defaultLoading && (
						<p>Loading...</p>
					)}
				</div>
				<div className="preview-posts__results">
					<h4>Test Results:</h4>
					{/* {defaultResults && (
						<ul>
							{testResults.map( result => <SearchResults result={result} /> )}
						</ul>
					)} */}
				</div>
			</div>
		</div>
	);
};

export default Weighting;
