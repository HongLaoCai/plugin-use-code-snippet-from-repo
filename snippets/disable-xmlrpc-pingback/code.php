add_filter(
	'xmlrpc_methods',
	static function ( $methods ) {
		unset( $methods['pingback.ping'] );
		return $methods;
	}
);
