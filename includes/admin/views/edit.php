<?php
/**
 * Snippet editor view.
 *
 * @package RepoCodeSnippets
 * @var array      $meta
 * @var string     $code
 * @var bool       $is_new
 * @var array|null $notice
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap rcs-wrap">
	<h1><?php echo $is_new ? esc_html__( 'Add Snippet', 'repo-code-snippets' ) : esc_html__( 'Edit Snippet', 'repo-code-snippets' ); ?></h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $meta['error'] ) ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Auto-deactivated after a fatal error:', 'repo-code-snippets' ); ?></strong>
				<?php echo esc_html( $meta['error'] ); ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcs-edit-form">
		<?php wp_nonce_field( 'rcs_save_snippet' ); ?>
		<input type="hidden" name="action" value="rcs_save_snippet" />
		<input type="hidden" name="old_slug" value="<?php echo esc_attr( $is_new ? '' : $meta['slug'] ); ?>" />

		<div class="rcs-edit-layout">
			<div class="rcs-edit-main">
				<p>
					<label for="rcs-name"><strong><?php esc_html_e( 'Name', 'repo-code-snippets' ); ?></strong></label><br />
					<input type="text" class="widefat" id="rcs-name" name="name" value="<?php echo esc_attr( $meta['name'] ); ?>" required />
				</p>

				<p>
					<label for="rcs-code"><strong><?php esc_html_e( 'Code', 'repo-code-snippets' ); ?></strong></label>
					<textarea id="rcs-code" name="code" class="rcs-code widefat" rows="22" spellcheck="false"><?php echo esc_textarea( $code ); ?></textarea>
					<span class="description"><?php esc_html_e( 'For PHP, omit the opening <?php tag (it is added when saving).', 'repo-code-snippets' ); ?></span>
				</p>
			</div>

			<div class="rcs-edit-side">
				<div class="rcs-panel">
					<h2><?php esc_html_e( 'Status', 'repo-code-snippets' ); ?></h2>
					<label class="rcs-switch">
						<input type="checkbox" name="status" value="1" <?php checked( $meta['status'], 'active' ); ?> />
						<span><?php esc_html_e( 'Active', 'repo-code-snippets' ); ?></span>
					</label>
					<?php submit_button( __( 'Save Snippet', 'repo-code-snippets' ), 'primary large', 'submit', false ); ?>
				</div>

				<div class="rcs-panel">
					<h2><?php esc_html_e( 'Settings', 'repo-code-snippets' ); ?></h2>

					<p>
						<label for="rcs-type"><?php esc_html_e( 'Type', 'repo-code-snippets' ); ?></label><br />
						<select id="rcs-type" name="type">
							<?php foreach ( array( 'php' => 'PHP', 'css' => 'CSS', 'js' => 'JavaScript', 'html' => 'HTML' ) as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $meta['type'], $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label for="rcs-scope"><?php esc_html_e( 'Run location', 'repo-code-snippets' ); ?></label><br />
						<select id="rcs-scope" name="scope">
							<option value="everywhere" <?php selected( $meta['scope'], 'everywhere' ); ?>><?php esc_html_e( 'Everywhere', 'repo-code-snippets' ); ?></option>
							<option value="frontend" <?php selected( $meta['scope'], 'frontend' ); ?>><?php esc_html_e( 'Frontend only', 'repo-code-snippets' ); ?></option>
							<option value="admin" <?php selected( $meta['scope'], 'admin' ); ?>><?php esc_html_e( 'Admin only', 'repo-code-snippets' ); ?></option>
						</select>
					</p>

					<p>
						<label for="rcs-slug"><?php esc_html_e( 'Slug (folder name)', 'repo-code-snippets' ); ?></label><br />
						<input type="text" id="rcs-slug" name="slug" value="<?php echo esc_attr( $meta['slug'] ); ?>" class="widefat" />
					</p>

					<p>
						<label for="rcs-priority"><?php esc_html_e( 'Priority', 'repo-code-snippets' ); ?></label><br />
						<input type="number" id="rcs-priority" name="priority" value="<?php echo esc_attr( (string) $meta['priority'] ); ?>" />
					</p>

					<p>
						<label for="rcs-description"><?php esc_html_e( 'Description', 'repo-code-snippets' ); ?></label><br />
						<textarea id="rcs-description" name="description" class="widefat" rows="3"><?php echo esc_textarea( $meta['description'] ); ?></textarea>
					</p>
				</div>
			</div>
		</div>
	</form>
</div>
