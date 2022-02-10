// External
import React, { Component } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';
import { __ } from '@wordpress/i18n';
import { pluck, debounce } from '../utils/helpers';

apiFetch.use(apiFetch.createRootURLMiddleware(window.epOrdering.restApiRoot));

export class Pointers extends Component {
	titleInput = null;

	debouncedDefaultResults = debounce(() => {
		this.getDefaultResults();
	}, 200);

	doSearch = debounce(() => {
		const { searchText, searchResults } = this.state;
		const searchTerm = searchText;

		// Set loading state
		searchResults[searchTerm] = false;
		this.setState({ searchResults });

		apiFetch({
			path: `/elasticpress/v1/pointer_search?s=${searchTerm}`,
		}).then((result) => {
			searchResults[searchTerm] = result;

			this.setState({ searchResults });
		});
	}, 200);

	debouncedHandleTitleChange = debounce(() => {
		this.handleTitleChange();
	}, 200);

	/**
	 * Initializes the component with initial state set by WP
	 *
	 * @param {Object} props Component props
	 */
	constructor(props) {
		super(props);

		// We need to know the title of the page and react to changes since this is the query we search for
		this.titleInput = document.getElementById('title');

		this.state = {
			pointers: window.epOrdering.pointers,
			posts: window.epOrdering.posts,
			title: this.titleInput.value,
			defaultResults: {},
			searchText: '',
			searchResults: {},
		};
	}

	componentDidMount() {
		this.titleInput.addEventListener('keyup', this.debouncedHandleTitleChange);

		const { title } = this.state;

		if (title?.length > 0) {
			this.getDefaultResults();
		}
	}

	componentWillUnmount() {
		this.titleInput.removeEventListener('keyup', this.debouncedHandleTitleChange);
	}

	handleTitleChange = () => {
		this.setState({ title: this.titleInput.value });
		this.debouncedDefaultResults();
	};

	getDefaultResults = () => {
		const { title: searchTerm } = this.state;

		apiFetch({
			path: `/elasticpress/v1/pointer_preview?s=${searchTerm}`,
		}).then((result) => {
			const { defaultResults } = this.state;

			defaultResults[searchTerm] = result;

			this.setState({ defaultResults });
		});
	};

	removePointer = (pointer) => {
		let { pointers } = this.state;

		delete pointers[pointers.indexOf(pointer)];
		pointers = pointers.filter((item) => item !== null);

		this.setState({ pointers });
	};

	getMergedPosts = () => {
		let { pointers } = this.state;
		const { title, defaultResults } = this.state;
		let merged = defaultResults[title].slice();

		const setIds = {};
		merged.forEach((item) => {
			setIds[item.ID] = item;
		});

		pointers = pointers.sort((a, b) => {
			return a.order > b.order ? 1 : -1;
		});

		pointers.forEach((pointer) => {
			// Remove the original if a duplicate
			if (setIds[pointer.ID]) {
				delete merged[merged.indexOf(setIds[pointer.ID])];
				merged = merged.filter((item) => item);
			}

			// Insert into proper location
			merged.splice(parseInt(pointer.order, 10) - 1, 0, pointer);
		});

		return merged;
	};

	/**
	 * Gets the next available position for a pointer
	 *
	 * @return {number|false} The available position
	 */
	getNextAvailablePosition = () => {
		const { pointers } = this.state;
		const availablePositions = {};

		for (let i = 1; i <= window.epOrdering.postsPerPage; i++) {
			availablePositions[i] = true;
		}

		pointers.forEach((item) => {
			delete availablePositions[item.order];
		});

		const keys = Object.keys(availablePositions);

		if (keys.length === 0) {
			return false;
		}

		return parseInt(keys[0], 10);
	};

