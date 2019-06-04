// External
import React, { Component } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { debounce } from '../utils/debounce';
import { pluck } from '../utils/pluck';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';

apiFetch.use( apiFetch.createRootURLMiddleware( window.epOrdering.restApiRoot ) );

/**
 * Pointer component
 */
export class Pointers extends Component {

	titleInput = null;

	/**
	 * Initializes the component with initial state set by WP
	 *
	 * @param props
	 */
	constructor( props ) {
		super( props );

		// We need to know the title of the page and react to changes since this is the query we search for
		this.titleInput = document.getElementById( 'title' );

		this.state = {
			pointers: window.epOrdering.pointers,
			posts: window.epOrdering.posts,
			title: this.titleInput.value,
			defaultResults: {},
			searchText: '',
			searchResults: {},
		};

		this.titleInput.addEventListener( 'keyup', debounce( this.handleTitleChange, 200 ) );

		this.getDefaultResults();
	}

	handleTitleChange = () => {
		this.setState( { title: this.titleInput.value } );
	};

	getDefaultResults = () => {
		let searchTerm = this.state.title;

		apiFetch( {
			path: `/elasticpress/v1/pointer_search?s=${searchTerm}`,
		} ).then( result => {

			let { defaultResults } = this.state;

			defaultResults[ searchTerm ] = result;

			this.setState( { defaultResults } );
		} );
	};

	removePointer = ( pointer ) => {
		let { pointers } = this.state;

		delete pointers[ pointers.indexOf( pointer ) ];
		pointers = pointers.filter( item => null !== item );

		this.setState( { pointers } );
	};

	getMergedPosts = () => {
		let merged = this.state.defaultResults[ this.state.title ].slice();
		let pointers = this.state.pointers;

		let setIds = {};
		merged.map( item => {
			setIds[ item.ID ] = item;
		} );

		pointers.map( pointer => {
			// Remove the original if a duplicate
			if ( setIds[ pointer.ID ] ) {
				delete merged[ merged.indexOf( setIds[ pointer.ID ] ) ];
			}

			// Insert into proper location
			merged.splice( parseInt( pointer.order, 10 ) - 1, 0, pointer );
		} );

		return merged;
	};

	doSearch = debounce( () => {
		let searchTerm = this.state.searchText;

		// Set loading state
		let { searchResults } = this.state;
		searchResults[ searchTerm ] = false;
		this.setState( { searchResults } );

		apiFetch( {
			path: `/elasticpress/v1/pointer_search?s=${searchTerm}`
		} ).then( result => {
			let { searchResults } = this.state;

			searchResults[ searchTerm ] = result;

			this.setState( { searchResults } );
		} );
	}, 200 );

	addPointer = ( post ) => {
		const id = post.ID;
		const { posts, pointers } = this.state;

		if ( ! posts[ id ] ) {
			posts[ id ] = post;
			this.setState( { posts } );
		}

		const merged = this.getMergedPosts();

		pointers.push( {
			ID: id,
			order: merged.length + 1,
		} );

		this.setState( { pointers } );
	};

	/**
	 * Callback when drag/drop is complete.
	 *
	 * Only the pointers are able to be dragged around, so all we need to do is increase any pointer by one that is
	 * either at the current position or greater
	 *
	 * @param result
	 */
	onDragComplete = ( result ) => {
		// dropped outside the list
		if ( ! result.destination ) {
			return;
		}

		let items = this.getMergedPosts();
		const startIndex = result.source.index;
		const endIndex = result.destination.index;

		const [removed] = items.splice( startIndex, 1 );
		items.splice( endIndex, 0, removed );

		// Now _all_ the items are in order - grab the pointers and set the new positions to state
		let pointers = [];

		items.map( ( item, index ) => {
			if ( item.order ) {
				pointers.push( {
					ID: item.ID,
					order: index + 1,
				} );
			}
		} );

		this.setState( { pointers } );
	};

	/**
	 * Renders the component
	 *
	 * @returns {*}
	 */
	render() {
		const { posts, defaultResults } = this.state;

		if ( ! defaultResults[ this.state.title ] ) {
			return (
				<div className="loading">
					<div className="spinner is-active"></div>
					<span>Loading Result Preview...</span>
				</div>
			);
		}

		const mergedPosts = this.getMergedPosts();
		const renderedIds = pluck( this.state.pointers, 'ID' );

		const searchResults = this.state.searchResults[ this.state.searchText ] ?
			this.state.searchResults[ this.state.searchText ].filter( item => -1 === renderedIds.indexOf( item.ID ) ) :
			false;

		return (
			<div>
				<input type="hidden" name="search-ordering-nonce" value={window.epOrdering.nonce} />
				<input type="hidden" name="ordered_posts" value={ JSON.stringify( this.state.pointers ) }/>
				<DragDropContext onDragEnd={this.onDragComplete}>
					<Droppable droppableId="droppable">
						{( provided, snapshot ) => (
							<div
								className="pointers"
								{...provided.droppableProps}
								ref={provided.innerRef}
							>
								{mergedPosts.map( ( item, index ) => {
									if ( item.order ) {
										// is pointer
										const referencedPost = posts[ item.ID ];

										return (
											<Draggable key={item.ID} draggableId={item.ID} index={index}>
												{( provided, snapshot ) => (
													<div
														className="pointer"
														ref={provided.innerRef}
														{...provided.draggableProps}
														{...provided.dragHandleProps}
													>
														<strong className="title">{referencedPost.post_title}</strong>
														<span className="dashicons dashicons-trash delete-pointer" onClick={ e => { e.preventDefault(); this.removePointer( item ); } }><span className="screen-reader-text">Remove Post</span></span>
													</div>
												)}
											</Draggable>
										);
									} else {
										// is default post
										return (
											<Draggable key={item.ID} draggableId={item.ID} index={index} isDragDisabled={true}>
												{( provided, snapshot ) => (
													<div
														className="post"
														ref={provided.innerRef}
														{...provided.draggableProps}
														{...provided.dragHandleProps}
													>
														<strong className="title">{item.post_title}</strong>
													</div>
												)}
											</Draggable>
										);
									}
								} )}
								{provided.placeholder}
							</div>
						)}
					</Droppable>
				</DragDropContext>

				<hr/>

				<div className="pointer-search">
					<input
						type="text"
						className="widefat search-pointers"
						placeholder="Search for Post"
						value={ this.state.searchText }
						onChange={ e => {
							this.setState( { searchText: e.target.value } );
							this.doSearch();
						} }/>

					<div className="pointer-results">
						{ this.searchResults( searchResults ) }
					</div>
				</div>
			</div>
		);
	}

	searchResults = ( searchResults ) => {
		if ( '' === this.state.searchText ) {
			return;
		}

		if ( false === searchResults ) {
			return (
				<div>
					<div className="spinner"></div>
					Loading...
				</div>
			);
		}

		if ( 0 === searchResults.length ) {
			return (
				<div>No results found.</div>
			);
		}

		return searchResults.map( result => {
			return (
				<div className="pointer-result" key={result.ID}>
					<span className="title">{result.post_title}</span>
					<span className="dashicons dashicons-plus add-pointer" onClick={ e => { e.preventDefault(); this.addPointer( result ); } }>
						<span className="screen-reader-text">Add Post</span>
					</span>
				</div>
			);
		} );
	};

}
