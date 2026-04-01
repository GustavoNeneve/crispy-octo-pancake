<?php
/**
 * WordPress shortcode [demandas_app] to embed the SPA.
 */

defined( 'ABSPATH' ) || exit;

class WP_Demandas_Shortcode {
	private const LOGIN_MAX_ATTEMPTS = 3;
	private const LOGIN_LOCK_SECONDS = 900;
	private const LOGIN_ATTEMPTS_TTL = 604800;

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

		wp_enqueue_style(
			'wp-demandas-fonts',
			'https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap',
			array(),
			null
		);

		if ( ! is_user_logged_in() ) {
			return;
		}

		// Tailwind CSS Play CDN – vanilla JS stack decision (Task 2: permanece vanilla JS).
		// preflight:false preserves existing custom CSS tokens without a reset conflict.
		wp_enqueue_script(
			'tailwindcss',
			'https://cdn.tailwindcss.com',
			array(),
			null,
			false // load in <head> so JIT scans the DOM on DOMContentLoaded
		);
		wp_add_inline_script(
			'tailwindcss',
			'tailwind.config = { corePlugins: { preflight: false }, theme: { extend: { colors: { "dm-primary": "var(--dm-primary)", "dm-blue": "var(--dm-blue)", "dm-green": "var(--dm-green)", "dm-yellow": "var(--dm-yellow)", "dm-pink": "var(--dm-pink)" } } } }',
			'before'
		);

		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
			array(),
			'4.4.3',
			true
		);

		wp_enqueue_script(
			'wp-demandas-app',
			WP_DEMANDAS_PLUGIN_URL . 'assets/js/app.js',
			array( 'chartjs' ),
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
			return self::render_login_form();
		}

		ob_start();
		include WP_DEMANDAS_PLUGIN_DIR . 'templates/app.php';
		return ob_get_clean();
	}

	private static function render_login_form() {
		$error_message = '';
		$login_value   = '';
		$remember_me   = false;

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['dm_login_action'] ) ) {
			$nonce = isset( $_POST['dm_login_nonce'] ) ? wp_unslash( $_POST['dm_login_nonce'] ) : '';
			if ( ! wp_verify_nonce( $nonce, 'dm_login_action' ) ) {
				$error_message = esc_html__( 'Não foi possível validar sua sessão. Atualize a página e tente novamente.', 'wp-demandas' );
			} else {
				$login_value = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
				$password    = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
				$remember_me = ! empty( $_POST['rememberme'] );
				$lock_key    = self::get_login_lock_key( $login_value );
				$lock_until  = (int) get_transient( $lock_key );

				if ( $lock_until > time() ) {
					$seconds_left = max( 1, $lock_until - time() );
					$error_message = sprintf(
						/* translators: %s: human-readable lockout time left. */
						esc_html__( 'Muitas tentativas inválidas. Tente novamente em %s.', 'wp-demandas' ),
						esc_html( self::format_lock_time_left( $seconds_left ) )
					);
				} elseif ( '' === $login_value || '' === $password ) {
					$error_message = esc_html__( 'Preencha usuário/e-mail e senha para continuar.', 'wp-demandas' );
				} else {
					$user = wp_signon(
						array(
							'user_login'    => $login_value,
							'user_password' => $password,
							'remember'      => $remember_me,
						),
						is_ssl()
					);

					if ( is_wp_error( $user ) ) {
						$attempts = (int) get_transient( $lock_key . '_attempts' );
						$attempts++;

						if ( $attempts >= self::LOGIN_MAX_ATTEMPTS ) {
							$lock_until = time() + self::LOGIN_LOCK_SECONDS;
							set_transient( $lock_key, $lock_until, self::LOGIN_LOCK_SECONDS );
							delete_transient( $lock_key . '_attempts' );

							$error_message = sprintf(
								/* translators: %s: human-readable lockout time. */
								esc_html__( 'Muitas tentativas inválidas. Tente novamente em %s.', 'wp-demandas' ),
								esc_html( self::format_lock_time_left( self::LOGIN_LOCK_SECONDS ) )
							);
						} else {
							set_transient( $lock_key . '_attempts', $attempts, self::LOGIN_ATTEMPTS_TTL );
							$error_message = esc_html__( 'Credenciais inválidas. Verifique seus dados e tente novamente.', 'wp-demandas' );
						}
					} else {
						delete_transient( $lock_key );
						delete_transient( $lock_key . '_attempts' );
						wp_set_current_user( $user->ID );
						wp_safe_redirect( get_permalink() );
						exit;
					}
				}
			}
		}

		ob_start();
		?>
		<div class="dm-login-shell" role="main">
			<div class="dm-login-card">
				<div class="dm-login-brand">
					<p class="dm-login-kicker"><?php esc_html_e( 'Demand Management', 'wp-demandas' ); ?></p>
					<h2><?php esc_html_e( 'Acessar plataforma', 'wp-demandas' ); ?></h2>
					<p><?php esc_html_e( 'Faça login para gerenciar demandas, quadros e relatórios do seu time.', 'wp-demandas' ); ?></p>
				</div>

				<?php if ( $error_message ) : ?>
					<div class="dm-login-alert" role="alert"><?php echo esc_html( $error_message ); ?></div>
				<?php endif; ?>

				<form method="post" class="dm-login-form" aria-label="<?php esc_attr_e( 'Formulário de login', 'wp-demandas' ); ?>" novalidate>
					<input type="hidden" name="dm_login_action" value="1">
					<?php wp_nonce_field( 'dm_login_action', 'dm_login_nonce' ); ?>

					<label for="dm-login-user"><?php esc_html_e( 'Usuário ou e-mail', 'wp-demandas' ); ?></label>
					<input
						type="text"
						id="dm-login-user"
						name="log"
						class="dm-input dm-full-width"
						autocomplete="username"
						required
						value="<?php echo esc_attr( $login_value ); ?>"
					>

					<label for="dm-login-password"><?php esc_html_e( 'Senha', 'wp-demandas' ); ?></label>
					<input
						type="password"
						id="dm-login-password"
						name="pwd"
						class="dm-input dm-full-width"
						autocomplete="current-password"
						required
					>

					<label class="dm-checkbox-label">
						<input type="checkbox" name="rememberme" value="forever" <?php checked( $remember_me ); ?>>
						<?php esc_html_e( 'Permanecer conectado', 'wp-demandas' ); ?>
					</label>

					<button type="submit" class="dm-btn dm-btn-primary dm-full-width">
						<?php esc_html_e( 'Entrar', 'wp-demandas' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function get_login_lock_key( $login_value ) {
		$login = strtolower( trim( (string) $login_value ) );
		$ip    = self::get_client_ip();
		return 'dm_login_lock_' . md5( $login . '|' . $ip );
	}

	private static function get_client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	}

	private static function format_lock_time_left( $seconds_left ) {
		$minutes = (int) ceil( $seconds_left / 60 );
		if ( $minutes <= 1 ) {
			return esc_html__( '1 minuto', 'wp-demandas' );
		}

		return sprintf(
			/* translators: %d: number of minutes. */
			esc_html__( '%d minutos', 'wp-demandas' ),
			$minutes
		);
	}
}
