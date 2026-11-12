/**
 * WordPress dependencies.
 */
import { useState, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useApiSearch } from '../../api-search';
import { facets } from '../config';
import Facet from './facets/facet';
import Header from './layout/header';
import Results from './layout/results';
import Sidebar from './layout/sidebar';
import Sort from './tools/sort';

/**
 * Search dialog.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const { isLoading } = useApiSearch();

	const [isSidebarOpen, setIsSidebarOpen] = useState(false);

	/**
	 * Sidebar toggle click handler.
	 *
	 * @returns {void}
	 */
	const onClickSidebarToggle = () => {
		setIsSidebarOpen(!isSidebarOpen);
	};

	return (
		<div className={`ep-search-page ${isLoading ? 'is-loading' : ''}`}>
			<Header isSidebarOpen={isSidebarOpen} onClickSidebarToggle={onClickSidebarToggle} />

			<div className="ep-search-page__body">
				<Sidebar isOpen={isSidebarOpen}>
					<Sort />
					{facets.map(({ label, name, postTypes, type }, index) => (
						<Facet
							index={index}
							key={name}
							label={label}
							name={name}
							postTypes={postTypes}
							type={type}
						/>
					))}
				</Sidebar>

				<Results />
			</div>
		</div>
	);
};
