<?php
/**
 * REST API endpoints for WP Demandas.
 *
 * Namespace: demandas/v1
 *
 * Routes:
 *   GET    /tasks                    – list tasks (current week, filtered by role)
 *   POST   /tasks                    – create task
 *   GET    /tasks/{id}               – get single task
 *   PUT    /tasks/{id}               – update task
 *   DELETE /tasks/{id}               – delete task
 *   POST   /tasks/{id}/status        – change status (move column)
 *   POST   /tasks/{id}/approve       – manager approves task
 *   POST   /tasks/{id}/transfer      – transfer task to another user
 *   GET    /tasks/{id}/history       – get task change history
 *   GET    /dashboard                – manager dashboard stats
 *   GET    /sectors                  – list sectors
 *   POST   /sectors                  – create sector (manager)
 *   GET    /recurring-types          – list recurring types (with autocomplete search)
 *   POST   /recurring-types          – create recurring type
 *   PUT    /recurring-types/{id}     – update recurring type
 *   DELETE /recurring-types/{id}     – delete recurring type
 *   GET    /users                    – list users in manager's sector
 *   GET    /settings                 – get current user settings
 *   PUT    /settings                 – update current user settings
 *   POST   /tasks/routine            – create / auto-create routine tasks for today
 *   GET    /weekly-history           – past week snapshots (manager)
 */

defined( 'ABSPATH' ) || exit;

class WP_Demandas_Rest_Api {

