<?php
/**
 * Template for ElasticPress sync page
 *
 * @since  4.0.0
 * @package elasticpress
 */

use ElasticPress\Utils as Utils;

$ep_last_index          = Utils\get_option( 'ep_last_index' );
$ep_last_sync_has_error = ! empty( $ep_last_index['failed'] );

?>
<?php require_once __DIR__ . '/header.php'; ?>

<div id="ep-sync-page" class="wrap">
	<h1><?php esc_html_e( 'Sync Settings', 'elasticpress' ); ?></h1>

	<div class="ep-sync-data">
		<div class="card ep-sync-box">
			<div class="ep-sync-box__description-actions">
				<div class="ep-sync-box__description">
					<p class="ep-sync-box__description_text">
						<?php esc_html_e( 'If you are missing data in your search results or have recently added custom content types to your site, you should run a sync to reflect these changes.', 'elasticpress' ); ?>
					</p>

					<div class="ep-last-sync">
						<p class="ep-last-sync__title">
							<?php echo esc_html__( 'Last sync:', 'elasticpress' ); ?>
						</p>
						<?php if ( $ep_last_index ) : ?>
							<img
								class="ep-last-sync__icon-status"
								width="16"
								src="<?php echo esc_url( plugins_url( $ep_last_sync_has_error ? '/images/thumbsdown.svg' : '/images/thumbsup.svg', dirname( __DIR__ ) ) ); ?>"
							/>
							<span class="ep-last-sync__status">
								<?php echo $ep_last_sync_has_error ? esc_html__( 'Sync unsuccessful on ', 'elasticpress' ) : esc_html__( 'Sync success on ', 'elasticpress' ); ?>
							</span>
						<?php endif; ?>
						<span class="ep-last-sync__date"></span>
					</div>
				</div>
				<div class="ep-sync-box__action">
					<button type="button" class="button button-primary ep-sync-box__button ep-sync-box__button-sync">
						<span class="dashicons dashicons-update-alt ep-sync-box__icon-button"></span> <?php echo esc_html__( 'Sync Now', 'elasticpress' ); ?>
					</button>
					<a class="ep-sync-box__learn-more-link" href="#">
						<?php echo esc_html__( 'Learn more', 'elasticpress' ); ?>
					</a>

					<div class="ep-sync-box__buttons">
						<button type="button" class="button button-primary ep-sync-box__button-pause pause-sync">
							<img width="16" src="<?php echo esc_url( plugins_url( '/images/pause.svg', dirname( __DIR__ ) ) ); ?>" />
							<span class="ep-sync-box__button-text">
								<?php echo esc_html__( 'Pause', 'elasticpress' ); ?>
							</span>
						</button>

						<button type="button" class="button button-primary ep-sync-box__button-resume resume-sync">
							<img width="16" src="<?php echo esc_url( plugins_url( '/images/resume.svg', dirname( __DIR__ ) ) ); ?>" />
							<span class="ep-sync-box__button-text">
								<?php echo esc_html__( 'Resume', 'elasticpress' ); ?>
							</span>
						</button>

						<button type="button" class="button button-primary ep-sync-box__button-stop">
							<img width="16" src="<?php echo esc_url( plugins_url( '/images/stop.svg', dirname( __DIR__ ) ) ); ?>" />
							<span class="ep-sync-box__button-text">
								<?php echo esc_html__( 'Stop', 'elasticpress' ); ?>
							</span>
						</button>
					</div>
				</div>
			</div>
			<div class="ep-sync-box__progress-wrapper">
				<div class="ep-sync-box__progress">
					<div class="ep-sync-box__sync-in-progress">
						<img width="36" height="36" src="<?php echo esc_url( plugins_url( '/images/sync-in-progress.png', dirname( __DIR__ ) ) ); ?>" />
						<div class="ep-sync-box__sync-in-progress-info">
							<div class="ep-sync-box__progress-info"><?php esc_html_e( 'Sync in progress', 'elasticpress' ); ?></div>
							<div class="ep-sync-box__start-time"><?php esc_html_e( 'Start time:', 'elasticpress' ); ?> <span class="ep-sync-box__start-time-date"></span></div>
						</div>
					</div>
					<span class="ep-sync-box__progressbar">
						<span class="ep-sync-box__progressbar ep-sync-box__progressbar_animated">
						</span>
					</span>
				</div>
				<div>
					<a href="#" role="button" class="ep-sync-box__show-hide-log">
						<?php echo esc_html__( 'Show log', 'elasticpress' ); ?>
					</a>
					<div class="ep-sync-box__output-tabs_hide">
						<div class="ep-sync-box__output-tabs">
							<div class="ep-sync-box__output-tab ep-sync-box__output-tab_active ep-sync-box__output-tab-fulllog">
								<?php esc_html_e( 'Full log', 'elasticpress' ); ?>
							</div>
							<div class="ep-sync-box__output-tab ep-sync-box__output-tab-error">
								<?php esc_html_e( 'Errors (0)', 'elasticpress' ); ?>
							</div>
						</div>
						<div class="ep-sync-box__output ep-sync-box__output-fulllog ep-sync-box__output_active">
							<div id="ep-sync-output" class="ep-sync-box__output-wrapper"></div>
						</div>
						<div class="ep-sync-box__output ep-sync-box__output-error">
							<div id="ep-sync-output-error" class="ep-sync-box__output-wrapper"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="ep-delete-data-and-sync">
		<h2 class="ep-delete-data-and-sync__title">
			<?php esc_html_e( 'Delete All Data and Sync', 'elasticpress' ); ?>
		</h2>
		<div class="card ep-sync-box">
			<div class="ep-sync-box__description">
				<p class="ep-sync-box__description_text">
					<?php esc_html_e( 'If you are still having issues with your search results, you may need to do a completely fresh sync.', 'elasticpress' ); ?>
				</p>
			</div>
			<div class="ep-sync-box__action">
				<button type="button" class="button button-large ep-delete-data-and-sync__button ep-delete-data-and-sync__button-delete">
					<?php echo esc_html__( 'Delete all Data and Start a Fresh Sync ', 'elasticpress' ); ?>
				</button>

				<button type="button" class="button button-large ep-delete-data-and-sync__button ep-delete-data-and-sync__button-cancel">
					<?php echo esc_html__( 'Cancel Deleting Process ', 'elasticpress' ); ?>
				</button>

				<div class="ep-sync-box__buttons">
					<button type="button" class="button button-primary ep-sync-box__button-pause pause-sync">
						<img width="16" src="<?php echo esc_url( plugins_url( '/images/pause.svg', dirname( __DIR__ ) ) ); ?>" />
						<span class="ep-sync-box__button-text">
							<?php echo esc_html__( 'Pause', 'elasticpress' ); ?>
						</span>
					</button>

					<button type="button" class="button button-primary ep-sync-box__button-resume resume-sync">
						<img width="16" src="<?php echo esc_url( plugins_url( '/images/resume.svg', dirname( __DIR__ ) ) ); ?>" />
						<span class="ep-sync-box__button-text">
							<?php echo esc_html__( 'Resume', 'elasticpress' ); ?>
						</span>
					</button>

					<button type="button" class="button button-primary ep-sync-box__button-stop">
						<img width="16" src="<?php echo esc_url( plugins_url( '/images/stop.svg', dirname( __DIR__ ) ) ); ?>" />
						<span class="ep-sync-box__button-text">
							<?php echo esc_html__( 'Stop', 'elasticpress' ); ?>
						</span>
					</button>
				</div>
			</div>
			<div class="ep-sync-box__progress-wrapper">
				<div class="ep-sync-box__progress">
					<div class="ep-sync-box__sync-in-progress">
						<img width="36" height="36" src="<?php echo esc_url( plugins_url( '/images/sync-in-progress.png', dirname( __DIR__ ) ) ); ?>" />
						<div class="ep-sync-box__sync-in-progress-info">
							<div class="ep-sync-box__progress-info"><?php esc_html_e( 'Sync in progress', 'elasticpress' ); ?></div>
							<div class="ep-sync-box__start-time"><?php esc_html_e( 'Start time: ', 'elasticpress' ); ?><span class="ep-sync-box__start-time-date"></span></div>
						</div>
					</div>
					<span class="ep-sync-box__progressbar">
						<span class="ep-sync-box__progressbar ep-sync-box__progressbar_animated">
						</span>
					</span>
				</div>
				<div>
					<a href="#" role="button" class="ep-sync-box__show-hide-log">
						<?php echo esc_html__( 'Show log', 'elasticpress' ); ?>
					</a>
					<div class="ep-sync-box__output-tabs_hide">
						<div class="ep-sync-box__output-tabs">
							<div class="ep-sync-box__output-tab ep-sync-box__output-tab_active ep-sync-box__output-tab-fulllog">
								<?php esc_html_e( 'Full log', 'elasticpress' ); ?>
							</div>
							<div class="ep-sync-box__output-tab ep-sync-box__output-tab-error">
								<?php esc_html_e( 'Errors (0)', 'elasticpress' ); ?>
							</div>
						</div>
						<div class="ep-sync-box__output ep-sync-box__output-fulllog ep-sync-box__output_active">
							<div id="ep-sync-output" class="ep-sync-box__output-wrapper"></div>
						</div>
						<div class="ep-sync-box__output ep-sync-box__output-error">
							<div id="ep-sync-output-error" class="ep-sync-box__output-wrapper"></div>
						</div>
					</div>
				</div>
			</div>

				<div class="ep-delete-data-and-sync__warning">
					<img
						class="ep-delete-data-and-sync__warning-icon"
						width="19"
						src="<?php echo esc_url( plugins_url( '/images/warning.svg', dirname( __DIR__ ) ) ); ?>"
					/>
					<p>
						<?php esc_html_e( 'All indexed data on ElasticPress will be deleted without affecting anything on your WordPress website. This may take a few hours depending on the amount of content that needs to be synced and indexed. While this is happenening, searches will use the default WordPress results', 'elasticpress' ); ?>
					</p>
				</div>
		</div>
	</div>
</div>
