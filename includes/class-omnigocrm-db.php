<?php
if (!defined('ABSPATH')) exit;

class OmniGoCRM_DB {
    public static function tables() {
        global $wpdb;
        $p = $wpdb->prefix . 'omnigo_';
        return array(
            'leads' => $p . 'leads',
            'contacts' => $p . 'contacts',
            'companies' => $p . 'companies',
            'opportunities' => $p . 'opportunities',
            'tasks' => $p . 'tasks',
            'notes' => $p . 'notes',
            'products' => $p . 'products',
            'templates' => $p . 'message_templates',
            'media' => $p . 'media_assets',
            'conversations' => $p . 'conversations',
            'messages' => $p . 'messages',
            'audit' => $p . 'audit_logs'
        );
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $t = self::tables();
        $charset = $wpdb->get_charset_collate();

        $sql = array();
        $sql[] = "CREATE TABLE {$t['leads']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) DEFAULT '',
            company varchar(190) DEFAULT '',
            email varchar(190) DEFAULT '',
            phone varchar(50) DEFAULT '',
            source varchar(100) DEFAULT '',
            status varchar(50) DEFAULT 'new',
            value decimal(15,2) DEFAULT 0,
            notes longtext,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY status (status), KEY email (email)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['contacts']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) DEFAULT '',
            company varchar(190) DEFAULT '',
            email varchar(190) DEFAULT '',
            phone varchar(50) DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY email (email)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['companies']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            email varchar(190) DEFAULT '',
            phone varchar(50) DEFAULT '',
            website varchar(255) DEFAULT '',
            address text,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['opportunities']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            company varchar(190) DEFAULT '',
            stage varchar(100) DEFAULT 'New',
            amount decimal(15,2) DEFAULT 0,
            close_date date DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY stage (stage)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['tasks']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            status varchar(50) DEFAULT 'pending',
            due_date datetime DEFAULT NULL,
            related_type varchar(50) DEFAULT '',
            related_id bigint(20) unsigned DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['notes']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned DEFAULT 0,
            content longtext NOT NULL,
            created_by bigint(20) unsigned DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY lead_id (lead_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['products']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            sku varchar(100) DEFAULT '',
            price decimal(15,2) DEFAULT 0,
            description text,
            active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY sku (sku)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['templates']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            channel varchar(30) DEFAULT 'whatsapp',
            body longtext NOT NULL,
            media_asset_id bigint(20) unsigned DEFAULT 0,
            active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY channel (channel)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['media']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            description text,
            asset_type varchar(30) DEFAULT 'document',
            url text NOT NULL,
            active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['conversations']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned DEFAULT 0,
            channel varchar(30) DEFAULT 'whatsapp',
            phone varchar(50) DEFAULT '',
            last_message text,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY lead_id (lead_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['messages']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned DEFAULT 0,
            direction varchar(20) DEFAULT 'outbound',
            body longtext NOT NULL,
            status varchar(30) DEFAULT 'prepared',
            metadata longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY conversation_id (conversation_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['audit']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action varchar(100) NOT NULL,
            object_type varchar(50) DEFAULT '',
            object_id bigint(20) unsigned DEFAULT 0,
            details longtext,
            user_id bigint(20) unsigned DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY action (action)
        ) $charset;";

        foreach ($sql as $statement) dbDelta($statement);

        $now = current_time('mysql');
        $defaults = array(
            array('Introduction', 'Hello {first_name}, this is {{agent_name}} from OmniGoCRM. I wanted to connect with you.'),
            array('Product follow-up', 'Hello {first_name}, following up regarding your enquiry. Please let me know a convenient time to discuss.'),
            array('Brochure sharing', 'Hello {first_name}, sharing our product brochure with you: {media_url}')
        );
        foreach ($defaults as $item) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['templates']} WHERE name=%s LIMIT 1", $item[0]));
            if (!$exists) {
                $wpdb->insert($t['templates'], array(
                    'name' => $item[0], 'channel' => 'whatsapp', 'body' => $item[1],
                    'active' => 1, 'created_at' => $now, 'updated_at' => $now
                ));
            }
        }
        update_option('omnigocrm_db_version', OMNIGOCRM_VERSION);
    }
}
