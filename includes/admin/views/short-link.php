<?php
/**
 * One short link: its address with copy and QR code, where it goes, clicks per day for the last
 * 90 days, conversions from Conversion Bridge when it's active, and forms to change the ending or
 * where it goes, or delete it.
 *
 * The chart is plain SVG drawn here, one bar per day, so there's no chart script to load. Each
 * bar has a <title> for its date and count, and a sentence above the chart gives the totals for
 * screen readers.
 *
 * Variables: $short_link (a row from Sunshine_UTM_Short_Links::get()), $plugin, $admin.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included inside a method, so these variables are local to it.

$short       = Sunshine_UTM_Short_Links::url( $short_link['code'] );
$short_bare  = preg_replace( '#^https?://#', '', $short );
$prefix      = Sunshine_UTM_Short_Links::prefix();
$base        = preg_replace( '#^https?://#', '', untrailingslashit( home_url() ) ) . '/' . ( '' === $prefix ? '' : $prefix . '/' );
$daily       = Sunshine_UTM_Short_Links::daily( (int) $short_link['id'], 90 );
$most        = max( 1, max( $daily ) );
$period      = array_sum( $daily );
$converted   = Sunshine_UTM_Conversion_Bridge::conversions( $short_link );
$fields      = Sunshine_UTM_URL_Builder::fields();
$bar_width   = 8;
$chart_width = count( $daily ) * $bar_width;
?>
<p class="sunshine-utm-back">
	<a href="<?php echo esc_url( Sunshine_UTM_Admin::url( array( 'tab' => 'links' ) ) ); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><?php esc_html_e( 'All short links', 'sunshine-utm-builder' ); ?></a>
</p>

<div class="sunshine-utm-batch-head" data-sunshine-utm-qr-after>
	<div>
		<h2 class="sunshine-utm-batch-head__title"><?php echo esc_html( $short_bare ); ?></h2>
		<p class="sunshine-utm-batch-head__meta">
			<?php
			/* translators: %s: date. */
			echo esc_html( sprintf( __( 'Made %s', 'sunshine-utm-builder' ), Sunshine_UTM_Admin::date( $short_link['created_at'] ) ) );
			?>
		</p>
	</div>
	<div class="sunshine-utm-batch-head__actions">
		<div class="sunshine-utm-copy-row">
			<input type="text" readonly value="<?php echo esc_attr( $short ); ?>" aria-label="<?php esc_attr_e( 'Short link', 'sunshine-utm-builder' ); ?>">
			<button type="button" class="button button-primary" data-sunshine-utm-copy><?php esc_html_e( 'Copy', 'sunshine-utm-builder' ); ?></button>
			<button type="button" class="button" data-sunshine-utm-qr="<?php echo esc_attr( $short ); ?>"><?php esc_html_e( 'QR code', 'sunshine-utm-builder' ); ?></button>
		</div>
	</div>
</div>
<p class="screen-reader-text" role="status" aria-live="polite" data-sunshine-utm-announce></p>

<section class="wps-panel">
	<div class="wps-panel__body">
		<ul class="sunshine-utm-stats">
			<li><span class="sunshine-utm-stats__number"><?php echo esc_html( number_format_i18n( (int) $short_link['clicks'] ) ); ?></span> <?php esc_html_e( 'clicks', 'sunshine-utm-builder' ); ?></li>
			<li><span class="sunshine-utm-stats__number"><?php echo esc_html( number_format_i18n( (int) $short_link['recent'] ) ); ?></span> <?php esc_html_e( 'in the last 30 days', 'sunshine-utm-builder' ); ?></li>
			<?php if ( null !== $converted ) : ?>
				<li><span class="sunshine-utm-stats__number"><?php echo esc_html( number_format_i18n( $converted ) ); ?></span> <?php esc_html_e( 'conversions', 'sunshine-utm-builder' ); ?></li>
			<?php endif; ?>
			<li>
				<span class="sunshine-utm-stats__number sunshine-utm-stats__number--date"><?php echo $short_link['last_click_at'] ? esc_html( Sunshine_UTM_Admin::date( $short_link['last_click_at'] ) ) : '&mdash;'; ?></span>
				<?php esc_html_e( 'last click', 'sunshine-utm-builder' ); ?>
			</li>
		</ul>

		<h3 class="sunshine-utm-chart__title"><?php esc_html_e( 'Clicks per day', 'sunshine-utm-builder' ); ?></h3>
		<p class="screen-reader-text">
			<?php
			/* translators: 1: clicks, 2: the most on one day. */
			echo esc_html( sprintf( __( '%1$s clicks in the last 90 days. The most on one day was %2$s.', 'sunshine-utm-builder' ), number_format_i18n( $period ), number_format_i18n( max( $daily ) ) ) );
			?>
		</p>
		<div class="sunshine-utm-chart">
			<svg viewBox="0 0 <?php echo (int) $chart_width; ?> 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
				<?php
				$x = 0;
				foreach ( $daily as $day => $count ) :
					$height = $count ? max( 2, round( 96 * $count / $most ) ) : 0;
					?>
					<rect x="<?php echo (int) $x + 1; ?>" y="<?php echo (int) ( 100 - $height ); ?>" width="<?php echo (int) $bar_width - 2; ?>" height="<?php echo (int) $height; ?>">
						<title>
							<?php
							/* translators: 1: date, 2: number of clicks. */
							echo esc_html( sprintf( _n( '%1$s: %2$s click', '%1$s: %2$s clicks', $count, 'sunshine-utm-builder' ), wp_date( get_option( 'date_format' ), strtotime( $day . ' 12:00:00 UTC' ) ), number_format_i18n( $count ) ) );
							?>
						</title>
					</rect>
					<?php
					$x += $bar_width;
				endforeach;
				?>
			</svg>
			<div class="sunshine-utm-chart__axis">
				<span><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( array_key_first( $daily ) . ' 12:00:00 UTC' ) ) ); ?></span>
				<span>
					<?php
					/* translators: %s: the most clicks on one day. */
					echo esc_html( sprintf( __( 'Most in a day: %s', 'sunshine-utm-builder' ), number_format_i18n( max( $daily ) ) ) );
					?>
				</span>
				<span><?php esc_html_e( 'Today', 'sunshine-utm-builder' ); ?></span>
			</div>
		</div>
		<?php if ( null !== $converted ) : ?>
			<p class="description">
				<?php
				/* translators: %s: Conversion Bridge, or the name it's white labeled with. */
				echo esc_html( sprintf( __( 'Conversions come from %s: sales, sign-ups and other conversions by visitors who arrived with this link\'s tags since it was made. Links with the same tags count together.', 'sunshine-utm-builder' ), Sunshine_UTM_Conversion_Bridge::name() ) );
				?>
			</p>
		<?php endif; ?>
	</div>