	/**
	 * Adds a new pointer. We place the new pointer at the highest available position
	 *
	 * @param {Object} post Post object
	 */
	addPointer = (post) => {
		const id = post.ID;
		const { posts, pointers } = this.state;

		if (!posts[id]) {
			posts[id] = post;
			this.setState({ posts });
		}

		const position = this.getNextAvailablePosition();

		if (!position) {
			/* eslint-disable no-alert */
			window.alert(
				__('You have added the maximum number of custom results.', 'elasticpress'),
			);
			/* eslint-enable no-alert */
			return;
		}

		pointers.push({
			ID: id,
			order: position,
		});

		this.setState({ pointers });
	};

	/**
	 * Callback when drag/drop is complete.
	 *
	 * Only the pointers are able to be dragged around, so all we need to do is increase any pointer by one that is
	 * either at the current position or greater
	 *
	 * @param {Object} result Dragged object
	 */
	onDragComplete = (result) => {
		// dropped outside the list
		if (!result.destination) {
			return;
		}

		const items = this.getMergedPosts();

		// Offsetting indexes when over posts per page to account for the non-sortable notice
		const ppp = parseInt(window.epOrdering.postsPerPage, 10);
		const startIndex =
			result.source.index >= ppp ? result.source.index - 1 : result.source.index;
		const endIndex =
			result.destination.index > ppp
				? result.destination.index - 1
				: result.destination.index;

		const [removed] = items.splice(startIndex, 1);
		items.splice(endIndex, 0, removed);

		// Now _all_ the items are in order - grab the pointers and set the new positions to state
		const pointers = [];

		items.forEach((item, index) => {
			if (item.order) {
				// Reordering an existing pointer
				pointers.push({
					ID: item.ID,
					order: index + 1,
				});
			} else if (item.ID === result.draggableId) {
				// Adding a default post to the pointers array
				pointers.push({
					ID: item.ID,
					order: index + 1,
				});
			}
		});

		this.setState({ pointers });
	};

	searchResults = (searchResults) => {
		const { searchText } = this.state;

		if (searchText === '') {
			return null;
		}

		if (searchResults === false) {
			return (
				<div className="loading">
					<div className="spinner is-active" />
					Loading...
				</div>
			);
		}

		if (searchResults.length === 0) {
			return <div className="no-results">{__('No results found.', 'elasticpress')}</div>;
		}

		return searchResults.map((result) => {
			return (
				<div className="pointer-result" key={result.ID}>
					<span className="title">{result.post_title}</span>
					<span
						role="button"
						tabIndex="0"
						className="dashicons dashicons-plus add-pointer"
						onClick={(event) => {
							event.preventDefault();
							this.addPointer(result);
						}}
						onKeyDown={(event) => {
							event.preventDefault();
							this.addPointer(result);
						}}
					>
						<span className="screen-reader-text">{__('Add Post', 'elasticpress')}</span>
					</span>
				</div>
			);
		});
	};

