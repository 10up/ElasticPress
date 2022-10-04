<?php
/**
 * Plugin Name: Custom Instant Results Template
 * Description: Customizes the Instant Results template for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 */

add_action(
	'wp_enqueue_scripts',
	function() {
		wp_add_inline_script(
			'elasticpress-instant-results',
			"const el = wp.element.createElement;
			const CustomResult = ({ date, title, url }) => {
				return el(
					'div',
					{
						className: 'my-custom-result',
					},
					el(
						'strong',
						{},
						el(
							'a',
							{ href: url },
							title
						),
					),
					' ',
					date
				);
			};
			wp.hooks.addFilter('elasticpress.InstantResults.Result', 'myTheme/customResult', () => CustomResult);",
			'before'
		);
	},
	11
);
