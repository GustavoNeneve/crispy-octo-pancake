<?php
/**
 * WordPress shortcode [demandas_app] to embed the SPA.
 */

defined( 'ABSPATH' ) || exit;

class WP_Demandas_Shortcode {

	public static function register() {
		add_shortcode( 'demandas_app', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets() {
		global $post;
		// Only enqueue on posts/pages that contain the shortcode.
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'demandas_app' ) ) {
			return;
		}

		wp_enqueue_style(
			'wp-demandas-app',
			WP_DEMANDAS_PLUGIN_URL . 'assets/css/app.css',
			array(),
			WP_DEMANDAS_VERSION
		);

		wp_enqueue_script(
			'wp-demandas-app',
			WP_DEMANDAS_PLUGIN_URL . 'assets/js/app.js',
			array(),
			WP_DEMANDAS_VERSION,
			true   // Footer
		);

		// Pass data to the JS app.
		$user     = wp_get_current_user();
		$is_mgr   = wp_demandas_is_manager();
		$settings = $user->ID ? WP_Demandas_Database::get_user_settings( $user->ID ) : array();

		wp_localize_script(
			'wp-demandas-app',
			'wpDemandas',
			array(
				'apiBase'    => esc_url_raw( rest_url( 'demandas/v1' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'loginUrl'   => wp_login_url( get_permalink() ),
				'user'       => $user->ID ? array(
					'id'          => $user->ID,
					'name'        => $user->display_name,
					'email'       => $user->user_email,
					'is_manager'  => $is_mgr,
					'sector_id'   => isset( $settings['sector_id'] ) ? (int) $settings['sector_id'] : 0,
					'auto_routines' => isset( $settings['auto_create_routines'] ) ? (bool) $settings['auto_create_routines'] : false,
				) : null,
				'weekKey'    => wp_demandas_week_key(),
				'dayOfWeek'  => (int) date( 'N', current_time( 'timestamp' ) ),
			)
		);
	}

	public static function render( $atts ) {
		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<div class="dm-login-prompt"><p>%s</p><a href="%s" class="dm-btn dm-btn-primary">%s</a></div>',
				esc_html__( 'Você precisa estar logado para acessar o sistema de demandas.', 'wp-demandas' ),
				esc_url( wp_login_url( get_permalink() ) ),
				esc_html__( 'Fazer login', 'wp-demandas' )
			);
		}

		ob_start();
		include WP_DEMANDAS_PLUGIN_DIR . 'templates/app.php';
		return ob_get_clean();
	}
}
