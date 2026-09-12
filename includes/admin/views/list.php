<?php
/**
 * Snippets list view.
 *
 * @package RepoCodeSnippets
 * @var array      $snippets
 * @var array|null $notice
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap rcs-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Repo Snippets', 'repo-code-snippets' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=rcs-snippet-edit' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'repo-code-snippets' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped rcs-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'repo-code-snippets' ); ?></th>
				<th style="width:90px"><?php esc_html_e( 'Type', 'repo-code-snippets' ); ?></th>
				<th style="width:110px"><?php esc_html_e( 'Scope', 'repo-code-snippets' ); ?></th>
				<th style="width:90px"><?php esc_html_e( 'Priority', 'repo-code-snippets' ); ?></th>
				<th style="width:140px"><?php esc_html_e( 'Status', 'repo-code-snippets' ); ?></th>
				<th style="width:160px"><?php esc_html_e( 'Actions', 'repo-code-snippets' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $snippets ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No snippets yet. Create one or Pull from Git.', 'repo-code-snippets' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $snippets as $item ) : ?>
					<?php
					$m      = $item['meta'];
					$active = ( 'active' === $m['status'] );
					?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=rcs-snippet-edit&slug=' . rawurlencode( $m['slug'] ) ) ); ?>">
									<?php echo esc_html( $m['name'] ? $m['name'] : $m['slug'] ); ?>
								</a>
							</strong>
							<?php if ( ! empty( $m['error'] ) ) : ?>
								<p class="rcs-error-hint"><?php echo esc_html( $m['error'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $m['description'] ) ) : ?>
								<p class="description"><?php echo esc_html( $m['description'] ); ?></p>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( strtoupper( $m['type'] ) ); ?></code></td>
						<td><?php echo esc_html( $m['scope'] ); ?></td>
						<td><?php echo esc_html( (string) $m['priority'] ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcs-inline-form">
								<?php wp_nonce_field( 'rcs_toggle_snippet' ); ?>
								<input type="hidden" name="action" value="rcs_toggle_snippet" />
								<input type="hidden" name="slug" value="<?php echo esc_attr( $m['slug'] ); ?>" />
								<input type="hidden" name="status" value="<?php echo esc_attr( $active ? 'inactive' : 'active' ); ?>" />
								<button type="submit" class="button button-small <?php echo $active ? 'rcs-btn-active' : ''; ?>">
									<?php echo $active ? esc_html__( 'Active', 'repo-code-snippets' ) : esc_html__( 'Inactive', 'repo-code-snippets' ); ?>
								</button>
							</form>
						</td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=rcs-snippet-edit&slug=' . rawurlencode( $m['slug'] ) ) ); ?>">
								<?php esc_html_e( 'Edit', 'repo-code-snippets' ); ?>
							</a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcs-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this snippet?', 'repo-code-snippets' ) ); ?>');">
								<?php wp_nonce_field( 'rcs_delete_snippet' ); ?>
								<input type="hidden" name="action" value="rcs_delete_snippet" />
								<input type="hidden" name="slug" value="<?php echo esc_attr( $m['slug'] ); ?>" />
								<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'repo-code-snippets' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