	/**
	 * Renders the component
	 *
	 * @return {*} The component
	 */
	render() {
		const {
			posts,
			defaultResults,
			title,
			pointers,
			searchText,
			searchResults: searchResultsFromState,
		} = this.state;

		if (title.length === 0) {
			return (
				<div className="new-post">
					<p>
						{__(
							'Enter your search query above to preview the results.',
							'elasticpress',
						)}
					</p>
				</div>
			);
		}

		if (!defaultResults[title]) {
			return (
				<div className="loading">
					<div className="spinner is-active" />
					<span>{__('Loading Result Preview…', 'elasticpress')}</span>
				</div>
			);
		}

		// We need to reference these by ID later
		const defaultResultsById = {};
		defaultResults[title].forEach((item) => {
			defaultResultsById[item.ID] = item;
		});

		const mergedPosts = this.getMergedPosts();
		const renderedIds = pluck(pointers, 'ID');

		const searchResults = searchResultsFromState[searchText]
			? searchResultsFromState[searchText].filter(
					(item) => renderedIds.indexOf(item.ID) === -1,
			  )
			: false;

		return (
			<div>
				<input type="hidden" name="search-ordering-nonce" value={window.epOrdering.nonce} />
				<input type="hidden" name="ordered_posts" value={JSON.stringify(pointers)} />
				<DragDropContext onDragEnd={this.onDragComplete}>
					<Droppable droppableId="droppable">
						{(provided) => (
							<div
								className="pointers"
								{...provided.droppableProps}
								ref={provided.innerRef}
							>
								{mergedPosts.map((item, index) => {
									const draggableIndex =
										parseInt(window.epOrdering.postsPerPage, 10) <= index
											? index + 1
											: index;

									let { title } = item;
									if (undefined === title) {
										title =
											undefined !== posts[item.ID]
												? posts[item.ID].post_title
												: defaultResultsById[item.ID].post_title;
									}

									// Determine if this result is part of default search results or not
									const isDefaultResult =
										undefined !== defaultResultsById[item.ID];
									const tooltipText =
										isDefaultResult === true
											? __('Return to original position', 'elasticpress')
											: __(
													'Remove custom result from results list',
													'elasticpress',
											  );

									return (
										<React.Fragment key={item.ID}>
											{parseInt(window.epOrdering.postsPerPage, 10) ===
												index && (
												<Draggable
													key="divider"
													draggableId="divider"
													index={index}
													isDragDisabled={false}
												>
													{(component) => (
														<div
															className={`next-page-notice ${index}`}
															ref={component.innerRef}
															{...component.draggableProps}
															{...component.dragHandleProps}
														>
															<span>
																{__(
																	'The following posts have been displaced to the next page of search results.',
																	'elasticpress',
																)}
															</span>
														</div>
													)}
												</Draggable>
											)}

											<Draggable
												key={item.ID}
												draggableId={item.ID}
												index={draggableIndex}
											>
												{(provided2) => (
													<div
														className={`pointer ${draggableIndex}`}
														ref={provided2.innerRef}
														{...provided2.draggableProps}
													>
														{item.order && isDefaultResult === true && (
															<span className="pointer-type">RD</span>
														)}
														{item.order &&
															isDefaultResult === false && (
																<span className="pointer-type">
																	CR
																</span>
															)}
														<strong className="title">{title}</strong>
														<div className="pointer-actions">
															<span
																className="dashicons dashicons-menu handle"
																{...provided2.dragHandleProps}
																title={__(
																	'Drag post up or down to reposition',
																	'elasticpress',
																)}
															/>
															{item.order && (
																<span
																	role="button"
																	tabIndex="0"
																	title={tooltipText}
																	className="dashicons dashicons-undo delete-pointer"
																	onClick={(event) => {
																		event.preventDefault();
																		this.removePointer(item);
																	}}
																	onKeyDown={(event) => {
																		event.preventDefault();
																		this.removePointer(item);
																	}}
																>
																	<span className="screen-reader-text">
																		Remove Post
																	</span>
																</span>
															)}
														</div>
													</div>
												)}
											</Draggable>
										</React.Fragment>
									);
								})}
								{provided.placeholder}
							</div>
						)}
					</Droppable>
				</DragDropContext>

				<div className="legend">
					<div className="legend-item">
						<span className="pointer-type">CR</span>
						<span className="type-description">
							{__('Custom Result (manually added to list)', 'elasticpress')}
						</span>
					</div>
					<div className="legend-item">
						<span className="pointer-type">RD</span>
						<span className="type-description">
							{__(
								'Reordered Default (originally in results, but repositioned)',
								'elasticpress',
							)}
						</span>
					</div>
				</div>

				<div className="pointer-search">
					<h2 className="section-title">{__('Add to results', 'elasticpress')}</h2>

					<div className="search-wrapper">
						<div className="input-wrap">
							<input
								type="text"
								className="widefat search-pointers"
								placeholder="Search for Post"
								value={searchText}
								onChange={(e) => {
									this.setState({ searchText: e.target.value });
									this.doSearch();
								}}
							/>
						</div>

						<div className="pointer-results">{this.searchResults(searchResults)}</div>
					</div>
				</div>
			</div>
		);
	}
}
