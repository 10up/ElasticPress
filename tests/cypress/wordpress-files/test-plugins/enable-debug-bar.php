<?php
/**
 * Plugin Name: Enable Debug bar
 * Description: Enable debug bar for all users
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 */

add_filter( 'pre_option_ep_last_sync', '__return_empty_array' );
