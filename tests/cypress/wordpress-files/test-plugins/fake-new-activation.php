<?php
/**
 * Plugin Name: Fake new activation
 * Description: Fake new activation state to test the quick setup.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 */

add_filter( 'pre_option_ep_last_sync', '__return_empty_array' );
