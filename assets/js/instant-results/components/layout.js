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
import SearchTermFacet from './facets/search-term-facet';
import Results from './layout/results';
import Sidebar from './layout/sidebar';
import Toolbar from './layout/toolbar';
import ActiveConstraints from './tools/active-constraints';
import ClearConstraints from './tools/clear-constraints';
import SidebarToggle from './tools/sidebar-toggle';
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
			<div className="ep-search-page__header">
				<SearchTermFacet />

				<Toolbar>
					<ActiveConstraints />
					<ClearConstraints />
					<SidebarToggle isOpen={isSidebarOpen} onClick={onClickSidebarToggle} />
				</Toolbar>
			</div>

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