	const NS = 'demandas/v1';

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		// Tasks collection.
		register_rest_route( self::NS, '/tasks', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_tasks' ),
				'permission_callback' => array( __CLASS__, 'is_logged_in' ),
				'args'                => array(
					'week_key'    => array( 'type' => 'string' ),
					'status'      => array( 'type' => 'string' ),
					'assigned_to' => array( 'type' => 'integer' ),
					'sector_id'   => array( 'type' => 'integer' ),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_task' ),
				'permission_callback' => array( __CLASS__, 'is_logged_in' ),
			),
		) );

		// Single task.
		register_rest_route( self::NS, '/tasks/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_task' ),
				'permission_callback' => array( __CLASS__, 'can_access_task' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_task' ),
				'permission_callback' => array( __CLASS__, 'can_edit_task' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_task' ),
				'permission_callback' => array( __CLASS__, 'can_edit_task' ),
			),
		) );

		// Task status transition.
		register_rest_route( self::NS, '/tasks/(?P<id>\d+)/status', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'change_status' ),
			'permission_callback' => array( __CLASS__, 'can_edit_task' ),
		) );

		// Approve task.
		register_rest_route( self::NS, '/tasks/(?P<id>\d+)/approve', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'approve_task' ),
			'permission_callback' => array( __CLASS__, 'is_manager' ),
		) );

		// Transfer task.
		register_rest_route( self::NS, '/tasks/(?P<id>\d+)/transfer', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'transfer_task' ),
			'permission_callback' => array( __CLASS__, 'can_edit_task' ),
		) );

		// Task history.
		register_rest_route( self::NS, '/tasks/(?P<id>\d+)/history', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_task_history' ),
			'permission_callback' => array( __CLASS__, 'can_access_task' ),
		) );

		// Routine creation.
		register_rest_route( self::NS, '/tasks/routine', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'create_routine_tasks' ),
			'permission_callback' => array( __CLASS__, 'is_logged_in' ),
		) );

		// Dashboard.
		register_rest_route( self::NS, '/dashboard', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_dashboard' ),
			'permission_callback' => array( __CLASS__, 'is_logged_in' ),
			'args'                => array(
				'week_key'  => array( 'type' => 'string' ),
				'sector_id' => array( 'type' => 'integer' ),
			),
		) );

		// Sectors.
		register_rest_route( self::NS, '/sectors', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_sectors' ),
				'permission_callback' => array( __CLASS__, 'is_logged_in' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_sector' ),
				'permission_callback' => array( __CLASS__, 'is_manager' ),
			),
		) );

		register_rest_route( self::NS, '/sectors/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_sector' ),
				'permission_callback' => array( __CLASS__, 'is_manager' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_sector' ),
				'permission_callback' => array( __CLASS__, 'is_manager' ),
			),
		) );

		// Recurring types.
		register_rest_route( self::NS, '/recurring-types', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_recurring_types' ),
				'permission_callback' => array( __CLASS__, 'is_logged_in' ),
				'args'                => array(
					'search'    => array( 'type' => 'string' ),
					'sector_id' => array( 'type' => 'integer' ),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_recurring_type' ),
				'permission_callback' => array( __CLASS__, 'is_logged_in' ),
			),
		) );

		register_rest_route( self::NS, '/recurring-types/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_recurring_type' ),
				'permission_callback' => array( __CLASS__, 'is_logged_in' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_recurring_type' ),
				'permission_callback' => array( __CLASS__, 'is_logged_in' ),
			),
		) );

		// Users list (for managers/transfers).
		register_rest_route( self::NS, '/users', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_users' ),
			'permission_callback' => array( __CLASS__, 'is_logged_in' ),
			'args'                => array(
				'sector_id' => array( 'type' => 'integer' ),
			),
		) );

		// Current user settings.
		register_rest_route( self::NS, '/settings', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_settings' ),
				'permission_callback' => array( __CLASS__, 'is_logged_in' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_settings' ),
				'permission_callback' => array( __CLASS__, 'is_logged_in' ),
			),
		) );

		// Weekly history (for past weeks).
		register_rest_route( self::NS, '/weekly-history', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_weekly_history' ),
			'permission_callback' => array( __CLASS__, 'is_logged_in' ),
			'args'                => array(
				'week_key'  => array( 'type' => 'string' ),
				'sector_id' => array( 'type' => 'integer' ),
			),
		) );
	}

	// ================================================================
	// Permission callbacks
	// ================================================================

	public static function is_logged_in( $request ) {
		return is_user_logged_in();
	}

	public static function is_manager( $request ) {
		return wp_demandas_is_manager();
	}

	public static function can_access_task( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$task = self::fetch_task( (int) $request['id'] );
		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Tarefa não encontrada.', 'wp-demandas' ), array( 'status' => 404 ) );
		}
		$uid = get_current_user_id();
		return wp_demandas_is_manager() || (int) $task['assigned_to'] === $uid || (int) $task['created_by'] === $uid;
	}

	public static function can_edit_task( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$task = self::fetch_task( (int) $request['id'] );
		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Tarefa não encontrada.', 'wp-demandas' ), array( 'status' => 404 ) );
		}
		$uid = get_current_user_id();
		return wp_demandas_is_manager() || (int) $task['assigned_to'] === $uid || (int) $task['created_by'] === $uid;
	}

	// ================================================================
	// Tasks
	// ================================================================

	public static function get_tasks( WP_REST_Request $request ) {
		global $wpdb;
		$uid       = get_current_user_id();
		$is_mgr    = wp_demandas_is_manager();
		$week_key  = sanitize_text_field( $request->get_param( 'week_key' ) ?: wp_demandas_week_key() );
		$status    = sanitize_text_field( $request->get_param( 'status' ) ?: '' );
		$sector_id = (int) $request->get_param( 'sector_id' );

		$where  = array( 'is_archived = 0' );
		$params = array();

		if ( $week_key ) {
			$where[]  = 'week_key = %s';
			$params[] = $week_key;
		}
		if ( $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		if ( ! $is_mgr ) {
			$where[]  = 'assigned_to = %d';
			$params[] = $uid;
		} elseif ( $sector_id ) {
			$where[]  = 'sector_id = %d';
			$params[] = $sector_id;
		}

		$sql   = 'SELECT * FROM ' . $wpdb->prefix . 'dm_tasks WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC';
		$tasks = $wpdb->get_results( $params ? $wpdb->prepare( $sql, ...$params ) : $sql, ARRAY_A );

		return rest_ensure_response( array_map( array( __CLASS__, 'format_task' ), $tasks ) );
	}

	public static function create_task( WP_REST_Request $request ) {
		global $wpdb;
		$uid      = get_current_user_id();
		$is_mgr   = wp_demandas_is_manager();
		$settings = WP_Demandas_Database::get_user_settings( $uid );

		$title             = sanitize_text_field( $request->get_param( 'title' ) );
		$description       = wp_kses_post( $request->get_param( 'description' ) );
		$task_type         = sanitize_key( $request->get_param( 'task_type' ) ?: 'planned' );
		$assigned_to       = (int) $request->get_param( 'assigned_to' ) ?: $uid;
		$sector_id         = (int) $request->get_param( 'sector_id' ) ?: (int) $settings['sector_id'];
		$recurring_type_id = (int) $request->get_param( 'recurring_type_id' ) ?: null;
		$images            = $request->get_param( 'images' ) ?: array();
		$week_key          = wp_demandas_week_key();

		if ( ! $is_mgr ) {
			$assigned_to = $uid;
		}

		// Determine color from task_type.
		$color = self::type_to_color( $task_type );

		if ( ! $title ) {
			return new WP_Error( 'missing_title', __( 'Título é obrigatório.', 'wp-demandas' ), array( 'status' => 400 ) );
		}

		$data = array(
			'title'             => $title,
			'description'       => $description,
			'task_type'         => $task_type,
			'status'            => 'waiting',
			'color'             => $color,
			'assigned_to'       => $assigned_to,
			'created_by'        => $uid,
			'sector_id'         => $sector_id,
			'recurring_type_id' => $recurring_type_id ?: null,
			'images'            => wp_json_encode( (array) $images ),
			'week_key'          => $week_key,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' );

		$wpdb->insert( $wpdb->prefix . 'dm_tasks', $data, $formats );
		$task_id = $wpdb->insert_id;

		WP_Demandas_Database::log_history( $task_id, 'created', array(), $data );

		return rest_ensure_response( self::format_task( self::fetch_task( $task_id ) ) );
	}

	public static function get_task( WP_REST_Request $request ) {
		$task = self::fetch_task( (int) $request['id'] );
		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Tarefa não encontrada.', 'wp-demandas' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::format_task( $task ) );
	}

	public static function update_task( WP_REST_Request $request ) {
		global $wpdb;
		$task_id = (int) $request['id'];
		$task    = self::fetch_task( $task_id );
		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Tarefa não encontrada.', 'wp-demandas' ), array( 'status' => 404 ) );
		}

		$old = $task;
		$upd = array();
		$fmt = array();

		$updatable = array(
			'title'             => array( 'sanitize_text_field', '%s' ),
			'description'       => array( 'wp_kses_post', '%s' ),
			'task_type'         => array( 'sanitize_key', '%s' ),
			'sector_id'         => array( 'intval', '%d' ),
			'recurring_type_id' => array( 'intval', '%d' ),
		);

		foreach ( $updatable as $field => $config ) {
			if ( null !== $request->get_param( $field ) ) {
				$upd[ $field ] = call_user_func( $config[0], $request->get_param( $field ) );
				$fmt[]         = $config[1];
			}
		}

		// Images.
		if ( null !== $request->get_param( 'images' ) ) {
			$upd['images'] = wp_json_encode( (array) $request->get_param( 'images' ) );
			$fmt[]         = '%s';
		}

		// Recompute color if type changed.
		if ( isset( $upd['task_type'] ) ) {
			$upd['color'] = self::type_to_color( $upd['task_type'] );
			$fmt[]        = '%s';
		}

		if ( empty( $upd ) ) {
			return rest_ensure_response( self::format_task( $task ) );
		}

		$wpdb->update( $wpdb->prefix . 'dm_tasks', $upd, array( 'id' => $task_id ), $fmt, array( '%d' ) );
		WP_Demandas_Database::log_history( $task_id, 'updated', $old, $upd );

		return rest_ensure_response( self::format_task( self::fetch_task( $task_id ) ) );
	}

	public static function delete_task( WP_REST_Request $request ) {
		global $wpdb;
		$task_id = (int) $request['id'];
		$task    = self::fetch_task( $task_id );
		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Tarefa não encontrada.', 'wp-demandas' ), array( 'status' => 404 ) );
		}
		WP_Demandas_Database::log_history( $task_id, 'deleted', $task, array() );
		$wpdb->delete( $wpdb->prefix . 'dm_tasks', array( 'id' => $task_id ), array( '%d' ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function change_status( WP_REST_Request $request ) {
		global $wpdb;
		$task_id    = (int) $request['id'];
		$new_status = sanitize_key( $request->get_param( 'status' ) );
		$allowed    = array( 'waiting', 'in_progress', 'in_approval', 'completed' );

		if ( ! in_array( $new_status, $allowed, true ) ) {
			return new WP_Error( 'invalid_status', __( 'Status inválido.', 'wp-demandas' ), array( 'status' => 400 ) );
		}

		$task = self::fetch_task( $task_id );
		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Tarefa não encontrada.', 'wp-demandas' ), array( 'status' => 404 ) );
		}

		$upd = array( 'status' => $new_status );
		if ( 'completed' === $new_status ) {
			$upd['completed_at'] = current_time( 'mysql' );
		}

		$wpdb->update( $wpdb->prefix . 'dm_tasks', $upd, array( 'id' => $task_id ), array( '%s', '%s' ), array( '%d' ) );
		WP_Demandas_Database::log_history( $task_id, 'status_changed', array( 'status' => $task['status'] ), array( 'status' => $new_status ) );

		return rest_ensure_response( self::format_task( self::fetch_task( $task_id ) ) );
	}

	public static function approve_task( WP_REST_Request $request ) {
		global $wpdb;
		$task_id = (int) $request['id'];
		$task    = self::fetch_task( $task_id );
		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Tarefa não encontrada.', 'wp-demandas' ), array( 'status' => 404 ) );
		}
		$uid = get_current_user_id();
		$wpdb->update(
			$wpdb->prefix . 'dm_tasks',
			array(
				'status'      => 'completed',
				'approved_by' => $uid,
				'approved_at' => current_time( 'mysql' ),
				'completed_at'=> current_time( 'mysql' ),
			),
			array( 'id' => $task_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
		WP_Demandas_Database::log_history( $task_id, 'approved', array( 'status' => $task['status'] ), array( 'status' => 'completed', 'approved_by' => $uid ) );
		return rest_ensure_response( self::format_task( self::fetch_task( $task_id ) ) );
	}

	public static function transfer_task( WP_REST_Request $request ) {
		global $wpdb;
		$task_id  = (int) $request['id'];
		$new_user = (int) $request->get_param( 'user_id' );
		$note     = sanitize_textarea_field( $request->get_param( 'note' ) );

		if ( ! $new_user ) {
			return new WP_Error( 'missing_user', __( 'Usuário destino é obrigatório.', 'wp-demandas' ), array( 'status' => 400 ) );
		}

		$task = self::fetch_task( $task_id );
		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Tarefa não encontrada.', 'wp-demandas' ), array( 'status' => 404 ) );
		}

		$old_user = $task['assigned_to'];
		$wpdb->update(
			$wpdb->prefix . 'dm_tasks',
			array( 'assigned_to' => $new_user ),
			array( 'id' => $task_id ),
			array( '%d' ),
			array( '%d' )
		);

		WP_Demandas_Database::log_history(
			$task_id,
			'transferred',
			array( 'assigned_to' => $old_user ),
			array( 'assigned_to' => $new_user, 'note' => $note )
		);

		return rest_ensure_response( self::format_task( self::fetch_task( $task_id ) ) );
	}

	public static function get_task_history( WP_REST_Request $request ) {
		global $wpdb;
		$task_id = (int) $request['id'];
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.*, u.display_name as user_name
				 FROM {$wpdb->prefix}dm_task_history h
				 LEFT JOIN {$wpdb->users} u ON u.ID = h.user_id
				 WHERE h.task_id = %d
				 ORDER BY h.created_at DESC",
				$task_id
			),
			ARRAY_A
		);
		foreach ( $rows as &$row ) {
			$row['old_value'] = json_decode( $row['old_value'], true );
			$row['new_value'] = json_decode( $row['new_value'], true );
		}
		return rest_ensure_response( $rows );
	}

	public static function create_routine_tasks( WP_REST_Request $request ) {
		global $wpdb;
		$uid      = get_current_user_id();
		$week_key = wp_demandas_week_key();
		$today    = date( 'Y-m-d', current_time( 'timestamp' ) );
		$settings = WP_Demandas_Database::get_user_settings( $uid );

		// Check if routine already created today.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}dm_tasks
				 WHERE assigned_to = %d AND task_type = 'routine' AND DATE(created_at) = %s AND is_archived = 0",
				$uid,
				$today
			)
		);
		if ( $existing > 0 ) {
			return rest_ensure_response( array( 'message' => 'Rotinas já criadas hoje.', 'created' => 0 ) );
		}

		$titles  = (array) $request->get_param( 'titles' );
		$created = array();

		foreach ( $titles as $title ) {
			$title = sanitize_text_field( $title );
			if ( ! $title ) {
				continue;
			}
			$wpdb->insert(
				$wpdb->prefix . 'dm_tasks',
				array(
					'title'         => $title,
					'task_type'     => 'routine',
					'status'        => 'waiting',
					'color'         => 'blue',
					'assigned_to'   => $uid,
					'created_by'    => $uid,
					'sector_id'     => (int) $settings['sector_id'],
					'week_key'      => $week_key,
					'is_auto_routine' => 1,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d' )
			);
			$id        = $wpdb->insert_id;
			$created[] = self::format_task( self::fetch_task( $id ) );
			WP_Demandas_Database::log_history( $id, 'created', array(), array( 'title' => $title, 'task_type' => 'routine' ) );
		}

		return rest_ensure_response( array( 'created' => count( $created ), 'tasks' => $created ) );
	}

	// ================================================================
	// Dashboard
	// ================================================================

	public static function get_dashboard( WP_REST_Request $request ) {
		global $wpdb;
		$uid       = get_current_user_id();
		$is_mgr    = wp_demandas_is_manager();
		$week_key  = sanitize_text_field( $request->get_param( 'week_key' ) ?: wp_demandas_week_key() );
		$sector_id = (int) $request->get_param( 'sector_id' );

		$where  = array( 'week_key = %s AND is_archived = 0' );
		$params = array( $week_key );

		if ( ! $is_mgr ) {
			$where[]  = 'assigned_to = %d';
			$params[] = $uid;
		} elseif ( $sector_id ) {
			$where[]  = 'sector_id = %d';
			$params[] = $sector_id;
		}

		$base_sql = 'FROM ' . $wpdb->prefix . 'dm_tasks WHERE ' . implode( ' AND ', $where );

		// Status counts.
		$status_counts = $wpdb->get_results(
			$wpdb->prepare( 'SELECT status, COUNT(*) as cnt ' . $base_sql . ' GROUP BY status', ...$params ),
			ARRAY_A
		);
		$by_status = array( 'waiting' => 0, 'in_progress' => 0, 'in_approval' => 0, 'completed' => 0 );
		foreach ( $status_counts as $row ) {
			$by_status[ $row['status'] ] = (int) $row['cnt'];
		}

		// Type counts.
		$type_counts = $wpdb->get_results(
			$wpdb->prepare( 'SELECT task_type, COUNT(*) as cnt ' . $base_sql . ' GROUP BY task_type', ...$params ),
			ARRAY_A
		);
		$by_type = array( 'routine' => 0, 'planned' => 0, 'urgent' => 0, 'planned_recurring' => 0 );
		foreach ( $type_counts as $row ) {
			$by_type[ $row['task_type'] ] = (int) $row['cnt'];
		}

		$total = array_sum( $by_status );

		$response = array(
			'week_key'   => $week_key,
			'total'      => $total,
			'by_status'  => $by_status,
			'by_type'    => $by_type,
		);

		// Per-member breakdown (only for managers).
		if ( $is_mgr ) {
			$members = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT t.assigned_to, u.display_name, t.status, COUNT(*) as cnt ' . $base_sql . ' AND t.assigned_to > 0 GROUP BY t.assigned_to, t.status',
					...$params
				),
				ARRAY_A
			);

			// The above query needs table alias – re-do.
			$per_member_sql = 'SELECT t.assigned_to, u.display_name, t.status, COUNT(*) as cnt
				FROM ' . $wpdb->prefix . 'dm_tasks t
				LEFT JOIN ' . $wpdb->users . ' u ON u.ID = t.assigned_to
				WHERE t.week_key = %s AND t.is_archived = 0';
			$pm_params = array( $week_key );
			if ( $sector_id ) {
				$per_member_sql .= ' AND t.sector_id = %d';
				$pm_params[]     = $sector_id;
			}
			$per_member_sql .= ' GROUP BY t.assigned_to, t.status ORDER BY u.display_name';

			$members = $wpdb->get_results( $wpdb->prepare( $per_member_sql, ...$pm_params ), ARRAY_A );
			$by_member = array();
			foreach ( $members as $row ) {
				$mid = $row['assigned_to'];
				if ( ! isset( $by_member[ $mid ] ) ) {
					$by_member[ $mid ] = array(
						'user_id'      => $mid,
						'display_name' => $row['display_name'],
						'waiting'      => 0,
						'in_progress'  => 0,
						'in_approval'  => 0,
						'completed'    => 0,
					);
				}
				$by_member[ $mid ][ $row['status'] ] = (int) $row['cnt'];
			}
			$response['by_member'] = array_values( $by_member );
		}

		return rest_ensure_response( $response );
	}

	// ================================================================
	// Sectors
	// ================================================================

	public static function get_sectors( WP_REST_Request $request ) {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}dm_sectors ORDER BY name", ARRAY_A );
		return rest_ensure_response( $rows );
	}

	public static function create_sector( WP_REST_Request $request ) {
		global $wpdb;
		$name       = sanitize_text_field( $request->get_param( 'name' ) );
		$manager_id = (int) $request->get_param( 'manager_id' ) ?: get_current_user_id();
		if ( ! $name ) {
			return new WP_Error( 'missing_name', __( 'Nome é obrigatório.', 'wp-demandas' ), array( 'status' => 400 ) );
		}
		$wpdb->insert( $wpdb->prefix . 'dm_sectors', array( 'name' => $name, 'manager_id' => $manager_id ), array( '%s', '%d' ) );
		return rest_ensure_response( array( 'id' => $wpdb->insert_id, 'name' => $name, 'manager_id' => $manager_id ) );
	}

	public static function update_sector( WP_REST_Request $request ) {
		global $wpdb;
		$id   = (int) $request['id'];
		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$mgr  = (int) $request->get_param( 'manager_id' );
		$upd  = array();
		$fmt  = array();
		if ( $name ) { $upd['name'] = $name; $fmt[] = '%s'; }
		if ( $mgr )  { $upd['manager_id'] = $mgr; $fmt[] = '%d'; }
		if ( $upd ) {
			$wpdb->update( $wpdb->prefix . 'dm_sectors', $upd, array( 'id' => $id ), $fmt, array( '%d' ) );
		}
		return rest_ensure_response( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dm_sectors WHERE id = %d", $id ), ARRAY_A ) );
	}

	public static function delete_sector( WP_REST_Request $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$wpdb->delete( $wpdb->prefix . 'dm_sectors', array( 'id' => $id ), array( '%d' ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	// ================================================================
	// Recurring types
	// ================================================================

	public static function get_recurring_types( WP_REST_Request $request ) {
		global $wpdb;
		$search    = sanitize_text_field( $request->get_param( 'search' ) );
		$sector_id = (int) $request->get_param( 'sector_id' );
		$uid       = get_current_user_id();
		$settings  = WP_Demandas_Database::get_user_settings( $uid );

		$where  = array( '1=1' );
		$params = array();

		if ( $search ) {
			$where[]  = 'name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		if ( $sector_id ) {
			$where[]  = 'sector_id = %d';
			$params[] = $sector_id;
		} elseif ( ! wp_demandas_is_manager() && $settings['sector_id'] ) {
			$where[]  = 'sector_id = %d';
			$params[] = (int) $settings['sector_id'];
		}

		$sql  = 'SELECT * FROM ' . $wpdb->prefix . 'dm_recurring_types WHERE ' . implode( ' AND ', $where ) . ' ORDER BY name';
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		return rest_ensure_response( $rows );
	}

	public static function create_recurring_type( WP_REST_Request $request ) {
		global $wpdb;
		$uid      = get_current_user_id();
		$settings = WP_Demandas_Database::get_user_settings( $uid );

		$name    = sanitize_text_field( $request->get_param( 'name' ) );
		$sector  = (int) $request->get_param( 'sector_id' ) ?: (int) $settings['sector_id'];
		$average = (float) $request->get_param( 'weekly_average' ) ?: 1.0;

		if ( ! $name ) {
			return new WP_Error( 'missing_name', __( 'Nome é obrigatório.', 'wp-demandas' ), array( 'status' => 400 ) );
		}

		$wpdb->insert(
			$wpdb->prefix . 'dm_recurring_types',
			array( 'name' => $name, 'sector_id' => $sector, 'created_by' => $uid, 'weekly_average' => $average ),
			array( '%s', '%d', '%d', '%f' )
		);
		return rest_ensure_response( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dm_recurring_types WHERE id = %d", $wpdb->insert_id ), ARRAY_A ) );
	}

	public static function update_recurring_type( WP_REST_Request $request ) {
		global $wpdb;
		$id  = (int) $request['id'];
		$upd = array();
		$fmt = array();
		if ( null !== $request->get_param( 'name' ) )           { $upd['name']           = sanitize_text_field( $request->get_param( 'name' ) ); $fmt[] = '%s'; }
		if ( null !== $request->get_param( 'weekly_average' ) ) { $upd['weekly_average']  = (float) $request->get_param( 'weekly_average' ); $fmt[] = '%f'; }
		if ( $upd ) {
			$wpdb->update( $wpdb->prefix . 'dm_recurring_types', $upd, array( 'id' => $id ), $fmt, array( '%d' ) );
		}
		return rest_ensure_response( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dm_recurring_types WHERE id = %d", $id ), ARRAY_A ) );
	}

	public static function delete_recurring_type( WP_REST_Request $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$wpdb->delete( $wpdb->prefix . 'dm_recurring_types', array( 'id' => $id ), array( '%d' ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	// ================================================================
	// Users
	// ================================================================

	public static function get_users( WP_REST_Request $request ) {
		global $wpdb;
		$sector_id = (int) $request->get_param( 'sector_id' );

		// If sector_id is given, return members of that sector by checking user_settings.
		if ( $sector_id ) {
			$user_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT user_id FROM {$wpdb->prefix}dm_user_settings WHERE sector_id = %d",
					$sector_id
				)
			);
		} else {
			// Return all users with dm_manager or dm_member role.
			$args = array(
				'role__in' => array( 'dm_manager', 'dm_member', 'administrator' ),
				'fields'   => array( 'ID', 'display_name', 'user_email' ),
			);
			$users = get_users( $args );
			return rest_ensure_response( array_map( function( $u ) {
				return array(
					'id'           => $u->ID,
					'display_name' => $u->display_name,
					'email'        => $u->user_email,
					'is_manager'   => wp_demandas_is_manager( $u->ID ),
				);
			}, $users ) );
		}

		if ( empty( $user_ids ) ) {
			return rest_ensure_response( array() );
		}

		$users   = get_users( array(
			'include' => $user_ids,
			'fields'  => array( 'ID', 'display_name', 'user_email' ),
		) );

		return rest_ensure_response( array_map( function( $u ) {
			return array(
				'id'           => $u->ID,
				'display_name' => $u->display_name,
				'email'        => $u->user_email,
				'is_manager'   => wp_demandas_is_manager( $u->ID ),
			);
		}, $users ) );
	}

	// ================================================================
	// Settings
	// ================================================================

	public static function get_settings( WP_REST_Request $request ) {
		$uid  = get_current_user_id();
		$row  = WP_Demandas_Database::get_user_settings( $uid );
		$row['settings_json'] = json_decode( $row['settings_json'] ?: '{}', true );
		return rest_ensure_response( $row );
	}

	public static function update_settings( WP_REST_Request $request ) {
		global $wpdb;
		$uid  = get_current_user_id();
		$upd  = array();
		$fmt  = array();

		if ( null !== $request->get_param( 'sector_id' ) ) {
			$upd['sector_id'] = (int) $request->get_param( 'sector_id' );
			$fmt[]            = '%d';
		}
		if ( null !== $request->get_param( 'auto_create_routines' ) ) {
			$upd['auto_create_routines'] = (int) (bool) $request->get_param( 'auto_create_routines' );
			$fmt[]                       = '%d';
		}
		if ( null !== $request->get_param( 'settings_json' ) ) {
			$upd['settings_json'] = wp_json_encode( $request->get_param( 'settings_json' ) );
			$fmt[]                = '%s';
		}

		if ( $upd ) {
			$wpdb->update( $wpdb->prefix . 'dm_user_settings', $upd, array( 'user_id' => $uid ), $fmt, array( '%d' ) );
		}

		return self::get_settings( $request );
	}

	// ================================================================
	// Weekly history snapshots
	// ================================================================

	public static function get_weekly_history( WP_REST_Request $request ) {
		global $wpdb;
		$uid       = get_current_user_id();
		$is_mgr    = wp_demandas_is_manager();
		$week_key  = sanitize_text_field( $request->get_param( 'week_key' ) );
		$sector_id = (int) $request->get_param( 'sector_id' );

		$where  = array( '1=1' );
		$params = array();
		if ( $week_key ) {
			$where[]  = 's.week_key = %s';
			$params[] = $week_key;
		}
		$sql = 'SELECT s.* FROM ' . $wpdb->prefix . 'dm_weekly_snapshots s WHERE ' . implode( ' AND ', $where ) . ' ORDER BY s.created_at DESC';
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $rows as &$row ) {
			$row['snapshot_data'] = json_decode( $row['snapshot_data'], true );
		}

		return rest_ensure_response( $rows );
	}

	// ================================================================
	// Internal helpers
	// ================================================================

	private static function fetch_task( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dm_tasks WHERE id = %d", $id ), ARRAY_A );
	}

	private static function format_task( $task ) {
		if ( ! $task ) {
			return null;
		}
		$task['images'] = json_decode( $task['images'] ?: '[]', true );
		$task['id']     = (int) $task['id'];
		// Attach assignee display name.
		if ( $task['assigned_to'] ) {
			$u                       = get_userdata( (int) $task['assigned_to'] );
			$task['assignee_name']   = $u ? $u->display_name : '';
		}
		if ( $task['created_by'] ) {
			$u                       = get_userdata( (int) $task['created_by'] );
			$task['creator_name']    = $u ? $u->display_name : '';
		}
		return $task;
	}

	private static function type_to_color( $type ) {
		$map = array(
			'routine'           => 'blue',
			'planned'           => 'yellow',
			'urgent'            => 'pink',
			'planned_recurring' => 'yellow',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : 'yellow';
	}
}
