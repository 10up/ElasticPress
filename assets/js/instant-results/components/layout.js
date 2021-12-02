/**
 * WordPress dependencies.
 */
import { useContext, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { facets } from '../config';
import Context from '../context';
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
 * @return {WPElement} Component element.
 */
export default () => {
	const {
		state: { isSidebarOpen },
	} = useContext(Context);

	return (
		<div className="ep-search-page">
			<div className="ep-search-page__header">
				<SearchTermFacet />

				<Toolbar>
					<ActiveConstraints />
					<ClearConstraints />
					<SidebarToggle />
				</Toolbar>
			</div>

			<div className="ep-search-page__body">
				<Sidebar isOpen={isSidebarOpen}>
					<Sort />
					{facets.map(({ name, label, type }, index) => (
						<Facet index={index} key={name} label={label} name={name} type={type} />
					))}
				</Sidebar>

				<Results />
			</div>
		</div>
	);
};
