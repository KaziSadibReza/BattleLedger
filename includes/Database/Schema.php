<?php
namespace BattleLedger\Database;

/**
 * Database schema manager
 */
class Schema {
    
    /**
     * Create all custom tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tournaments table
        $table_tournaments = $wpdb->prefix . 'bl_tournaments';
        $sql_tournaments = "CREATE TABLE $table_tournaments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description longtext,
            game_type varchar(100),
            status varchar(50) DEFAULT 'draft',
            start_date datetime,
            end_date datetime,
            max_participants int(11),
            entry_fee decimal(10,2) DEFAULT 0,
            prize_pool decimal(10,2) DEFAULT 0,
            settings longtext,
            created_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY start_date (start_date),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Tournament participants table
        $table_participants = $wpdb->prefix . 'bl_tournament_participants';
        $sql_participants = "CREATE TABLE $table_participants (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tournament_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            team_name varchar(255),
            status varchar(50) DEFAULT 'registered',
            `rank` int(11),
            score decimal(10,2) DEFAULT 0,
            slots int(11) DEFAULT 1,
            metadata longtext,
            registered_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tournament_id (tournament_id),
            KEY user_id (user_id),
            KEY status (status),
            UNIQUE KEY tournament_user (tournament_id, user_id)
        ) $charset_collate;";
        
        // Matches table
        $table_matches = $wpdb->prefix . 'bl_matches';
        $sql_matches = "CREATE TABLE $table_matches (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tournament_id bigint(20) unsigned NOT NULL,
            round int(11) NOT NULL,
            match_number int(11) NOT NULL,
            participant1_id bigint(20) unsigned,
            participant2_id bigint(20) unsigned,
            winner_id bigint(20) unsigned,
            status varchar(50) DEFAULT 'scheduled',
            scheduled_at datetime,
            completed_at datetime,
            score_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tournament_id (tournament_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";
        
        // Tournament logs table
        $table_logs = $wpdb->prefix . 'bl_tournament_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tournament_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned,
            action varchar(100) NOT NULL,
            description text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tournament_id (tournament_id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Wallets table - stores user wallet balances
        $table_wallets = $wpdb->prefix . 'bl_wallets';
        $sql_wallets = "CREATE TABLE $table_wallets (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            balance decimal(12,2) DEFAULT 0.00,
            currency varchar(3) DEFAULT 'USD',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Wallet transactions table - stores all wallet transactions
        $table_wallet_transactions = $wpdb->prefix . 'bl_wallet_transactions';
        $sql_wallet_transactions = "CREATE TABLE $table_wallet_transactions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            wallet_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            amount decimal(12,2) NOT NULL,
            balance_after decimal(12,2) NOT NULL,
            description text,
            reference_type varchar(50),
            reference_id bigint(20) unsigned,
            created_by bigint(20) unsigned,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY wallet_id (wallet_id),
            KEY user_id (user_id),
            KEY type (type),
            KEY reference_type (reference_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Game rules table - stores game rule configurations
        $table_game_rules = $wpdb->prefix . 'bl_game_rules';
        $sql_game_rules = "CREATE TABLE $table_game_rules (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            game_name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            game_icon varchar(500),
            game_image varchar(500),
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            all_maps longtext,
            all_team_modes longtext,
            all_player_counts longtext,
            player_fields longtext,
            available_settings longtext,
            game_modes longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        // Withdrawal requests table - stores all withdrawal requests
        $table_withdrawal_requests = $wpdb->prefix . 'bl_withdrawal_requests';
        $sql_withdrawal_requests = "CREATE TABLE $table_withdrawal_requests (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            amount decimal(12,2) NOT NULL,
            method_id varchar(100) NOT NULL,
            method_name varchar(255) NOT NULL,
            method_details longtext,
            status varchar(20) NOT NULL DEFAULT 'pending',
            transaction_id bigint(20) unsigned,
            refund_transaction_id bigint(20) unsigned,
            admin_note text,
            processed_by bigint(20) unsigned,
            processed_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Finished tournaments table — immutable snapshots created each time winners are assigned
        $table_finished = $wpdb->prefix . 'bl_finished_tournaments';
        $sql_finished = "CREATE TABLE $table_finished (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tournament_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description longtext,
            game_type varchar(100),
            start_date datetime,
            end_date datetime,
            max_participants int(11),
            entry_fee decimal(10,2) DEFAULT 0,
            prize_pool decimal(10,2) DEFAULT 0,
            settings longtext,
            participants longtext,
            participant_count int(11) DEFAULT 0,
            winners longtext,
            finished_by bigint(20) unsigned,
            finished_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tournament_id (tournament_id),
            KEY game_type (game_type),
            KEY finished_at (finished_at)
        ) $charset_collate;";

        // Notifications table — stores admin & user in-app notifications
        $table_notifications = $wpdb->prefix . 'bl_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            icon varchar(50) DEFAULT '',
            link varchar(500) DEFAULT '',
            is_read tinyint(1) DEFAULT 0,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Execute table creation
        dbDelta($sql_tournaments);
        dbDelta($sql_participants);
        dbDelta($sql_matches);
        dbDelta($sql_logs);
        dbDelta($sql_wallets);
        dbDelta($sql_wallet_transactions);
        dbDelta($sql_withdrawal_requests);
        dbDelta($sql_game_rules);
        dbDelta($sql_finished);
        dbDelta($sql_notifications);
    }
    
    /**
     * Drop all custom tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'bl_tournaments',
            $wpdb->prefix . 'bl_tournament_participants',
            $wpdb->prefix . 'bl_matches',
            $wpdb->prefix . 'bl_tournament_logs',
            $wpdb->prefix . 'bl_wallets',
            $wpdb->prefix . 'bl_wallet_transactions',
            $wpdb->prefix . 'bl_withdrawal_requests',
            $wpdb->prefix . 'bl_game_rules',
            $wpdb->prefix . 'bl_finished_tournaments',
            $wpdb->prefix . 'bl_notifications',
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('battle_ledger_db_version');
    }
}
