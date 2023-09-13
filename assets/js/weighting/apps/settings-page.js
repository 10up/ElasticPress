/**
 * WordPress dependencies.
 */
import { SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticeStore } from '@wordpress/notices';

/**
 * Internal Dependencies.
 */
import Actions from '../components/actions';
import PostType from '../components/post-type';
import { useWeighting } from '../provider';

/**
 * Weighting settings app.
 *
 * @returns {WPElement} Element.
 */
export default () => {
	const { removeNotice } = useDispatch(noticeStore);

	const { notices } = useSelect((select) => {
		return {
			notices: select(noticeStore).getNotices(),
		};
	}, []);

	const { save, weightableFields } = useWeighting();

	/**
	 * Submit event.
	 *
	 * @param {Event} event Submit event.
	 */
	const onSubmit = (event) => {
		event.preventDefault();

		save();
	};

	return (
		<>
			<form className="ep-weighting-screen" onSubmit={onSubmit}>
				<h1 className="page-title">
					{__('Manage Search Fields & Weighting', 'elasticpress')}
				</h1>
				<div className="page-description">
					<p>
						{__(
							'This dashboard enables you to select which fields ElasticPress should sync, whether to use those fields in searches, and how heavily to weight fields in the search algorithm. In general, increasing the Weight of a field will increase the relevancy score of a post that has matching text in that field.',
							'elasticpress',
						)}
					</p>
					<p>
						{__(
							'For example, adding more weight to the title attribute will cause search matches on the post title to appear more prominently.',
							'elasticpress',
						)}
					</p>
				</div>
				{weightableFields.map(({ key }) => {
					return <PostType key={key} postType={key} />;
				})}
				<Actions />
			</form>
			<SnackbarList notices={notices} onRemove={(notice) => removeNotice(notice)} />
		</>
	);
};
