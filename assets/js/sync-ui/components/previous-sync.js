/**
 * External dependencies.
 */
import classnames from 'classnames';

/**
 * WordPress dependencies.
 */
import { Icon } from '@wordpress/components';
import { useMemo, WPElement } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { __, _n, _x, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import error from './icons/error';
import success from './icons/success';

/**
 * Delete checkbox component.
 *
 * @param {object} props Component props.
 * @param {number} props.failures Number of failed items.
 * @param {boolean} props.isFullSync Whether the job requested a fresh rebuild.
 * @param {object} props.scope Saved target sites and shared content.
 * @param {string} props.method Sync method.
 * @param {string} props.stateDatetime Sync end date and time.
 * @param {string} props.status Sync status.
 * @param {string} props.trigger Sync trigger.
 * @returns {WPElement} Sync page component.
 */
export default ({ failures, isFullSync, method, scope, stateDatetime, status, trigger }) => {
	/**
	 * When the sync was started.
	 */
	const when = useMemo(() => dateI18n('l F j, Y g:ia', stateDatetime), [stateDatetime]);

	/**
	 * How the sync completed.
	 */
	const how = useMemo(() => {
		switch (status) {
			case 'failed':
				return __('Failed.', 'elasticpress');
			case 'with_errors':
				return failures
					? sprintf(
							/* translators: number of errors */
							_n(
								'Completed with %d error.',
								'Completed with %d errors.',
								failures,
								'elasticpress',
							),
							failures,
						)
					: __('Completed with errors.', 'elasticpress');
			case 'aborted':
				return __('Stopped.', 'elasticpress');
			case 'success':
				return __('Completed successfully.', 'elasticpress');
			default:
				return __('Completed.', 'elasticpress');
		}
	}, [failures, status]);

	/**
	 * Why the sync was started.
	 */
	const why = useMemo(() => {
		if (method === 'cli') {
			return __('Manual sync from WP CLI.', 'elasticpress');
		}

		switch (trigger) {
			case 'features':
				return __('Automatic sync after settings change.', 'elasticpress');
			case 'install':
				return __('Automatic sync after installation.', 'elasticpress');
			case 'synonyms-error':
				return __('Manual sync following an error in synonyms settings.', 'elasticpress');
			case 'manual':
				return __('Manual sync from Sync Settings.', 'elasticpress');
			case 'upgrade':
				return __('Automatic sync after plugin update.', 'elasticpress');
			default:
				return null;
		}
	}, [method, trigger]);

	/**
	 * Whether the sync has errors.
	 */
	const isError = useMemo(() => {
		return status === 'failed' || status === 'with_errors' || status === 'aborted';
	}, [status]);

	/**
	 * Whether the sync was a success.
	 */
	const isSuccess = useMemo(() => {
		return status === 'success';
	}, [status]);

	const scopeText = useMemo(() => {
		if (!scope) {
			return __('Not recorded', 'elasticpress');
		}

		const parts = [];
		const sites = scope.sites.map((site) =>
			sprintf(
				/* translators: 1: Site name, 2: Site address, 3: Site ID. */
				__('%1$s (%2$s, ID %3$d)', 'elasticpress'),
				site.name,
				site.url,
				site.id,
			),
		);

		if (sites.length) {
			/* translators: List of targeted sites. */
			parts.push(sprintf(__('Sites: %s', 'elasticpress'), sites.join('; ')));
		}

		if (scope.shared.length) {
			parts.push(
				sprintf(
					/* translators: List of shared indexable names. */
					__('Shared network content: %s', 'elasticpress'),
					scope.shared.join(', '),
				),
			);
		}

		return parts.length ? parts.join('; ') : __('No targets', 'elasticpress');
	}, [scope]);

	const syncMode = useMemo(() => {
		if (typeof isFullSync !== 'boolean') {
			return __('Not recorded', 'elasticpress');
		}

		return isFullSync
			? __('Fresh rebuild', 'elasticpress')
			: __('Ordinary sync', 'elasticpress');
	}, [isFullSync]);

	return (
		<div
			className={classnames('ep-previous-sync', {
				'is-error': isError,
				'is-success': isSuccess,
			})}
		>
			<Icon icon={isError ? error : success} />
			<div className="ep-previous-sync__title">
				{why
					? sprintf(
							/* translators: %1$s Sync date and time. %2%s sync trigger. */ _x(
								'%1$s — %2$s',
								'Sync info',
								'elasticpress',
							),
							when,
							why,
						)
					: when}
			</div>
			<div className="ep-previous-sync__help">
				<div>{how}</div>
				<div>
					{sprintf(
						/* translators: Saved sync target scope. */
						__('Target scope: %s', 'elasticpress'),
						scopeText,
					)}
				</div>
				<div>
					{sprintf(
						/* translators: Ordinary sync or fresh rebuild. */
						__('Sync mode: %s', 'elasticpress'),
						syncMode,
					)}
				</div>
			</div>
		</div>
	);
};
