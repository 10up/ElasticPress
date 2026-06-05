/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { Placeholder } from '@wordpress/components';
import { createRoot, render, useEffect, useState, WPElement } from '@wordpress/element';
import Skeleton from 'react-loading-skeleton';
import 'react-loading-skeleton/dist/skeleton.css';

const { restApiEndpoint, searchQuery } = window.epAISearchSummary;

/**
 * App component
 *
 * @returns {WPElement} App component.
 */
const App = () => {
	const [className, setClassName] = useState('');
	const [message, setMessage] = useState('');
	const [isLoading, setIsLoading] = useState(true);

	useEffect(() => {
		apiFetch({
			path: `${restApiEndpoint}?search_query=${searchQuery}`,
		})
			.then((response) => {
				setClassName(response.class);
				setMessage(response.html);
			})
			.finally(() => {
				setIsLoading(false);
			});
	}, []);

	return isLoading ? (
		<Placeholder>
			<Skeleton count={5} />
		</Placeholder>
	) : (
		<div
			className={`ep-ai-search-summary-generated ${className}`}
			// eslint-disable-next-line react/no-danger
			dangerouslySetInnerHTML={{ __html: message }}
		/>
	);
};

domReady(() => {
	const blocks = document.querySelectorAll('.ep-ai-search-summary-response');

	blocks.forEach((block) => {
		if (typeof createRoot === 'function') {
			const root = createRoot(block);

			root.render(<App />);
		} else {
			render(<App />, block);
		}
	});
});
