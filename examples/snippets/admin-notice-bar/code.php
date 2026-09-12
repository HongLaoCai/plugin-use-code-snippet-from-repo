add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-info is-dismissible"><p><strong>RCS:</strong> Admin-only PHP snippet is running.</p></div>';
	}
);
