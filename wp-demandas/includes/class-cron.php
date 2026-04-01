<?php
/**
 * Weekly reset cron job for WP Demandas.
 *
 * Every Monday at 00:00 (WordPress cron):
 *  1. Snapshot all current-week tasks.
 *  2. Archive completed tasks (move them to history / set is_archived = 1).
 *  3. Urgent tasks in waiting/in_progress become planned for the new week.
 *  4. waiting/in_progress tasks are kept but assigned the new week_key.
 *  5. Auto-create routine tasks for users with auto_create_routines = 1.
 */

defined( 'ABSPATH' ) || exit;

class WP_Demandas_Cron {

	const HOOK = 'wp_demandas_weekly_reset';

	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'do_weekly_reset' ) );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// Schedule for next Monday 00:00 in the WordPress site timezone.
			$next_monday = self::next_monday_midnight();
			wp_schedule_event( $next_monday, 'weekly', self::HOOK );
		}
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	/**
	 * Main weekly reset routine.
	 */
	public static function do_weekly_reset() {
		global $wpdb;

		$old_week_key = wp_demandas_week_key( strtotime( 'last week', current_time( 'timestamp' ) ) );
		$new_week_key = wp_demandas_week_key();

		// ----------------------------------------------------------------
		// 1. Snapshot ALL non-archived tasks of the ending week.
		// ----------------------------------------------------------------
		$tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dm_tasks WHERE week_key = %s AND is_archived = 0",
				$old_week_key
			),
			ARRAY_A
		);

		foreach ( $tasks as $task ) {
			$task['images'] = json_decode( $task['images'] ?: '[]', true );
			$wpdb->insert(
				$wpdb->prefix . 'dm_weekly_snapshots',
				array(
					'task_id'       => (int) $task['id'],
					'week_key'      => $old_week_key,
					'snapshot_data' => wp_json_encode( $task ),
				),
				array( '%d', '%s', '%s' )
			);
		}

		// ----------------------------------------------------------------
		// 2. Archive completed tasks.
		// ----------------------------------------------------------------
		$wpdb->update(
			$wpdb->prefix . 'dm_tasks',
			array( 'is_archived' => 1 ),
			array( 'week_key' => $old_week_key, 'status' => 'completed', 'is_archived' => 0 ),
			array( '%d' ),
			array( '%s', '%s', '%d' )
		);
		// Also archive in_approval tasks (they can carry over but data is saved).
		// Policy: leave them active but push to new week.

		// ----------------------------------------------------------------
		// 3. Carry over waiting / in_progress tasks to new week.
		//    Urgent ones become planned.
		// ----------------------------------------------------------------
		$carry_over_tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, task_type, status FROM {$wpdb->prefix}dm_tasks
				 WHERE week_key = %s AND is_archived = 0 AND status IN ('waiting','in_progress','in_approval')",
				$old_week_key
			),
			ARRAY_A
		);

		foreach ( $carry_over_tasks as $t ) {
			$new_type  = $t['task_type'];
			$new_color = null;

			// Urgent tasks become planned at the start of a new week.
			if ( 'urgent' === $new_type ) {
				$new_type  = 'planned';
				$new_color = 'yellow';
			}

			$upd = array( 'week_key' => $new_week_key, 'task_type' => $new_type );
			$fmt = array( '%s', '%s' );
			if ( $new_color ) {
				$upd['color'] = $new_color;
				$fmt[]        = '%s';
			}

			$wpdb->update(
				$wpdb->prefix . 'dm_tasks',
				$upd,
				array( 'id' => (int) $t['id'] ),
				$fmt,
				array( '%d' )
			);

			WP_Demandas_Database::log_history(
				(int) $t['id'],
				'weekly_carryover',
				array( 'week_key' => $old_week_key, 'task_type' => $t['task_type'] ),
				array( 'week_key' => $new_week_key, 'task_type' => $new_type ),
				0
			);
		}

		// ----------------------------------------------------------------
		// 4. Auto-create routine tasks for users who opted in.
		// ----------------------------------------------------------------
		$auto_users = $wpdb->get_results(
			"SELECT user_id, settings_json FROM {$wpdb->prefix}dm_user_settings WHERE auto_create_routines = 1",
			ARRAY_A
		);

		foreach ( $auto_users as $row ) {
			$extra = json_decode( $row['settings_json'] ?: '{}', true );
			$routine_titles = isset( $extra['routine_titles'] ) ? (array) $extra['routine_titles'] : array( 'Rotina diária' );
			$sector_id      = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT sector_id FROM {$wpdb->prefix}dm_user_settings WHERE user_id = %d",
					(int) $row['user_id']
				)
			);

			foreach ( $routine_titles as $title ) {
				$wpdb->insert(
					$wpdb->prefix . 'dm_tasks',
					array(
						'title'           => sanitize_text_field( $title ),
						'task_type'       => 'routine',
						'status'          => 'waiting',
						'color'           => 'blue',
						'assigned_to'     => (int) $row['user_id'],
						'created_by'      => 0,
						'sector_id'       => $sector_id,
						'week_key'        => $new_week_key,
						'is_auto_routine' => 1,
					),
					array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d' )
				);
			}
		}

		do_action( 'wp_demandas_after_weekly_reset', $old_week_key, $new_week_key );
	}

	/**
	 * Returns a Unix timestamp for the next Monday at 00:00 in the WordPress timezone.
	 *
	 * wp_timezone() (WP 5.3+) gives us a DateTimeZone built from the site's
	 * "timezone_string" or "gmt_offset" option, so the reset fires at Monday
	 * 00:00 local time regardless of the server's UTC offset.
	 */
	private static function next_monday_midnight() {
		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );
		$day = (int) $now->format( 'N' ); // 1 = Monday … 7 = Sunday
		$diff = ( 8 - $day ) % 7;
		if ( 0 === $diff ) {
			$diff = 7;
		}
		// Move forward $diff days and set to midnight in the site's timezone.
		$target = $now->modify( "+{$diff} days" )->setTime( 0, 0, 0 );
		// Return as UTC epoch (what wp_schedule_event expects).
		return $target->getTimestamp();
	}
}
