/**
 * WordPress dependencies.
 */
import { Button } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies.
 */
import { useSettingsScreen } from '../../settings-screen';
import PostType from '../components/post-type';
import { useWeightingSettings } from '../provider';

/**
 * Weighting settings app.
 *
 * @returns {WPElement} Element.
 */
export default () => {
	const { createNotice } = useSettingsScreen();
	const { isBusy, save, weightableFields } = useWeightingSettings();

	/**
	 * Submit event.
	 *
	 * @param {Event} event Submit event.
	 */
	const onSubmit = async (event) => {
		event.preventDefault();

		try {
			await save();
			createNotice('success', __('Settings saved.', 'elasticpress'));
		} catch (e) {
			createNotice('error', __('Something went wrong. Please try again.', 'elasticpress'));
		}
	};

	return (
		<>
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
			<form className="ep-weighting-screen" onSubmit={onSubmit}>
				{weightableFields.map(({ key }) => {
					return <PostType key={key} postType={key} />;
				})}
				<Button disabled={isBusy} isBusy={isBusy} isPrimary type="submit" variant="primary">
					{__('Save changes', 'elasticpress')}
				</Button>
			</form>
		</>
	);
};
