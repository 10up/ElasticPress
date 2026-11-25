/**
 * WordPress dependencies.
 */
import { Component, FunctionComponent, WPElement } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import SearchTermFacet from '../facets/search-term-facet';
import ActiveConstraints from '../tools/active-constraints';
import ClearConstraints from '../tools/clear-constraints';
import SidebarToggle from '../tools/sidebar-toggle';
import Toolbar from './toolbar';

/**
 * Search page header: the search input and toolbar.
 *
 * @param {object} props Component props.
 * @param {boolean} props.isSidebarOpen Whether the sidebar is open.
 * @param {Function} props.onClickSidebarToggle Sidebar toggle click handler.
 * @returns {WPElement} Component element.
 */
const Header = ({ isSidebarOpen, onClickSidebarToggle }) => {
	return (
		<div className="ep-search-page__header">
			<SearchTermFacet />

			<Toolbar>
				<ActiveConstraints />
				<ClearConstraints />
				<SidebarToggle isOpen={isSidebarOpen} onClick={onClickSidebarToggle} />
			</Toolbar>
		</div>
	);
};

/**
 * Filter the Header component.
 *
 * @filter ep.InstantResults.Header
 * @since 5.4.0
 *
 * @param {Component|FunctionComponent} Header Header component.
 * @returns {Component|FunctionComponent} Header component.
 */
export default applyFilters('ep.InstantResults.Header', Header);
