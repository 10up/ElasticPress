<?php
/**
 * Plugin Name: Site Sync Content
 * Description: Site-only post types and taxonomy for native-runtime sync tests.
 *
 * @package elasticpress
 */

add_action(
	'init',
	static function () {
		register_post_type(
			'ep_site_book',
			[
				'public'       => true,
				'label'        => 'Site Books',
				'show_in_rest' => true,
			]
		);
		register_post_type(
			'ep_private_book',
			[
				'public' => false,
				'label'  => 'Private Books',
			]
		);
		register_taxonomy(
			'ep_book_genre',
			'ep_site_book',
			[
				'public' => true,
				'label'  => 'Book Genres',
			]
		);
	}
);
