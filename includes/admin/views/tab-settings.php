<?php
/**
 * Settings tab.
 *
 * Variables: $plugin, $admin.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included inside a method, so these variables are local to it.

$settings   = $plugin->get_settings();
$site_host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
$post_types = get_post_types( array( 'public' => true ), 'objects' );
unset( $post_types['attachment'] );
?>
<div class="wps-layout">
	<div class="wps-layout__main">
		<?php Sunshine_UTM_Admin::form_open( 'save_settings', 'sunshine-utm-settings' ); ?>

			<?php Sunshine_UTM_Admin::panel_open( 'shortcuts', __( 'Shortcuts', 'sunshine-utm-builder' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Admin bar', 'sunshine-utm-builder' ) ); ?>
					<?php Sunshine_UTM_Admin::checkbox_field( 'admin_bar', (bool) $settings['admin_bar'], __( 'Show "UTM link" in the admin bar on your site', 'sunshine-utm-builder' ) ); ?>
				<?php Sunshine_UTM_Admin::option_row_close( __( 'Opens the Link Builder with the page you\'re looking at already filled in.', 'sunshine-utm-builder' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Post lists', 'sunshine-utm-builder' ) ); ?>
					<?php Sunshine_UTM_Admin::checkbox_field( 'row_actions', (bool) $settings['row_actions'], __( 'Show "UTM link" under each published post', 'sunshine-utm-builder' ) ); ?>
				<?php Sunshine_UTM_Admin::option_row_close( __( 'In Posts, Pages and the other lists you choose below.', 'sunshine-utm-builder' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Editor', 'sunshine-utm-builder' ) ); ?>
					<?php Sunshine_UTM_Admin::checkbox_field( 'editor_panel', (bool) $settings['editor_panel'], __( 'Show a "UTM link" panel when editing a post', 'sunshine-utm-builder' ) ); ?>
				<?php Sunshine_UTM_Admin::option_row_close( __( 'In the block editor\'s sidebar, with a button to build a link to the post and its short links with their clicks.', 'sunshine-utm-builder' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Post types', 'sunshine-utm-builder' ) ); ?>
					<fieldset class="sunshine-utm-checks">
						<legend class="screen-reader-text"><?php esc_html_e( 'Post types', 'sunshine-utm-builder' ); ?></legend>
						<?php foreach ( $post_types as $public_type ) : ?>
							<label>
								<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $public_type->name ); ?>" <?php checked( in_array( $public_type->name, (array) $settings['post_types'], true ) ); ?>>
								<?php echo esc_html( $public_type->labels->name ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php Sunshine_UTM_Admin::option_row_close( __( 'Where the shortcuts show. With none chosen, the admin bar link shows on every page of your site, and the post list link and editor panel are hidden.', 'sunshine-utm-builder' ) . ' ' . Sunshine_UTM_Admin::doc_link( 'utm-builder-shortcuts' ) ); ?>

			<?php Sunshine_UTM_Admin::panel_close(); ?>

			<?php
			Sunshine_UTM_Admin::panel_open(
				'formatting',
				__( 'Tag formatting', 'sunshine-utm-builder' ),
				array( 'intro' => __( 'Google Analytics treats Email, email and e-mail as three different mediums. These rules clean up tags as you type them so the same thing is always spelled the same way. They apply to the five main tags, not to other parameters.', 'sunshine-utm-builder' ) . ' ' . Sunshine_UTM_Admin::doc_link( 'utm-tag-formatting-rules' ) )
			);
			?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Lowercase', 'sunshine-utm-builder' ) ); ?>
					<?php Sunshine_UTM_Admin::checkbox_field( 'lowercase', (bool) $settings['lowercase'], __( 'Make every tag lowercase', 'sunshine-utm-builder' ) ); ?>
				<?php Sunshine_UTM_Admin::option_row_close(); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Spaces', 'sunshine-utm-builder' ), 'sunshine-utm-spaces' ); ?>
					<select id="sunshine-utm-spaces" name="spaces">
						<option value="_" <?php selected( '_', $settings['spaces'] ); ?>><?php esc_html_e( 'Change to underscores (spring_sale)', 'sunshine-utm-builder' ); ?></option>
						<option value="-" <?php selected( '-', $settings['spaces'] ); ?>><?php esc_html_e( 'Change to dashes (spring-sale)', 'sunshine-utm-builder' ); ?></option>
						<?php /* translators: "spring%20sale" is an example; %20 is how a link writes a space, not a placeholder. */ ?>
						<option value="keep" <?php selected( 'keep', $settings['spaces'] ); ?>><?php esc_html_e( 'Leave them (spring%20sale)', 'sunshine-utm-builder' ); ?></option>
					</select>
				<?php Sunshine_UTM_Admin::option_row_close(); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Symbols', 'sunshine-utm-builder' ) ); ?>
					<?php Sunshine_UTM_Admin::checkbox_field( 'strip_symbols', (bool) $settings['strip_symbols'], __( 'Remove symbols like ! ? & and quotes', 'sunshine-utm-builder' ) ); ?>
				<?php Sunshine_UTM_Admin::option_row_close( __( 'Keeps letters, numbers, dashes, underscores and periods.', 'sunshine-utm-builder' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Try it', 'sunshine-utm-builder' ), 'sunshine-utm-format-try' ); ?>
					<input type="text" id="sunshine-utm-format-try" class="regular-text" value="Spring Sale & Clearance!" data-sunshine-utm-format-try>
					<p class="sunshine-utm-format-result"><?php esc_html_e( 'Becomes:', 'sunshine-utm-builder' ); ?> <code data-sunshine-utm-format-result><?php echo esc_html( Sunshine_UTM_URL_Builder::format_value( 'Spring Sale & Clearance!', $plugin->format_options() ) ); ?></code></p>
				<?php Sunshine_UTM_Admin::option_row_close(); ?>

			<?php Sunshine_UTM_Admin::panel_close(); ?>

			<?php
			Sunshine_UTM_Admin::panel_open(
				'lists',
				__( 'Team lists', 'sunshine-utm-builder' ),
				array( 'intro' => __( 'When one person types facebook, another fb and another Facebook, Google Analytics splits the visits three ways. List the sources and mediums your team uses and the builder offers only those.', 'sunshine-utm-builder' ) . ' ' . Sunshine_UTM_Admin::doc_link( 'utm-builder-team-lists' ) )
			);
			?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Sources', 'sunshine-utm-builder' ), 'sunshine-utm-allowed-sources' ); ?>
					<textarea id="sunshine-utm-allowed-sources" name="allowed_sources" rows="5" class="regular-text code" spellcheck="false"><?php echo esc_textarea( implode( "\n", (array) $settings['allowed_sources'] ) ); ?></textarea>
				<?php Sunshine_UTM_Admin::option_row_close( __( 'One per line, like facebook or newsletter. Leave empty to allow any.', 'sunshine-utm-builder' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Mediums', 'sunshine-utm-builder' ), 'sunshine-utm-allowed-mediums' ); ?>
					<textarea id="sunshine-utm-allowed-mediums" name="allowed_mediums" rows="5" class="regular-text code" spellcheck="false"><?php echo esc_textarea( implode( "\n", (array) $settings['allowed_mediums'] ) ); ?></textarea>
				<?php Sunshine_UTM_Admin::option_row_close( __( 'One per line, like social or email. Leave empty to allow any.', 'sunshine-utm-builder' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Enforce', 'sunshine-utm-builder' ) ); ?>
					<?php Sunshine_UTM_Admin::checkbox_field( 'allowed_only', (bool) $settings['allowed_only'], __( 'Only allow these', 'sunshine-utm-builder' ) ); ?>
				<?php Sunshine_UTM_Admin::option_row_close( __( 'Off: the lists are offered as you type, and anything else still works. On: links with a source or medium that isn\'t listed aren\'t made, in the builder, the Bulk Builder, the API and the AI tools.', 'sunshine-utm-builder' ) ); ?>

			<?php Sunshine_UTM_Admin::panel_close(); ?>

			<?php Sunshine_UTM_Admin::panel_open( 'checks', __( 'Link checks', 'sunshine-utm-builder' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Check pages', 'sunshine-utm-builder' ) ); ?>
					<?php Sunshine_UTM_Admin::checkbox_field( 'check_urls', (bool) $settings['check_urls'], __( 'Check that each page loads before a batch is saved', 'sunshine-utm-builder' ) ); ?>
				<?php
				Sunshine_UTM_Admin::option_row_close(
					sprintf(
						/* translators: %s: the most pages that can be checked at once. */
						__( 'Catches typos and deleted pages before the links go out. Links to pages that don\'t load are marked in the batch. Creating a batch takes a little longer, and checks run on batches going to up to %s different pages.', 'sunshine-utm-builder' ),
						number_format_i18n( Sunshine_UTM_Bulk::CHECK_MAX )
					) . ' ' . Sunshine_UTM_Admin::doc_link( 'utm-builder-link-checks' )
				);
				?>

			<?php Sunshine_UTM_Admin::panel_close(); ?>

			<?php
			Sunshine_UTM_Admin::panel_open(
				'shortening',
				__( 'Link shortening', 'sunshine-utm-builder' ),
				array( 'intro' => __( 'Short links are easier to share where long ones look messy, like social posts and text messages. Set up as many of these as you like, and choose one each time you shorten a link.', 'sunshine-utm-builder' ) . ' ' . Sunshine_UTM_Admin::doc_link( 'short-links-on-your-own-domain' ) )
			);
			?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Your site', 'sunshine-utm-builder' ) ); ?>
					<?php
					/* translators: %s: this site's domain. */
					Sunshine_UTM_Admin::checkbox_field( 'site_links', (bool) $settings['site_links'], sprintf( __( 'Make short links on %s', 'sunshine-utm-builder' ), $site_host ) );
					?>
					<?php if ( ! Sunshine_UTM_Short_Links::available() ) : ?>
						<div class="sunshine-utm-alert sunshine-utm-alert--error">
							<p>
								<?php
								printf(
									/* translators: %s: link to the Permalinks settings. */
									esc_html__( 'Short links on your site need pretty permalinks. Choose any setting other than Plain, without index.php, under %s.', 'sunshine-utm-builder' ),
									'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Settings → Permalinks', 'sunshine-utm-builder' ) . '</a>'
								);
								echo ' ' . Sunshine_UTM_Admin::doc_link( 'short-link-page-not-found' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in doc_link().
								?>
							</p>
						</div>
					<?php endif; ?>
				<?php Sunshine_UTM_Admin::option_row_close( __( 'No account needed. The same long link always gets the same short link.', 'sunshine-utm-builder' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Short link address', 'sunshine-utm-builder' ), 'sunshine-utm-short-prefix' ); ?>
					<span class="sunshine-utm-prefix">
						<span class="sunshine-utm-prefix__home"><?php echo esc_html( untrailingslashit( preg_replace( '#^https?://#', '', home_url() ) ) ); ?>/</span>
						<input type="text" id="sunshine-utm-short-prefix" name="short_prefix" class="small-text" maxlength="20" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e( 'optional', 'sunshine-utm-builder' ); ?>" value="<?php echo esc_attr( Sunshine_UTM_Short_Links::prefix() ); ?>" data-sunshine-utm-prefix>
					</span>
					<p class="sunshine-utm-format-result"><?php esc_html_e( 'Your links will look like:', 'sunshine-utm-builder' ); ?> <code data-sunshine-utm-prefix-example data-home="<?php echo esc_attr( untrailingslashit( home_url() ) ); ?>"><?php echo esc_html( Sunshine_UTM_Short_Links::url( 'x8d89c' ) ); ?></code></p>
				<?php
				Sunshine_UTM_Admin::option_row_close(
					__( 'Leave it empty for the shortest links. A real page always wins over a short link at the same address. With a word here (like g), short links are answered before WordPress looks for a page, which is a little faster. Links you\'ve already shared keep working if you change it later.', 'sunshine-utm-builder' )
				);
				?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Bitly access token', 'sunshine-utm-builder' ), 'sunshine-utm-bitly-token' ); ?>
					<input type="password" id="sunshine-utm-bitly-token" name="bitly_token" class="regular-text code" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $settings['bitly_token'] ); ?>">
				<?php Sunshine_UTM_Admin::option_row_close( __( 'To shorten with Bitly. In Bitly, open Settings, then Developer settings, then API, and generate a token. Leave empty if you don\'t use Bitly.', 'sunshine-utm-builder' ) . ' ' . Sunshine_UTM_Admin::doc_link( 'shorten-utm-links-bitly-rebrandly' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Rebrandly API key', 'sunshine-utm-builder' ), 'sunshine-utm-rebrandly-key' ); ?>
					<input type="password" id="sunshine-utm-rebrandly-key" name="rebrandly_key" class="regular-text code" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $settings['rebrandly_key'] ); ?>">
				<?php Sunshine_UTM_Admin::option_row_close( __( 'To shorten with Rebrandly. In Rebrandly, open your account settings, then API keys, and create a key. Leave empty if you don\'t use Rebrandly.', 'sunshine-utm-builder' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Default', 'sunshine-utm-builder' ), 'sunshine-utm-shortener' ); ?>
					<select id="sunshine-utm-shortener" name="shortener">
						<?php foreach ( Sunshine_UTM_Shortener::services() as $service => $name ) : ?>
							<option value="<?php echo esc_attr( $service ); ?>" <?php selected( $service, $settings['shortener'] ); ?>><?php echo esc_html( 'site' === $service ? sprintf( '%s (%s)', $name, $site_host ) : $name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php Sunshine_UTM_Admin::option_row_close( __( 'Used by the AI tools and the API when they don\'t pick one. If it isn\'t set up, the first one that is gets used.', 'sunshine-utm-builder' ) ); ?>

			<?php Sunshine_UTM_Admin::panel_close(); ?>

			<?php Sunshine_UTM_Admin::panel_open( 'data', __( 'Your data', 'sunshine-utm-builder' ) ); ?>

				<?php Sunshine_UTM_Admin::option_row_open( __( 'Uninstall', 'sunshine-utm-builder' ) ); ?>
					<?php Sunshine_UTM_Admin::checkbox_field( 'delete_data', (bool) $settings['delete_data'], __( 'Delete presets, batches, short links and settings when the plugin is deleted', 'sunshine-utm-builder' ) ); ?>
				<?php Sunshine_UTM_Admin::option_row_close( __( 'Leave this off if you might reinstall later. Short links on your own domain stop working once they\'re deleted, and deleted data can\'t be brought back.', 'sunshine-utm-builder' ) . ' ' . Sunshine_UTM_Admin::doc_link( 'utm-builder-data-and-uninstall' ) ); ?>

			<?php Sunshine_UTM_Admin::panel_close(); ?>

			<div class="wps-save-bar">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'sunshine-utm-builder' ); ?></button>
			</div>
		</form>
	</div>

	<div class="wps-layout__side">
		<?php include SUNSHINE_UTM_DIR . 'includes/admin/views/sidebar.php'; ?>
	</div>
</div>
