<?php
/**
 * The site-wide audit report screen.
 *
 * Available here, from Open_Accessibility_Report::display_report_page():
 *
 * @var array $progress Scan progress, already defaults-filled.
 * @var array $totals   Counts by severity, plus posts scanned and with issues.
 * @var int   $page     Current page number.
 * @var array $rows     Page of report rows, worst first.
 *
 * @since    1.4.2
 * @package  Open_Accessibility
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$scanning   = ! empty( $progress['running'] );
$has_run    = Open_Accessibility_Report::has_scanned( $progress, $totals );
$categories = Open_Accessibility_Audit::unchecked_categories();
$total_rows = Open_Accessibility_Scanner::count_posts_with_findings();
$page_count = $total_rows > 0 ? (int) ceil( $total_rows / Open_Accessibility_Report::PER_PAGE ) : 1;
?>
<div class="wrap oa-report">
	<h1><?php esc_html_e( 'Accessibility Report', 'open-accessibility' ); ?></h1>

	<?php if ( isset( $_GET['oa_scanned'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Scan complete.', 'open-accessibility' ); ?></p>
		</div>
	<?php endif; ?>

	<p class="oa-report__intro">
		<?php esc_html_e( 'Checks the saved content of your posts, pages and other public post types against a fixed set of rules. It reads what is stored, so it reports what a visitor would be served.', 'open-accessibility' ); ?>
	</p>

	<?php /* Progress. aria-live so the change is announced rather than only shown. */ ?>
	<div class="oa-report__progress" id="oa-report-progress" role="status" aria-live="polite" data-running="<?php echo $scanning ? 'true' : 'false'; ?>">
		<?php if ( $scanning ) : ?>
			<p class="oa-report__progress-text">
				<?php
				printf(
					/* translators: 1: posts scanned, 2: total posts. */
					esc_html__( '%1$s of %2$s posts scanned', 'open-accessibility' ),
					esc_html( number_format_i18n( (int) $progress['scanned'] ) ),
					esc_html( number_format_i18n( (int) $progress['total'] ) )
				);
				?>
			</p>
			<div class="oa-report__bar">
				<span
					class="oa-report__bar-fill"
					style="width:<?php echo esc_attr( Open_Accessibility_Report::percent( $progress ) ); ?>%"
				></span>
			</div>
		<?php endif; ?>
	</div>

	<p class="oa-report__actions">
		<button
			type="button"
			class="button button-primary"
			id="oa-report-scan"
			data-force="<?php echo $has_run ? 'true' : 'false'; ?>"
		>
			<?php echo $has_run ? esc_html__( 'Rescan site', 'open-accessibility' ) : esc_html__( 'Scan site', 'open-accessibility' ); ?>
		</button>
		<span class="oa-report__action-note">
			<?php esc_html_e( 'A scan runs in the background, in batches, so it can cover a large site without timing out.', 'open-accessibility' ); ?>
		</span>
	</p>

	<?php if ( ! $has_run ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'No scan has run yet. The results below are empty because nothing has been checked, not because nothing needs fixing.', 'open-accessibility' ); ?></p>
		</div>
	<?php endif; ?>

	<?php /* Totals. Each figure is labelled; none relies on colour alone. */ ?>
	<h2 class="screen-reader-text"><?php esc_html_e( 'Summary', 'open-accessibility' ); ?></h2>
	<ul class="oa-report__totals">
		<li>
			<span class="oa-report__figure"><?php echo esc_html( number_format_i18n( (int) $totals['posts_scanned'] ) ); ?></span>
			<span class="oa-report__label"><?php esc_html_e( 'Posts scanned', 'open-accessibility' ); ?></span>
		</li>
		<li>
			<span class="oa-report__figure"><?php echo esc_html( number_format_i18n( (int) $totals['posts_with_issues'] ) ); ?></span>
			<span class="oa-report__label"><?php esc_html_e( 'Posts with issues', 'open-accessibility' ); ?></span>
		</li>
		<li>
			<span class="oa-report__figure oa-report__figure--error"><?php echo esc_html( number_format_i18n( (int) $totals['error'] ) ); ?></span>
			<span class="oa-report__label"><?php esc_html_e( 'Errors', 'open-accessibility' ); ?></span>
		</li>
		<li>
			<span class="oa-report__figure oa-report__figure--warning"><?php echo esc_html( number_format_i18n( (int) $totals['warning'] ) ); ?></span>
			<span class="oa-report__label"><?php esc_html_e( 'Warnings', 'open-accessibility' ); ?></span>
		</li>
		<li>
			<span class="oa-report__figure oa-report__figure--review"><?php echo esc_html( number_format_i18n( (int) $totals['review'] ) ); ?></span>
			<span class="oa-report__label"><?php esc_html_e( 'Needs review', 'open-accessibility' ); ?></span>
		</li>
	</ul>

	<?php /* Rows. */ ?>
	<h2><?php esc_html_e( 'Posts with issues', 'open-accessibility' ); ?></h2>

	<?php if ( empty( $rows ) ) : ?>
		<p>
			<?php if ( $page > $page_count ) : ?>
				<?php
				/*
				 * The reader asked for a page past the end. Saying "nothing
				 * found" here would read as a clean site, which is the one
				 * thing this report must never claim by accident: the site may
				 * well have findings, just not on this page.
				 */
				printf(
					/* translators: %s: the last page number. */
					esc_html__( 'That page is past the end of the report. The last page is %s.', 'open-accessibility' ),
					esc_html( number_format_i18n( $page_count ) )
				);
				?>
			<?php elseif ( $has_run ) : ?>
				<?php esc_html_e( 'Nothing found. Every scanned post passed the rules below.', 'open-accessibility' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Run a scan to populate this report.', 'open-accessibility' ); ?>
			<?php endif; ?>
		</p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped oa-report__table">
			<caption class="screen-reader-text">
				<?php esc_html_e( 'Posts with accessibility findings, most findings first.', 'open-accessibility' ); ?>
			</caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Post', 'open-accessibility' ); ?></th>
					<th scope="col" class="oa-report__col-count"><?php esc_html_e( 'Findings', 'open-accessibility' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Breakdown', 'open-accessibility' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'open-accessibility' ); ?></th>
				</tr>
			</thead>
			<tbody id="oa-report-rows">
				<?php foreach ( $rows as $row ) : ?>
					<?php $post = get_post( $row['post_id'] ); ?>
					<?php if ( ! $post ) { continue; } ?>
					<tr data-post-id="<?php echo esc_attr( $row['post_id'] ); ?>">
						<td>
							<strong><a href="<?php echo esc_url( (string) get_edit_post_link( $row['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></strong>
							<span class="oa-report__type"><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?></span>
						</td>
						<td class="oa-report__col-count">
							<span class="oa-report__count"><?php echo esc_html( number_format_i18n( (int) $row['count'] ) ); ?></span>
						</td>
						<td>
							<?php
							$parts = array();

							foreach ( array( 'error', 'warning', 'review' ) as $severity ) {
								$value = isset( $row['summary'][ $severity ] ) ? (int) $row['summary'][ $severity ] : 0;

								if ( $value > 0 ) {
									$parts[] = sprintf(
										'%1$s: %2$s',
										Open_Accessibility_Report::severity_label( $severity ),
										number_format_i18n( $value )
									);
								}
							}

							echo esc_html( implode( ', ', $parts ) );
							?>
							<details class="oa-report__details">
								<summary><?php esc_html_e( 'Show findings', 'open-accessibility' ); ?></summary>
								<ul class="oa-report__findings">
									<?php foreach ( Open_Accessibility_Report::describe_findings( $row['findings'] ) as $finding ) : ?>
										<li>
											<span class="oa-report__severity oa-report__severity--<?php echo esc_attr( $finding['severity'] ); ?>">
												<?php echo esc_html( Open_Accessibility_Report::severity_label( $finding['severity'] ) ); ?>
											</span>
											<?php echo esc_html( $finding['message'] ); ?>
											<?php if ( '' !== $finding['wcag'] ) : ?>
												<span class="oa-report__wcag">
													<?php
													printf(
														/* translators: %s: WCAG success criterion, e.g. 1.1.1. */
														esc_html__( 'WCAG %s', 'open-accessibility' ),
														esc_html( $finding['wcag'] )
													);
													?>
												</span>
											<?php endif; ?>
											<?php if ( '' !== $finding['block'] ) : ?>
												<code class="oa-report__block"><?php echo esc_html( $finding['block'] ); ?></code>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</details>
						</td>
						<td>
							<button
								type="button"
								class="button button-small oa-report__rescan"
								data-post-id="<?php echo esc_attr( $row['post_id'] ); ?>"
							>
								<?php esc_html_e( 'Rescan', 'open-accessibility' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $page_count > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $page,
								'total'     => $page_count,
								'prev_text' => __( '&laquo; Previous', 'open-accessibility' ),
								'next_text' => __( 'Next &raquo;', 'open-accessibility' ),
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php /* Scope. Stated on the report itself, not only in the editor panel. */ ?>
	<h2><?php esc_html_e( 'What this report does not check', 'open-accessibility' ); ?></h2>
	<p>
		<?php esc_html_e( 'A pass here is not a claim of conformance. These categories are outside what automated rules can decide, and each needs a person:', 'open-accessibility' ); ?>
	</p>
	<ul class="oa-report__unchecked">
		<?php foreach ( $categories as $category ) : ?>
			<li><?php echo esc_html( $category ); ?></li>
		<?php endforeach; ?>
	</ul>

	<h2><?php esc_html_e( 'Rules used', 'open-accessibility' ); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<caption class="screen-reader-text"><?php esc_html_e( 'The rules this report applies.', 'open-accessibility' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Severity', 'open-accessibility' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Finding', 'open-accessibility' ); ?></th>
				<th scope="col"><?php esc_html_e( 'WCAG', 'open-accessibility' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( Open_Accessibility_Audit::rule_definitions() as $definition ) : ?>
				<tr>
					<td>
						<span class="oa-report__severity oa-report__severity--<?php echo esc_attr( $definition['severity'] ); ?>">
							<?php echo esc_html( Open_Accessibility_Report::severity_label( $definition['severity'] ) ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $definition['message'] ); ?></td>
					<td><?php echo esc_html( $definition['wcag'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
