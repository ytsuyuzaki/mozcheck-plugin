<?php
/**
 * HTML email generation and delivery.
 *
 * @package Mozcheck
 */

/**
 * Sends reports through wp_mail().
 */
final class Mozcheck_Mailer {
	/**
	 * Send a report individually to each recipient.
	 *
	 * @param array<string, mixed> $report Filtered report.
	 * @param string[]             $recipients Recipient addresses.
	 * @return array<string, mixed>
	 */
	public function send( array $report, array $recipients ): array {
		$subject = $this->subject( $report );
		$body    = $this->body( $report );
		$sent    = array();
		$failed  = array();

		add_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		try {
			foreach ( $recipients as $recipient ) {
				if ( wp_mail( $recipient, $subject, $body ) ) {
					$sent[] = $recipient;
				} else {
					$failed[] = $recipient;
				}
			}
		} finally {
			remove_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		}

		return array(
			'status' => empty( $failed ) ? 'success' : ( empty( $sent ) ? 'failed' : 'partial' ),
			'sent'   => $sent,
			'failed' => $failed,
		);
	}

	/**
	 * HTML content type callback.
	 *
	 * @return string
	 */
	public function html_content_type(): string {
		return 'text/html';
	}

	/**
	 * Build a localized subject.
	 *
	 * @param array<string, mixed> $report Report.
	 * @return string
	 */
	public function subject( array $report ): string {
		$counts = $report['counts'];
		if ( 0 === $counts['critical'] && 0 === $counts['recommended'] ) {
			/* translators: %s: site name. */
			return sprintf( __( '[MozCheck] %s: Site Health is good', 'mozcheck' ), $report['site_name'] );
		}
		return sprintf(
			/* translators: 1: site name, 2: critical count, 3: recommended count. */
			__( '[MozCheck] %1$s: %2$d critical, %3$d recommended', 'mozcheck' ),
			$report['site_name'],
			$counts['critical'],
			$counts['recommended']
		);
	}

	/**
	 * Build the HTML report.
	 *
	 * @param array<string, mixed> $report Report.
	 * @return string
	 */
	public function body( array $report ): string {
		$counts     = $report['counts'];
		$categories = Mozcheck_Settings::categories();
		$statuses   = array(
			'critical'    => __( 'Critical', 'mozcheck' ),
			'recommended' => __( 'Recommended', 'mozcheck' ),
			'good'        => __( 'Good', 'mozcheck' ),
		);
		$health_url = admin_url( 'site-health.php' );
		$problems   = array_filter( $report['results'], static fn( $result ) => 'good' !== $result['status'] );
		ob_start();
		?>
<!doctype html>
<html>
<body style="margin:0;background:#f0f0f1;color:#1d2327;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
	<div style="max-width:680px;margin:0 auto;padding:24px">
		<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:24px">
			<h1 style="margin-top:0;font-size:24px"><?php /* translators: %s: site name. */ echo esc_html( sprintf( __( 'Site Health report for %s', 'mozcheck' ), $report['site_name'] ) ); ?></h1>
			<p><a href="<?php echo esc_url( $report['site_url'] ); ?>"><?php echo esc_html( $report['site_url'] ); ?></a><br><?php echo esc_html( get_date_from_gmt( $report['generated_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></p>
			<table role="presentation" style="width:100%;border-collapse:collapse;margin:20px 0;text-align:center">
				<tr>
					<td style="padding:14px;border:1px solid #d63638"><strong style="font-size:24px;color:#b32d2e"><?php echo esc_html( (string) $counts['critical'] ); ?></strong><br><?php esc_html_e( 'Critical', 'mozcheck' ); ?></td>
					<td style="padding:14px;border:1px solid #dba617"><strong style="font-size:24px;color:#996800"><?php echo esc_html( (string) $counts['recommended'] ); ?></strong><br><?php esc_html_e( 'Recommended', 'mozcheck' ); ?></td>
					<td style="padding:14px;border:1px solid #00a32a"><strong style="font-size:24px;color:#008a20"><?php echo esc_html( (string) $counts['good'] ); ?></strong><br><?php esc_html_e( 'Good', 'mozcheck' ); ?></td>
				</tr>
			</table>
			<?php if ( empty( $problems ) ) : ?>
				<p><?php esc_html_e( 'No problems were found in the selected categories.', 'mozcheck' ); ?></p>
			<?php else : ?>
				<h2 style="font-size:19px"><?php esc_html_e( 'Items to review', 'mozcheck' ); ?></h2>
				<?php foreach ( $problems as $result ) : ?>
					<div style="border-left:4px solid <?php echo 'critical' === $result['status'] ? '#d63638' : '#dba617'; ?>;padding:4px 16px;margin:18px 0">
						<p style="margin:0 0 6px;color:#646970"><?php echo esc_html( $categories[ $result['category'] ] ?? $categories['other'] ); ?> · <?php echo esc_html( $statuses[ $result['status'] ] ?? $result['status'] ); ?></p>
						<h3 style="margin:0 0 8px;font-size:16px"><?php echo esc_html( $result['label'] ); ?></h3>
						<div><?php echo wp_kses_post( $result['description'] ); ?></div>
						<div><?php echo wp_kses_post( $result['actions'] ); ?></div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
			<p style="margin:24px 0 0"><a href="<?php echo esc_url( $health_url ); ?>" style="display:inline-block;background:#2271b1;color:#fff;text-decoration:none;padding:10px 16px;border-radius:3px"><?php esc_html_e( 'Open Site Health', 'mozcheck' ); ?></a></p>
		</div>
		<p style="color:#646970;font-size:12px;text-align:center"><?php esc_html_e( 'This email was sent by the MozCheck WordPress plugin.', 'mozcheck' ); ?></p>
	</div>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}
}
