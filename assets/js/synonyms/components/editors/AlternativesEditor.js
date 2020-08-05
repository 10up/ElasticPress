import React, { useContext } from 'react';
import AlternativeEditor from './AlternativeEditor';
import { Dispatch } from '../../context';

/**
 * Synonyms editor component.
 */
export default function AlterativesEditor( { alternatives } ) {
	const dispatch = useContext( Dispatch );
	const { alternativesInputHeading, alternativesPrimaryHeading } = window.epSynonyms.i18n;

	/**
	 * Handle click.
	 * @param {Array} token
	 */
	const handleClick = e => {
		dispatch( { type: 'ADD_ALTERNATIVE' } );
		e.preventDefault();
	};

	return (
		<div className="synonym-alternatives-editor metabox-holder">
			<div className="postbox">
				<h2 className="hndle">
					<span className="synonym-alternatives__primary-heading">
						{ alternativesPrimaryHeading }
					</span>
					<span className="synonym-alternatives__input-heading">
						{ alternativesInputHeading }
					</span>
				</h2>
				<div className="inside">
					{
						alternatives.map( ( { synonyms, id } ) => (
							<AlternativeEditor
								updateAction="UPDATE_ALTERNATIVE"
								removeAction="REMOVE_ALTERNATIVE"
								synonyms={synonyms}
								key={id}
								id={id}
							/>
						) )
					}
					<button
						className="button button-secondary"
						onClick={handleClick}
					>Add Set</button>
				</div>
			</div>
		</div>
	);
}
