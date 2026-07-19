<?php
/**
 * Site Health result collection.
 *
 * @package Mozcheck
 */

/**
 * Runs and normalizes WordPress Site Health tests.
 */
final class Mozcheck_Site_Health_Collector {
	/**
	 * Collect the current report.
	 *
	 * @return array<string, mixed>
	 */
	public function collect(): array {
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$health  = WP_Site_Health::get_instance();
		$tests   = WP_Site_Health::get_tests();
		$results = array();

		foreach ( $tests['direct'] ?? array() as $id => $test ) {
			if ( ! empty( $test['skip_cron'] ) ) {
				continue;
			}
			$callback = $test['test'] ?? null;
			if ( is_string( $callback ) ) {
				$method   = 'get_test_' . $callback;
				$callback = is_callable( array( $health, $method ) ) ? array( $health, $method ) : null;
			}
			$results[] = $this->run_callable( $callback, (string) $id, $test['label'] ?? '' );
		}

		foreach ( $tests['async'] ?? array() as $id => $test ) {
			if ( ! empty( $test['skip_cron'] ) || ( 'https_status' === $id && in_array( wp_get_environment_type(), array( 'development', 'local' ), true ) ) ) {
				continue;
			}
			if ( is_callable( $test['async_direct_test'] ?? null ) ) {
				$results[] = $this->run_callable( $test['async_direct_test'], (string) $id, $test['label'] ?? '' );
			} else {
				$results[] = $this->run_async( $test, (string) $id );
			}
		}

		$results = array_merge( $results, $this->collect_inventory() );

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url( '/' ),
			'results'      => $results,
		);
	}

	/**
	 * Run a callable test safely.
	 *
	 * @param mixed  $callback Test callback.
	 * @param string $id Test ID.
	 * @param string $fallback_label Fallback label.
	 * @return array<string, mixed>
	 */
	private function run_callable( $callback, string $id, string $fallback_label ): array {
		if ( ! is_callable( $callback ) ) {
			return $this->unavailable( $id, $fallback_label );
		}
		try {
			$result = apply_filters( 'site_status_test_result', call_user_func( $callback ) );
			return $this->normalize( $result, $id, $fallback_label );
		} catch ( Throwable $error ) {
			return $this->unavailable( $id, $fallback_label );
		}
	}

	/**
	 * Run an asynchronous test using the same transport as core.
	 *
	 * @param array<string, mixed> $test Test definition.
	 * @param string               $id Test ID.
	 * @return array<string, mixed>
	 */
	private function run_async( array $test, string $id ): array {
		if ( ! is_string( $test['test'] ?? null ) ) {
			return $this->unavailable( $id, $test['label'] ?? '' );
		}
		$args     = array( 'body' => array( '_wpnonce' => wp_create_nonce( ! empty( $test['has_rest'] ) ? 'wp_rest' : 'health-check-site-status' ) ) );
		$response = ! empty( $test['has_rest'] )
			? wp_remote_get( $test['test'], $args )
			: wp_remote_post( admin_url( 'admin-ajax.php' ), array_merge_recursive( $args, array( 'body' => array( 'action' => $test['test'] ) ) ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->unavailable( $id, $test['label'] ?? '' );
		}
		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $result ) ? $this->normalize( $result, $id, $test['label'] ?? '' ) : $this->unavailable( $id, $test['label'] ?? '' );
	}

	/**
	 * Normalize one result.
	 *
	 * @param mixed  $result Raw result.
	 * @param string $id Test ID.
	 * @param string $fallback_label Fallback label.
	 * @return array<string, mixed>
	 */
	private function normalize( $result, string $id, string $fallback_label ): array {
		if ( ! is_array( $result ) || ! in_array( $result['status'] ?? '', array( 'good', 'recommended', 'critical' ), true ) ) {
			return $this->unavailable( $id, $fallback_label );
		}
		return array(
			'id'          => $id,
			'category'    => $this->category_for( $id ),
			'status'      => $result['status'],
			'label'       => sanitize_text_field( $result['label'] ?? $fallback_label ),
			'description' => wp_kses_post( $result['description'] ?? '' ),
			'actions'     => wp_kses_post( $result['actions'] ?? '' ),
			'is_update'   => false,
		);
	}

	/**
	 * Return a fallback result.
	 *
	 * @param string $id Test ID.
	 * @param string $label Fallback label.
	 * @return array<string, mixed>
	 */
	private function unavailable( string $id, string $label ): array {
		return array(
			'id'          => $id,
			'category'    => $this->category_for( $id ),
			'status'      => 'recommended',
			'label'       => $label ? $label : __( 'A Site Health test is unavailable', 'mozcheck' ),
			'description' => __( 'WordPress could not complete this Site Health test during the scheduled check.', 'mozcheck' ),
			'actions'     => '',
			'is_update'   => false,
		);
	}

	/**
	 * Collect update and unused-item details.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_inventory(): array {
		$results = array();
		$core    = get_site_transient( 'update_core' );
		if ( ! empty( $core->updates ) && is_array( $core->updates ) ) {
			foreach ( $core->updates as $update ) {
				if ( isset( $update->response ) && 'upgrade' === $update->response ) {
					/* translators: %s: available WordPress version. */
					$description = sprintf( __( 'WordPress %s is available.', 'mozcheck' ), $update->current );
					$results[]   = $this->inventory_result( 'core_update', 'updates', __( 'A WordPress update is available', 'mozcheck' ), $description, true );
					break;
				}
			}
		}

		$plugins       = get_plugins();
		$plugin_update = get_site_transient( 'update_plugins' );
		foreach ( (array) ( $plugin_update->response ?? array() ) as $file => $update ) {
			$name = $plugins[ $file ]['Name'] ?? $file;
			/* translators: 1: plugin name, 2: available version. */
			$description = sprintf( __( '%1$s can be updated to version %2$s.', 'mozcheck' ), $name, $update->new_version ?? '' );
			$results[]   = $this->inventory_result( 'plugin_update_' . md5( $file ), 'updates', __( 'A plugin update is available', 'mozcheck' ), $description, true );
		}

		$theme_update = get_site_transient( 'update_themes' );
		$themes       = wp_get_themes();
		foreach ( (array) ( $theme_update->response ?? array() ) as $slug => $update ) {
			$name = isset( $themes[ $slug ] ) ? $themes[ $slug ]->get( 'Name' ) : $slug;
			/* translators: 1: theme name, 2: available version. */
			$description = sprintf( __( '%1$s can be updated to version %2$s.', 'mozcheck' ), $name, $update['new_version'] ?? '' );
			$results[]   = $this->inventory_result( 'theme_update_' . md5( $slug ), 'updates', __( 'A theme update is available', 'mozcheck' ), $description, true );
		}

		foreach ( $plugins as $file => $data ) {
			if ( ! is_plugin_active( $file ) && ! is_plugin_active_for_network( $file ) ) {
				/* translators: %s: plugin name. */
				$description = sprintf( __( '%s is inactive. Remove it if it is no longer needed.', 'mozcheck' ), $data['Name'] ?? $file );
				$results[]   = $this->inventory_result( 'inactive_plugin_' . md5( $file ), 'unused', __( 'An inactive plugin is installed', 'mozcheck' ), $description );
			}
		}

		$active_themes = array_filter( array( get_stylesheet(), get_template(), defined( 'WP_DEFAULT_THEME' ) ? WP_DEFAULT_THEME : '' ) );
		foreach ( $themes as $slug => $theme ) {
			if ( ! in_array( $slug, $active_themes, true ) ) {
				/* translators: %s: theme name. */
				$description = sprintf( __( '%s is not active. Remove it if it is no longer needed.', 'mozcheck' ), $theme->get( 'Name' ) );
				$results[]   = $this->inventory_result( 'inactive_theme_' . md5( $slug ), 'unused', __( 'An unused theme is installed', 'mozcheck' ), $description );
			}
		}

		return $results;
	}

	/**
	 * Build an inventory result.
	 *
	 * @param string $id ID.
	 * @param string $category Category.
	 * @param string $label Label.
	 * @param string $description Description.
	 * @param bool   $is_update Is an update.
	 * @return array<string, mixed>
	 */
	private function inventory_result( string $id, string $category, string $label, string $description, bool $is_update = false ): array {
		return array(
			'id'           => $id,
			'category'     => $category,
			'label'        => $label,
			'description'  => $description,
			'is_update'    => $is_update,
			'status'       => 'recommended',
			'actions'      => '',
			'count_status' => false,
		);
	}

	/**
	 * Map core test IDs to report categories.
	 *
	 * @param string $id Test ID.
	 * @return string
	 */
	private function category_for( string $id ): string {
		$map = array(
			'updates'  => array( 'wordpress_version', 'plugin_version', 'theme_version' ),
			'php_db'   => array( 'php_version', 'php_extensions', 'php_default_timezone', 'php_sessions', 'sql_server' ),
			'rest'     => array( 'rest_availability', 'authorization_header' ),
			'loopback' => array( 'loopback_requests' ),
			'https'    => array( 'https_status', 'ssl_support' ),
			'cron'     => array( 'scheduled_events', 'background_updates' ),
		);
		foreach ( $map as $category => $ids ) {
			if ( in_array( $id, $ids, true ) ) {
				return $category;
			}
		}
		return 'other';
	}
}
