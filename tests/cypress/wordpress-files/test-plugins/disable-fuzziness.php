<?php
/**
 * Plugin Name: Disable Fuzziness
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_filter( 'ep_post_match_fuzziness', '__return_zero' );
