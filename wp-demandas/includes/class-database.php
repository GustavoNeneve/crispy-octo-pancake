<?php
/**
 * Database class for WP Demandas plugin.
 * Creates and manages custom tables for tasks, history, recurring types, sectors and settings.
 */

defined( 'ABSPATH' ) || exit;

class WP_Demandas_Database {

	/**
	 * Run on plugin activation – create all tables.
	 */
	public static function install() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// ----------------------------------------------------------------
		// Sectors table
		// ----------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}dm_sectors (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name        VARCHAR(120)        NOT NULL,
			manager_id  BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY manager_id (manager_id)
		) $charset_collate;";
		dbDelta( $sql );

		// ----------------------------------------------------------------
		// Recurring task types
		// ----------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}dm_recurring_types (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name            VARCHAR(200)        NOT NULL,
			sector_id       BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_by      BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			weekly_average  DECIMAL(5,2)        NOT NULL DEFAULT 1.00,
			created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY sector_id (sector_id)
		) $charset_collate;";
		dbDelta( $sql );

		// ----------------------------------------------------------------
		// Tasks table
		// ----------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}dm_tasks (
			id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title               VARCHAR(255)        NOT NULL,
			description         LONGTEXT,
			task_type           ENUM('routine','planned','urgent','planned_recurring') NOT NULL DEFAULT 'planned',
			status              ENUM('waiting','in_progress','in_approval','completed') NOT NULL DEFAULT 'waiting',
			color               ENUM('blue','yellow','pink') NOT NULL DEFAULT 'yellow',
			assigned_to         BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_by          BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			sector_id           BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			recurring_type_id   BIGINT(20) UNSIGNED DEFAULT NULL,
			images              LONGTEXT COMMENT 'JSON array of image URLs',
			week_key            VARCHAR(10)  NOT NULL DEFAULT '',
			is_archived         TINYINT(1)   NOT NULL DEFAULT 0,
			is_auto_routine     TINYINT(1)   NOT NULL DEFAULT 0,
			approved_by         BIGINT(20) UNSIGNED DEFAULT NULL,
			approved_at         DATETIME    DEFAULT NULL,
			completed_at        DATETIME    DEFAULT NULL,
			created_at          DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at          DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY assigned_to (assigned_to),
			KEY sector_id (sector_id),
			KEY status (status),
			KEY week_key (week_key),
			KEY task_type (task_type)
		) $charset_collate;";
		dbDelta( $sql );

		// ----------------------------------------------------------------
		// Task change history
		// ----------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}dm_task_history (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			task_id     BIGINT(20) UNSIGNED NOT NULL,
			user_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			action      VARCHAR(80)         NOT NULL,
			old_value   LONGTEXT COMMENT 'JSON snapshot of changed fields before',
			new_value   LONGTEXT COMMENT 'JSON snapshot of changed fields after',
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY task_id (task_id),
			KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql );

		// ----------------------------------------------------------------
		// Weekly snapshots (end-of-week state saved for reports)
		// ----------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}dm_weekly_snapshots (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			task_id       BIGINT(20) UNSIGNED NOT NULL,
			week_key      VARCHAR(10)         NOT NULL,
			snapshot_data LONGTEXT            NOT NULL COMMENT 'Full task JSON at snapshot time',
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY task_id (task_id),
			KEY week_key (week_key)
		) $charset_collate;";
		dbDelta( $sql );

		// ----------------------------------------------------------------
		// Per-user settings
		// ----------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}dm_user_settings (
			id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id              BIGINT(20) UNSIGNED NOT NULL,
			sector_id            BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			auto_create_routines TINYINT(1)          NOT NULL DEFAULT 0,
			settings_json        LONGTEXT            COMMENT 'Extra settings JSON',
			created_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql );

		update_option( 'wp_demandas_db_version', WP_DEMANDAS_VERSION );
	}

	// ----------------------------------------------------------------
	// Helper: log a task history entry
	// ----------------------------------------------------------------
	public static function log_history( $task_id, $action, $old = array(), $new = array(), $user_id = 0 ) {
		global $wpdb;
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		$wpdb->insert(
			$wpdb->prefix . 'dm_task_history',
			array(
				'task_id'   => (int) $task_id,
				'user_id'   => (int) $user_id,
				'action'    => sanitize_text_field( $action ),
				'old_value' => wp_json_encode( $old ),
				'new_value' => wp_json_encode( $new ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
	}

	// ----------------------------------------------------------------
	// Helper: get or create user settings row
	// ----------------------------------------------------------------
	public static function get_user_settings( $user_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dm_user_settings WHERE user_id = %d",
				(int) $user_id
			),
			ARRAY_A
		);
		if ( ! $row ) {
			$wpdb->insert(
				$wpdb->prefix . 'dm_user_settings',
				array( 'user_id' => (int) $user_id ),
				array( '%d' )
			);
			$row = array(
				'user_id'              => $user_id,
				'sector_id'            => 0,
				'auto_create_routines' => 0,
				'settings_json'        => '{}',
			);
		}
		return $row;
	}
}
