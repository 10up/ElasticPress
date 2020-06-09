import React from 'react';

/**
 * Search results component, showing a list
 * of search results.
 *
 * @param {results} param0
 */
const SearchResults = ( { results } ) => {
	return (
		<ul>
			{results.map( ( result ) => {
				return (
					<li>
						<h5>{result.post_title}</h5>
						{result.post_content && (
							<p
								dangerouslySetInnerHTML={{
									__html: `${result.post_content.substring( 0, 100 )}...`,
								}}
							></p>
						)}
					</li>
				);
			} )}
		</ul>
	);
};

export default SearchResults;
