<?php
/**
 * Settings + Git sync view.
 *
 * @package RepoCodeSnippets
 * @var array       $settings
 * @var array|null  $notice
 * @var array|false $diff
 * @var bool        $has_git
 * @var string      $safe_url
 */

defined( 'ABSPATH' ) || exit;

$git_configured = ! empty( $settings['git_url'] );
?>
<div class="wrap rcs-wrap">
	<h1><?php esc_html_e( 'Repo Snippets Settings', 'repo-code-snippets' ); ?></h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'rcs_save_settings' ); ?>
		<input type="hidden" name="action" value="rcs_save_settings" />

		<h2 id="rcs-git"><?php esc_html_e( 'Git Sync (Pull only)', 'repo-code-snippets' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Point to a Git repository and the folder that contains snippet directories (each with meta.json + code.*). Diff compares WordPress vs Git; Pull replaces all local snippets.', 'repo-code-snippets' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="git_url"><?php esc_html_e( 'Git repository URL', 'repo-code-snippets' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="git_url" name="git_url" value="<?php echo esc_attr( $settings['git_url'] ); ?>" placeholder="https://github.com/org/repo.git" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="git_folder"><?php esc_html_e( 'Folder in repo', 'repo-code-snippets' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="git_folder" name="git_folder" value="<?php echo esc_attr( $settings['git_folder'] ); ?>" placeholder="snippets" />
					<p class="description"><?php esc_html_e( 'Relative path inside the repo, e.g. snippets or code/wp-snippets.', 'repo-code-snippets' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="git_branch"><?php esc_html_e( 'Branch', 'repo-code-snippets' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="git_branch" name="git_branch" value="<?php echo esc_attr( $settings['git_branch'] ); ?>" placeholder="main" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="git_token"><?php esc_html_e( 'Access token (optional)', 'repo-code-snippets' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="git_token" name="git_token" value="" autocomplete="new-password" placeholder="<?php echo ! empty( $settings['git_token'] ) ? esc_attr__( '(saved — leave blank to keep)', 'repo-code-snippets' ) : ''; ?>" />
					<label>
						<input type="checkbox" name="git_token_clear" value="1" />
						<?php esc_html_e( 'Clear saved token', 'repo-code-snippets' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Needed for private repos. Used as HTTPS token or GitHub/GitLab bearer.', 'repo-code-snippets' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Fetch method', 'repo-code-snippets' ); ?></th>
				<td>
					<?php if ( $has_git ) : ?>
						<span class="rcs-badge rcs-badge-ok"><?php esc_html_e( 'git CLI available', 'repo-code-snippets' ); ?></span>
					<?php else : ?>
						<span class="rcs-badge"><?php esc_html_e( 'ZIP fallback (GitHub / GitLab)', 'repo-code-snippets' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Safety', 'repo-code-snippets' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-disable on crash', 'repo-code-snippets' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="auto_disable" value="1" <?php checked( ! empty( $settings['auto_disable'] ) ); ?> />
						<?php esc_html_e( 'If a PHP snippet fatals, deactivate that snippet automatically.', 'repo-code-snippets' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Safe Mode URL', 'repo-code-snippets' ); ?></th>
				<td>
					<?php if ( $safe_url ) : ?>
						<code class="rcs-safe-url"><?php echo esc_html( $safe_url ); ?></code>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'Open this URL to stop all snippets (cookie lasts 24h). Or add define( \'REPO_CODE_SNIPPETS_SAFE_MODE\', true ); to wp-config.php.', 'repo-code-snippets' ); ?>
					</p>
					<label>
						<input type="checkbox" name="regenerate_safe_key" value="1" />
						<?php esc_html_e( 'Regenerate Safe Mode key', 'repo-code-snippets' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Storage path', 'repo-code-snippets' ); ?></th>
				<td><code><?php echo esc_html( WP_CONTENT_DIR . '/repo-code-snippets/snippets' ); ?></code></td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'repo-code-snippets' ) ); ?>
	</form>

	<?php if ( $git_configured ) : ?>
		<hr />
		<h2><?php esc_html_e( 'Sync actions', 'repo-code-snippets' ); ?></h2>
		<p><?php esc_html_e( 'Always Diff first. Pull replaces every local snippet with the Git folder contents.', 'repo-code-snippets' ); ?></p>

		<div class="rcs-sync-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcs-inline-form">
				<?php wp_nonce_field( 'rcs_git_diff' ); ?>
				<input type="hidden" name="action" value="rcs_git_diff" />
				<?php submit_button( __( 'Diff', 'repo-code-snippets' ), 'secondary', 'submit', false ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcs-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Pull will REPLACE all WordPress snippets with the Git folder. Continue?', 'repo-code-snippets' ) ); ?>');">
				<?php wp_nonce_field( 'rcs_git_pull' ); ?>
				<input type="hidden" name="action" value="rcs_git_pull" />
				<?php submit_button( __( 'Pull', 'repo-code-snippets' ), 'primary', 'submit', false ); ?>
			</form>
		</div>

		<?php if ( is_array( $diff ) ) : ?>
			<div class="rcs-diff-panel">
				<h3><?php esc_html_e( 'Last Diff result', 'repo-code-snippets' ); ?></h3>
				<p><strong><?php echo esc_html( $diff['summary'] ); ?></strong></p>

				<?php if ( ! empty( $diff['changed'] ) ) : ?>
					<h4><?php esc_html_e( 'Changed', 'repo-code-snippets' ); ?></h4>
					<ul>
						<?php foreach ( $diff['changed'] as $row ) : ?>
							<li>
								<code><?php echo esc_html( $row['slug'] ); ?></code>
								— <?php echo esc_html( implode( ', ', $row['diffs'] ) ); ?>
								<details>
									<summary><?php esc_html_e( 'Show code', 'repo-code-snippets' ); ?></summary>
									<div class="rcs-diff-cols">
										<div>
											<strong><?php esc_html_e( 'WordPress', 'repo-code-snippets' ); ?></strong>
											<pre><?php echo esc_html( $row['local']['code'] ); ?></pre>
										</div>
										<div>
											<strong><?php esc_html_e( 'Git', 'repo-code-snippets' ); ?></strong>
											<pre><?php echo esc_html( $row['remote']['code'] ); ?></pre>
										</div>
									</div>
								</details>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( ! empty( $diff['only_local'] ) ) : ?>
					<h4><?php esc_html_e( 'Only on WordPress', 'repo-code-snippets' ); ?></h4>
					<p><?php echo esc_html( implode( ', ', $diff['only_local'] ) ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $diff['only_remote'] ) ) : ?>
					<h4><?php esc_html_e( 'Only in Git', 'repo-code-snippets' ); ?></h4>
					<p><?php echo esc_html( implode( ', ', $diff['only_remote'] ) ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $diff['identical'] ) ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %d: count */
							esc_html__( '%d snippet(s) identical.', 'repo-code-snippets' ),
							count( $diff['identical'] )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'Save a Git repository URL to unlock Diff and Pull.', 'repo-code-snippets' ); ?></p>
	<?php endif; ?>

	<hr />
	<h2><?php esc_html_e( 'Expected Git folder layout', 'repo-code-snippets' ); ?></h2>
	<pre class="rcs-layout-sample">snippets/
  my-snippet/
    meta.json
    code.php
  site-styles/
    meta.json
    code.css</pre>
	<p class="description">
		<?php esc_html_e( 'meta.json fields: name, slug, type (php|css|js|html), status (active|inactive), scope (everywhere|frontend|admin), priority, description.', 'repo-code-snippets' ); ?>
	</p>
</div>
