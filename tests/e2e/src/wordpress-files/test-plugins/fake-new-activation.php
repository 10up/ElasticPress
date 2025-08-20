<?php
/**
 * Plugin Name: Fake new activation
 * Description: Fake new activation state to test the quick setup.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_filter( 'pre_option_ep_last_sync', '__return_empty_array' );
add_filter( 'option_ep_skip_install', '__return_false' );