</section>

<?php Sunshine_UTM_Admin::panel_open( 'short-link-edit', __( 'Edit', 'sunshine-utm-builder' ) ); ?>
	<?php Sunshine_UTM_Admin::form_open( 'update_short_link' ); ?>
		<input type="hidden" name="code" value="<?php echo esc_attr( $short_link['code'] ); ?>">

		<?php Sunshine_UTM_Admin::option_row_open( __( 'Ending', 'sunshine-utm-builder' ), 'sunshine-utm-new-code' ); ?>
			<div class="sunshine-utm-ending">
				<span class="sunshine-utm-ending__base"><?php echo esc_html( $base ); ?></span>
				<input type="text" id="sunshine-utm-new-code" name="new_code" value="<?php echo esc_attr( $short_link['code'] ); ?>" maxlength="50" spellcheck="false" autocomplete="off">
			</div>
		<?php Sunshine_UTM_Admin::option_row_close( __( 'Make it something people can read and type, like spring-sale. Lowercase letters, numbers and dashes. If you change it, the old ending stops working, so only change it before the link is shared.', 'sunshine-utm-builder' ) . ' ' . Sunshine_UTM_Admin::doc_link( 'short-links-on-your-own-domain' ) ); ?>

		<?php Sunshine_UTM_Admin::option_row_open( __( 'Goes to', 'sunshine-utm-builder' ), 'sunshine-utm-new-url' ); ?>
			<input type="url" id="sunshine-utm-new-url" name="url" value="<?php echo esc_attr( $short_link['url'] ); ?>" class="large-text code" spellcheck="false">
		<?php Sunshine_UTM_Admin::option_row_close( __( 'Change this when the page moves or a promotion ends. The short link keeps working, even on things already printed. Build the new link with its tags in the Link Builder and paste it here.', 'sunshine-utm-builder' ) ); ?>

		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'sunshine-utm-builder' ); ?></button></p>
	</form>

	<?php if ( array_filter( array_intersect_key( $short_link, $fields ) ) ) : ?>
		<dl class="sunshine-utm-tags">
			<?php foreach ( $fields as $key => $field ) : ?>
				<?php if ( '' !== (string) $short_link[ $key ] ) : ?>
					<div><dt><?php echo esc_html( $field['label'] ); ?></dt><dd><?php echo esc_html( $short_link[ $key ] ); ?></dd></div>
				<?php endif; ?>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>
<?php Sunshine_UTM_Admin::panel_close(); ?>

<div class="sunshine-utm-danger">
	<?php Sunshine_UTM_Admin::form_open( 'delete_short_link', 'sunshine-utm-inline-form' ); ?>
		<input type="hidden" name="code" value="<?php echo esc_attr( $short_link['code'] ); ?>">
		<?php /* translators: %s: short link, like example.com/x8d89c. */ ?>
		<button type="submit" class="button-link button-link-delete" data-sunshine-utm-confirm="<?php echo esc_attr( sprintf( __( 'Delete %s? Anyone who clicks it after this gets a "page not found" error, and its clicks are deleted too.', 'sunshine-utm-builder' ), $short_bare ) ); ?>"><?php esc_html_e( 'Delete this short link', 'sunshine-utm-builder' ); ?></button>
	</form>
</div>
