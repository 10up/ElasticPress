/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Component, FunctionComponent, WPElement } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import { isNumberedPagination } from '../../config';

/**
 * Search results component.
 *
 * @param {object} props Props.
 * @param {number} props.offset Current items offset.
 * @param {Function} props.onNext Next button handler.
 * @param {Function} props.onPrevious Previous button handler.
 * @param {Function} props.onPage Page button handler.
 * @param {number} props.perPage Items per page.
 * @param {number} props.total Total number of items.
 * @returns {WPElement} Element.
 */
const Pagination = ({ offset, onNext, onPage = {}, onPrevious, perPage, total }) => {
	if (perPage <= 0) {
		return null;
	}

	const totalPages = Math.ceil(total / perPage);
	const isNumbered = isNumberedPagination;

	const createPageItem = (pageNumber) => ({
		key: `page-${pageNumber}`,
		type: 'page',
		value: pageNumber,
	});

	const createEllipsisItem = (position) => ({
		key: `ellipsis-${position}`,
		type: 'ellipsis',
	});

	const getPageRange = (start, end) => {
		const pages = [];

		for (let page = start; page <= end; page++) {
			pages.push(createPageItem(page));
		}

		return pages;
	};

	const getPages = (currentPage) => {
		const maxVisiblePages = 10;

		if (totalPages <= maxVisiblePages) {
			return getPageRange(1, totalPages);
		}

		const windowSize = maxVisiblePages - 2;
		const halfWindow = Math.floor(windowSize / 2);
		const maxStart = totalPages - windowSize;
		const start = Math.max(2, Math.min(currentPage - halfWindow, maxStart));
		const end = Math.min(totalPages - 1, start + windowSize - 1);

		const pages = [createPageItem(1)];

		if (start > 2) {
			pages.push(createEllipsisItem('start'));
		}

		pages.push(...getPageRange(start, end));

		if (end < totalPages - 1) {
			pages.push(createEllipsisItem('end'));
		}

		pages.push(createPageItem(totalPages));

		return pages;
	};

	const renderCount = (currentPage) => (
		<div className="ep-search-pagination__count" role="status">
			{total > 0 &&
				sprintf(
					/* translators: %1$d: current page. %2$d: total pages. */
					__('Page %1$d of %2$d', 'elasticpress'),
					currentPage,
					totalPages,
				)}
		</div>
	);

	const renderButtonNavigation = () => {
		const currentPage = (offset + perPage) / perPage;
		const nextIsAvailable = total > offset + perPage;
		const previousIsAvailable = offset > 0;

		return (
			<>
				<div className="ep-search-pagination__previous">
					<button
						className="ep-search-pagination-button ep-search-pagination-button--previous"
						disabled={!previousIsAvailable}
						onClick={onPrevious}
						type="button"
					>
						{__('Previous', 'elasticpress')}
					</button>
				</div>

				{renderCount(currentPage)}

				<div className="ep-search-pagination__next">
					<button
						className="ep-search-pagination-button ep-search-pagination-button--next"
						disabled={!nextIsAvailable}
						onClick={onNext}
						type="button"
					>
						{__('Next', 'elasticpress')}
					</button>
				</div>
			</>
		);
	};

	const renderNumberedNavigation = () => {
		if (totalPages <= 0) {
			return null;
		}

		const rawCurrentPage = Math.floor(offset / perPage) + 1;
		const currentPage = Math.min(Math.max(rawCurrentPage, 1), totalPages);
		const pages = getPages(currentPage);
		const onPageHandler = typeof onPage === 'function' ? onPage : undefined;

		return (
			<>
				{renderCount(currentPage)}

				<ul className="ep-search-pagination__list">
					{pages.map((page) => {
						if (page.type === 'ellipsis') {
							return (
								<li
									key={page.key}
									className="ep-search-pagination__item ep-search-pagination__ellipsis"
								>
									<span aria-hidden="true">...</span>
								</li>
							);
						}

						const isCurrent = page.value === currentPage;

						return (
							<li key={page.key} className="ep-search-pagination__item">
								<button
									className={`ep-search-pagination-button${isCurrent ? ' is-current' : ''}`}
									type="button"
									onClick={() => {
										if (!isCurrent) {
											onPageHandler?.(page.value);
										}
									}}
									aria-current={isCurrent ? 'page' : undefined}
									aria-label={sprintf(__('Page %d', 'elasticpress'), page.value)}
								>
									{page.value}
								</button>
							</li>
						);
					})}
				</ul>
			</>
		);
	};

	return (
		<nav className={`ep-search-pagination ${isNumbered ? 'is-numbered' : ''}`}>
			{isNumbered ? renderNumberedNavigation() : renderButtonNavigation()}
		</nav>
	);
};

/**
 * Filter the Pagination component.
 *
 * @filter ep.InstantResults.Pagination
 * @since 5.3.3
 *
 * @param {Component|FunctionComponent} Pagination Pagination component.
 * @returns {Component|FunctionComponent} Pagination component.
 */
export default applyFilters('ep.InstantResults.Pagination', Pagination);
