<?php
/**
 * Plugin Name: Enable Did You Mean Block in Editor
 * Description: Enables the Did You Mean block in the post editor for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_filter( 'ep_did_you_mean_enabled_in_editor', '__return_true' );
