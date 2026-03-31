<?php
/**
 * Plugin Name: WP Demandas - Gestão de Demandas de Marketing
 * Plugin URI:  https://github.com/GustavoNeneve/crispy-octo-pancake
 * Description: Sistema de gestão de demandas e tarefas semanais para equipes de marketing. Suporta gestores e liderados, quadro Kanban, histórico de alterações e relatórios.
 * Version:     1.0.0
 * Author:      Gustavo Neneve
 * License:     GPL-2.0+
 * Text Domain: wp-demandas
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_DEMANDAS_VERSION', '1.0.0' );
define( 'WP_DEMANDAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_DEMANDAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WP_DEMANDAS_PLUGIN_DIR . 'includes/class-database.php';
require_once WP_DEMANDAS_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once WP_DEMANDAS_PLUGIN_DIR . 'includes/class-cron.php';
require_once WP_DEMANDAS_PLUGIN_DIR . 'includes/class-shortcode.php';

register_activation_hook( __FILE__, array( 'WP_Demandas_Database', 'install' ) );
register_deactivation_hook( __FILE__, array( 'WP_Demandas_Cron', 'deactivate' ) );

add_action( 'plugins_loaded', 'wp_demandas_init' );

function wp_demandas_init() {
	WP_Demandas_Rest_Api::register();
	WP_Demandas_Cron::register();
	WP_Demandas_Shortcode::register();

	// Add custom roles if they don't exist.
	if ( ! get_role( 'dm_manager' ) ) {
		add_role(
			'dm_manager',
			__( 'Gestor de Demandas', 'wp-demandas' ),
			array(
				'read'              => true,
				'dm_manage_sector'  => true,
				'dm_view_dashboard' => true,
			)
		);
	}
	if ( ! get_role( 'dm_member' ) ) {
		add_role(
			'dm_member',
			__( 'Liderado', 'wp-demandas' ),
			array(
				'read'           => true,
				'dm_view_board'  => true,
			)
		);
	}
}

/**
 * Check if a user is a manager (gestor).
 */
function wp_demandas_is_manager( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	return in_array( 'dm_manager', (array) $user->roles, true )
		|| in_array( 'administrator', (array) $user->roles, true );
}

/**
 * Get the week key string (YYYY-WW).
 */
function wp_demandas_week_key( $timestamp = null ) {
	if ( ! $timestamp ) {
		$timestamp = current_time( 'timestamp' );
	}
	return date( 'Y-W', $timestamp );
}

/**
 * Determine task type based on creation context.
 * Returns: routine | planned | urgent | planned_recurring
 */
function wp_demandas_suggest_task_type( $user_id = 0 ) {
	$day = (int) date( 'N', current_time( 'timestamp' ) ); // 1=Mon … 7=Sun
	if ( $day === 1 ) {
		return 'planned';
	}
	return 'urgent';
}
