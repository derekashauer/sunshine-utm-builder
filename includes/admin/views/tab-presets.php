<?php
/**
 * Presets tab: add or edit a preset, and the list. ?preset= opens one for editing.
 *
 * Variables: $plugin, $admin.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included inside a method, so these variables are local to it.

$presets = Sunshine_UTM_Presets::get_all();
$fields  = Sunshine_UTM_URL_Builder::fields();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
$editing = isset( $_GET['preset'] ) ? Sunshine_UTM_Presets::get( absint( $_GET['preset'] ) ) : null;
$values  = $editing ? $editing : array_fill_keys( array_merge( array( 'name' ), Sunshine_UTM_URL_Builder::KEYS ), '' );
?>
<div class="wps-layout">
	<div class="wps-layout__main">

		<?php
		Sunshine_UTM_Admin::panel_open(
			'preset-form',
			$editing ? __( 'Edit preset', 'sunshine-utm-builder' ) : __( 'Add a preset', 'sunshine-utm-builder' ),
			array( 'intro' => $editing ? '' : __( 'A preset saves tags you use a lot, so every link for a campaign or channel is spelled the same way. Pick it in the Link Builder or Bulk Builder to fill in the tags.', 'sunshine-utm-builder' ) . ' ' . Sunshine_UTM_Admin::doc_link( 'utm-presets-and-tokens' ) )
		);
		Sunshine_UTM_Admin::form_open( 'save_preset' );
		?>
			<input type="hidden" name="preset_id" value="<?php echo esc_attr( $editing ? $editing['id'] : 0 ); ?>">

			<?php Sunshine_UTM_Admin::option_row_open( __( 'Name', 'sunshine-utm-builder' ), 'sunshine-utm-preset-name' ); ?>
				<input type="text" id="sunshine-utm-preset-name" name="name" class="regular-text" maxlength="191" required value="<?php echo esc_attr( $values['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Monthly newsletter', 'sunshine-utm-builder' ); ?>">
			<?php Sunshine_UTM_Admin::option_row_close(); ?>

			<?php foreach ( $fields as $key => $field ) : ?>
				<?php Sunshine_UTM_Admin::option_row_open( $field['label'], 'sunshine-utm-preset-' . $key ); ?>
					<input type="text" id="sunshine-utm-preset-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="regular-text" maxlength="255" autocomplete="off" spellcheck="false" value="<?php echo esc_attr( $values[ $key ] ); ?>" placeholder="<?php echo esc_attr( Sunshine_UTM_URL_Builder::placeholder( $field ) ); ?>">
				<?php Sunshine_UTM_Admin::option_row_close( '<code>' . esc_html( $key ) . '</code> ' . esc_html( $field['help'] ) ); ?>
			<?php endforeach; ?>

			<div class="wps-save-bar">
				<button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Update preset', 'sunshine-utm-builder' ) : esc_html__( 'Add preset', 'sunshine-utm-builder' ); ?></button>
				<?php if ( $editing ) : ?>
					<a href="<?php echo esc_url( Sunshine_UTM_Admin::url( array( 'tab' => 'presets' ) ) ); ?>"><?php esc_html_e( 'Cancel', 'sunshine-utm-builder' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
		<?php Sunshine_UTM_Admin::panel_close(); ?>

		<section class="wps-panel">
			<div class="wps-panel__head">
				<h2 class="wps-panel__title"><?php esc_html_e( 'Your presets', 'sunshine-utm-builder' ); ?></h2>
			</div>
			<?php if ( ! $presets ) : ?>
				<div class="wps-panel__body">
					<p class="sunshine-utm-empty"><?php esc_html_e( 'No presets yet.', 'sunshine-utm-builder' ); ?></p>
				</div>
			<?php else : ?>
				<table class="sunshine-utm-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Name', 'sunshine-utm-builder' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Tags', 'sunshine-utm-builder' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'sunshine-utm-builder' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $presets as $preset ) : ?>
							<tr<?php echo $editing && (int) $editing['id'] === (int) $preset['id'] ? ' class="is-editing"' : ''; ?>>
								<td><strong><?php echo esc_html( $preset['name'] ); ?></strong></td>
								<td>
									<dl class="sunshine-utm-tags">
										<?php foreach ( $fields as $key => $field ) : ?>
											<?php if ( '' !== $preset[ $key ] ) : ?>
												<div><dt><?php echo esc_html( $field['label'] ); ?></dt><dd><?php echo esc_html( $preset[ $key ] ); ?></dd></div>
											<?php endif; ?>
										<?php endforeach; ?>
									</dl>
								</td>
								<td class="sunshine-utm-table__actions">
									<a href="<?php echo esc_url( Sunshine_UTM_Admin::url( array(
'tab'    => 'presets',
'preset' => $preset['id']
) ) ); ?>" class="button">
										<?php esc_html_e( 'Edit', 'sunshine-utm-builder' ); ?><span class="screen-reader-text"> <?php echo esc_html( $preset['name'] ); ?></span>
									</a>
									<?php Sunshine_UTM_Admin::form_open( 'delete_preset', 'sunshine-utm-inline-form' ); ?>
										<input type="hidden" name="preset_id" value="<?php echo esc_attr( $preset['id'] ); ?>">
										<?php /* translators: %s: preset name. */ ?>
										<button type="submit" class="button-link button-link-delete" data-sunshine-utm-confirm="<?php echo esc_attr( sprintf( __( 'Delete the preset "%s"? Links already made with it don\'t change.', 'sunshine-utm-builder' ), $preset['name'] ) ); ?>">
											<?php esc_html_e( 'Delete', 'sunshine-utm-builder' ); ?><span class="screen-reader-text"> <?php echo esc_html( $preset['name'] ); ?></span>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

	</div>
	<div class="wps-layout__side">
		<div class="wps-card">
			<p class="wps-card__eyebrow"><?php esc_html_e( 'Tokens', 'sunshine-utm-builder' ); ?></p>
			<p><?php esc_html_e( 'Put these in any tag and they\'re filled in for each link:', 'sunshine-utm-builder' ); ?></p>
			<ul class="sunshine-utm-token-list">
				<?php foreach ( Sunshine_UTM_Presets::tokens() as $token => $what ) : ?>
					<li><code><?php echo esc_html( $token ); ?></code> <?php echo esc_html( $what ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<?php
				/* translators: %s: today's date, like 261009. */
				echo esc_html( sprintf( __( 'For example, a campaign of spring_{date_short} built today becomes spring_%s.', 'sunshine-utm-builder' ), wp_date( 'ymd' ) ) );
				?>
			</p>
		</div>
		<?php include SUNSHINE_UTM_DIR . 'includes/admin/views/sidebar.php'; ?>
	</div>
</div>
