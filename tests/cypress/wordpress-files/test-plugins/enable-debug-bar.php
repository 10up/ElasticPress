<?php
/**
 * Plugin Name: Enable Debug bar
 * Description: Enable debug bar for all users
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 */

add_filter( 'debug_bar_enable', '__return_true' );
add_filter( 'show_admin_bar', '__return_true' );
