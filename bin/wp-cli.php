<?php

WP_CLI::add_command( 'elasticpress', 'ElasticPress_CLI_Command' );

/**
 * CLI Commands for ElasticPress
 *
 */
class ElasticPress_CLI_Command extends WP_CLI_Command {

	/**
	 * Add the document mapping
	 *
	 * @synopsis [--network-wide]
	 * @subcommand put-mapping
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function put_mapping( $args, $assoc_args ) {
		$site_id = null;
		if ( ! empty( $assoc_args['network-wide'] ) ) {
			$site_id = 0;
		}

		WP_CLI::line( "Adding mapping" );

		// Flushes index first
		$this->flush( $args, $assoc_args );

		$result = ep_put_mapping( $site_id );

		if ( $result ) {
			WP_CLI::success( 'Mapping sent' );
		} else {
			WP_CLI::error( 'Mapping failed' );
		}
	}

	/**
	 * Flush the current index. !!Warning!! This empties your elasticsearch index for the entire site.
	 *
	 * @todo replace this function with one that updates all rows with a --force option
	 * @synopsis [--network-wide]
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function flush( $args, $assoc_args ) {
		$site_id = null;
		if ( ! empty( $assoc_args['network-wide'] ) ) {
			$site_id = 0;
		}

		WP_CLI::line( "Flushing index..." );

		$result = ep_flush( $site_id );

		if ( $result ) {
			WP_CLI::success( 'Index flushed' );
		} else {
			WP_CLI::error( 'Flush failed' );
		}
	}

	/**
	 * Index all posts for a site or network wide
	 *
	 * @synopsis [--network-wide]
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function index( $args, $assoc_args ) {
		if ( empty( $assoc_args['network-wide'] ) ) {
			WP_CLI::line( 'Indexing posts on current site' );

			$site_config = ep_get_option();

			if ( ! empty( $site_config['post_types'] ) ) {

				$synced = 0;
				$errors = array();
				$offset = 0;

				while ( true ) {

					$args = array(
						'posts_per_page' => 500,
						'post_type'      => $site_config['post_types'],
						'offset'         => $offset,
						'post_status'    => 'publish',
					);

					$query = new WP_Query( $args );

					if ( $query->have_posts() ) {

						while ( $query->have_posts() ) {
							$query->the_post();

							$result = ep_sync_post( get_the_ID(), null, null );

							if ( ! $result ) {
								$errors[] = get_the_ID();
							} else {
								$synced++;
							}
						}
					} else {
						break;
					}

					$offset += 500;
				}

				wp_reset_postdata();

				WP_CLI::line( 'Number of posts synced on current site (' . get_current_blog_id() . '): ' . $synced );

				if ( ! empty( $errors ) ) {
					WP_CLI::error( 'Number of post sync errors on current site (' . get_current_blog_id() . '): ' . count( $errors ) );
				}
			}

		} else {
			WP_CLI::line( 'Indexing posts network-wide' );

			$sites = wp_get_sites();

			foreach ( $sites as $site ) {
				$site_config = ep_get_option( $site['blog_id'] );

				if ( ! empty( $site_config['post_types'] ) ) {

					// Do sync for this site!
					switch_to_blog( $site['blog_id'] );

					$synced = 0;
					$errors = array();
					$offset = 0;

					while ( true ) {

						$args = array(
							'posts_per_page' => 300000,
							'post_type'      => $site_config['post_types'],
							'post_status'    => 'publish',
							'offset'         => $offset,
						);

						$query = new WP_Query( $args );

						if ( $query->have_posts() ) {

							while ( $query->have_posts() ) {
								$query->the_post();

								$result = ep_sync_post( get_the_ID(), null, 0 );

								if ( ! $result ) {
									$errors[] = get_the_ID();
								} else {
									$synced++;
								}
							}
						} else {
							break;
						}

						$offset += 500;
					}

					wp_reset_postdata();

					WP_CLI::line( 'Number of posts synced on site ' . get_current_blog_id() . ': ' . $synced );

					if ( ! empty( $errors ) ) {
						WP_CLI::error( 'Number of post sync errors on site ' . get_current_blog_id() . ': ' . count( $errors ) );
					}

					restore_current_blog();
				}
			}
		}

		WP_CLI::success( 'Done!' );
	}
}