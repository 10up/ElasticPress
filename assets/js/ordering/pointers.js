/**
 * External dependencies.
 */
import { DragDropContext, Droppable, Draggable } from '@hello-pangea/dnd';

/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect, useMemo, useCallback, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { pluck, debounce } from '../utils/helpers';

apiFetch.use(apiFetch.createRootURLMiddleware(window.epOrdering.restApiRoot));

export const Pointers = () => {
	const [pointers, setPointers] = useState(window.epOrdering.pointers);
	const [posts, setPosts] = useState(window.epOrdering.posts);
	const [title, setTitle] = useState(document.getElementById('title')?.value || '');
	const [defaultResults, setDefaultResults] = useState({});
	const [searchText, setSearchText] = useState('');
	const [searchResultsData, setSearchResultsData] = useState({});
	const [removedPointers, setRemovedPointers] = useState([]);

	/**
	 * Updates the publish button disabled state based on loading status.
	 */
	const updatePublishButtonState = useCallback(() => {
		const publishButton = document.getElementById('publish');
		if (!publishButton) {
			return;
		}
		const isLoading = title.length === 0 || !defaultResults[title];
		publishButton.disabled = isLoading;
	}, [title, defaultResults]);

	useEffect(() => {
		updatePublishButtonState();
	}, [updatePublishButtonState]);

	const getDefaultResults = useCallback(
		(searchTerm) => {
			apiFetch({
				path: `/elasticpress/v1/pointer_preview?s=${searchTerm}`,
			}).then((result) => {
				setDefaultResults((prevDefaultResults) => ({
					...prevDefaultResults,
					[searchTerm]: result,
				}));
			});
		},
		[], // No dependencies needed as we use functional state update
	);

	const debouncedDefaultResults = useMemo(
		() =>
			debounce((searchTerm) => {
				getDefaultResults(searchTerm);
			}, 200),
		[getDefaultResults],
	);

	const handleTitleChange = useCallback(() => {
		const titleInput = document.getElementById('title');
		if (!titleInput) {
			return;
		}
		const newTitle = titleInput.value;
		setTitle(newTitle);
		debouncedDefaultResults(newTitle);
	}, [debouncedDefaultResults]);

	const debouncedHandleTitleChange = useMemo(
		() =>
			debounce(() => {
				handleTitleChange();
			}, 200),
		[handleTitleChange],
	);

	useEffect(() => {
		const titleInput = document.getElementById('title');
		if (titleInput) {
			titleInput.addEventListener('keyup', debouncedHandleTitleChange);
		}

		if (title?.length > 0) {
			getDefaultResults(title);
		}

		updatePublishButtonState();

		return () => {
			if (titleInput) {
				titleInput.removeEventListener('keyup', debouncedHandleTitleChange);
			}
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []); // We only want this to run on mount

	const doSearch = useMemo(
		() =>
			debounce((searchTerm) => {
				// Set loading state
				setSearchResultsData((prev) => ({
					...prev,
					[searchTerm]: false,
				}));

				apiFetch({
					path: `/elasticpress/v1/pointer_search?s=${searchTerm}`,
				}).then((result) => {
					setSearchResultsData((prev) => ({
						...prev,
						[searchTerm]: result,
					}));
				});
			}, 200),
		[],
	);

	const removePointer = (pointer) => {
		const newRemovedPointers = [...removedPointers];
		newRemovedPointers.push(pointer.ID);
		setRemovedPointers(newRemovedPointers);

		const pointerIndex = pointers.indexOf(pointer);
		if (pointerIndex > -1) {
			const newPointers = [...pointers];
			delete newPointers[pointerIndex];
			setPointers(newPointers.filter((item) => item !== null));
		}
	};

	const getMergedPosts = () => {
		if (!defaultResults[title]) {
			return [];
		}

		let merged = defaultResults[title].slice();

		const sortedPointers = [...pointers].sort((a, b) => {
			return a.order > b.order ? 1 : -1;
		});
		const pointersIds = pluck(sortedPointers, 'ID');

		// Remove all custom pointers from the default results
		merged = merged.filter((item) => pointersIds.indexOf(item.ID) === -1);

		// Insert pointers into their proper location
		sortedPointers.forEach((pointer) => {
			merged.splice(parseInt(pointer.order, 10) - 1, 0, pointer);
		});

		return merged;
	};

	/**
	 * Gets the next available position for a pointer
	 *
	 * @returns {number|false} The available position
	 */
	const getNextAvailablePosition = () => {
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
	 * @param {object} post Post object
	 */
	const addPointer = (post) => {
		const id = post.ID;

		if (!posts[id]) {
			setPosts((prevPosts) => ({
				...prevPosts,
				[id]: post,
			}));
		}

		const position = getNextAvailablePosition();

		if (!position) {
			/* eslint-disable no-alert */
			window.alert(
				__('You have added the maximum number of custom results.', 'elasticpress'),
			);
			/* eslint-enable no-alert */
			return;
		}

		const newPointers = [...pointers];
		newPointers.push({
			ID: id,
			order: position,
			type: 'custom-result',
		});

		setPointers(newPointers);
	};

	/**
	 * Callback when drag/drop is complete.
	 *
	 * Only the pointers are able to be dragged around, so all we need to do is increase any pointer by one that is
	 * either at the current position or greater
	 *
	 * @param {object} result Dragged object
	 */
	const onDragComplete = (result) => {
		// dropped outside the list
		if (!result.destination) {
			return;
		}

		const items = getMergedPosts();

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
		const newPointers = [];

		items.forEach((item, index) => {
			// Reordering an existing pointer or adding a default post to the newPointers array
			if (item.order || Number(item.ID) === Number(result.draggableId)) {
				newPointers.push({
					ID: item.ID,
					order: index + 1,
					type: item?.type || 'reordered',
				});
			}
		});

		setPointers(newPointers);
	};

	const renderSearchResults = (results) => {
		if (searchText === '') {
			return null;
		}

		// Check explicitly for false (loading state)
		if (results === false) {
			return (
				<div className="loading">
					<div className="spinner is-active" />
					Loading...
				</div>
			);
		}

		if (!results || results.length === 0) {
			return <div className="no-results">{__('No results found.', 'elasticpress')}</div>;
		}

		return results.map((result) => {
			return (
				<div className="pointer-result" key={result.ID}>
					<span className="title">{result.post_title}</span>
					<span
						role="button"
						tabIndex="0"
						className="dashicons dashicons-plus add-pointer"
						onClick={(event) => {
							event.preventDefault();
							addPointer(result);
						}}
						onKeyDown={(event) => {
							event.preventDefault();
							addPointer(result);
						}}
					>
						<span className="screen-reader-text">{__('Add Post', 'elasticpress')}</span>
					</span>
				</div>
			);
		});
	};

	if (title.length === 0) {
		return (
			<div className="new-post">
				<p>{__('Enter your search query above to preview the results.', 'elasticpress')}</p>
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

	const mergedPosts = getMergedPosts();
	const renderedIds = pluck(pointers, 'ID');

	const searchResults = searchResultsData[searchText]
		? searchResultsData[searchText].filter((item) => renderedIds.indexOf(item.ID) === -1)
		: false;

	return (
		<div>
			<input type="hidden" name="search-ordering-nonce" value={window.epOrdering.nonce} />
			<input type="hidden" name="ordered_posts" value={JSON.stringify(pointers)} />
			<DragDropContext onDragEnd={onDragComplete}>
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

								const isRemoved = removedPointers.includes(item.ID);

								let { title: itemTitle } = item;
								if (undefined === itemTitle) {
									itemTitle =
										undefined !== posts[item.ID]
											? posts[item.ID].post_title
											: defaultResultsById[item.ID].post_title;
								}

								// Determine if this result is part of default search results or not
								const itemType = item?.type || 'reordered';
								const tooltipText =
									itemType === 'reordered'
										? __('Return to original position', 'elasticpress')
										: __(
												'Remove custom result from results list',
												'elasticpress',
											);

								return (
									<Fragment key={item.ID}>
										{parseInt(window.epOrdering.postsPerPage, 10) === index && (
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
											draggableId={String(item.ID)}
											index={draggableIndex}
										>
											{(provided2) => (
												<div
													className={`pointer ${draggableIndex} ${
														isRemoved ? 'removed' : ''
													}`}
													ref={provided2.innerRef}
													{...provided2.draggableProps}
												>
													{item.order && itemType === 'reordered' && (
														<span className="pointer-type">RD</span>
													)}
													{item.order && itemType !== 'reordered' && (
														<span className="pointer-type">CR</span>
													)}
													<strong className="title">{itemTitle}</strong>
													<div className="pointer-actions">
														{item.order && (
															<span
																role="button"
																tabIndex="0"
																title={tooltipText}
																className="dashicons dashicons-undo delete-pointer"
																onClick={(event) => {
																	event.preventDefault();
																	removePointer(item);
																}}
																onKeyDown={(event) => {
																	event.preventDefault();
																	removePointer(item);
																}}
															>
																<span className="screen-reader-text">
																	Remove Post
																</span>
															</span>
														)}
														<span
															className="dashicons dashicons-menu handle"
															{...provided2.dragHandleProps}
															title={__(
																'Drag post up or down to reposition',
																'elasticpress',
															)}
														/>
													</div>
												</div>
											)}
										</Draggable>
									</Fragment>
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
								setSearchText(e.target.value);
								doSearch(e.target.value);
							}}
						/>
					</div>

					<div className="pointer-results">{renderSearchResults(searchResults)}</div>
				</div>
			</div>
		</div>
	);
};
