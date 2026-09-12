<?php
// Sample: adds a body class on the frontend.
add_filter(
	'body_class',
	static function ( $classes ) {
		$classes[] = 'rcs-example-greeting';
		return $classes;
	}
);
