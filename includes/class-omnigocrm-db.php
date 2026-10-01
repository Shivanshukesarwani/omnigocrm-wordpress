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
            'pipelines' => $p . 'pipelines',
            'stages' => $p . 'pipeline_stages',
            'templates' => $p . 'message_templates',
            'media' => $p . 'media_assets',
            'conversations' => $p . 'conversations',
            'messages' => $p . 'messages',
            'calls' => $p . 'calls',
            'campaigns' => $p . 'campaigns',
            'automations' => $p . 'automations',
            'automation_jobs' => $p . 'automation_jobs',
            'notifications' => $p . 'notifications',
            'audit' => $p . 'audit_logs',
            'tags' => $p . 'tags',
            'entity_tags' => $p . 'entity_tags',
            'quotes' => $p . 'quotes',
            'quote_items' => $p . 'quote_items',
            'orders' => $p . 'orders',
            'order_items' => $p . 'order_items',
            'invoices' => $p . 'invoices',
            'payments' => $p . 'payments',
            'plans' => $p . 'plans',
            'subscriptions' => $p . 'subscriptions',
            'integrations' => $p . 'integrations'
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
            job_title varchar(190) DEFAULT '',
            website varchar(255) DEFAULT '',
            location varchar(190) DEFAULT '',
            industry varchar(120) DEFAULT '',
            source varchar(100) DEFAULT 'manual',
            status varchar(50) DEFAULT 'new',
            score int(11) DEFAULT 0,
            value decimal(15,2) DEFAULT 0,
            expected_close_date date DEFAULT NULL,
            owner_id bigint(20) unsigned DEFAULT 0,
            custom_fields longtext,
            notes longtext,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY status (status), KEY email (email), KEY owner_id (owner_id), KEY source (source)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['contacts']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) DEFAULT '',
            company varchar(190) DEFAULT '',
            email varchar(190) DEFAULT '',
            phone varchar(50) DEFAULT '',
            job_title varchar(190) DEFAULT '',
            website varchar(255) DEFAULT '',
            location varchar(190) DEFAULT '',
            account_id bigint(20) unsigned DEFAULT 0,
            owner_id bigint(20) unsigned DEFAULT 0,
            custom_fields longtext,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY email (email), KEY account_id (account_id), KEY owner_id (owner_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['companies']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            email varchar(190) DEFAULT '',
            phone varchar(50) DEFAULT '',
            website varchar(255) DEFAULT '',
            industry varchar(120) DEFAULT '',
            address text,
            owner_id bigint(20) unsigned DEFAULT 0,
            custom_fields longtext,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY owner_id (owner_id), KEY name (name)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['pipelines']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            description text,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['stages']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            pipeline_id bigint(20) unsigned NOT NULL,
            name varchar(120) NOT NULL,
            position int(11) DEFAULT 1,
            probability int(11) DEFAULT 0,
            stage_color varchar(30) DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY pipeline_id (pipeline_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['opportunities']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            company varchar(190) DEFAULT '',
            amount decimal(15,2) DEFAULT 0,
            currency varchar(3) DEFAULT 'INR',
            stage varchar(100) DEFAULT 'New',
            probability int(11) DEFAULT 0,
            close_date date DEFAULT NULL,
            expected_close_date date DEFAULT NULL,
            pipeline_id bigint(20) unsigned DEFAULT 0,
            account_id bigint(20) unsigned DEFAULT 0,
            contact_id bigint(20) unsigned DEFAULT 0,
            owner_id bigint(20) unsigned DEFAULT 0,
            description text,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY stage (stage), KEY pipeline_id (pipeline_id), KEY account_id (account_id), KEY contact_id (contact_id), KEY owner_id (owner_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['tasks']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            status varchar(50) DEFAULT 'open',
            priority varchar(30) DEFAULT 'normal',
            due_date datetime DEFAULT NULL,
            due_at datetime DEFAULT NULL,
            assigned_to bigint(20) unsigned DEFAULT 0,
            related_type varchar(50) DEFAULT '',
            related_id bigint(20) unsigned DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY status (status), KEY due_at (due_at), KEY assigned_to (assigned_to)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['notes']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            body longtext NOT NULL,
            lead_id bigint(20) unsigned DEFAULT 0,
            related_type varchar(50) DEFAULT '',
            related_id bigint(20) unsigned DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY lead_id (lead_id), KEY related (related_type, related_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['products']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            sku varchar(100) DEFAULT '',
            description text,
            price decimal(15,2) DEFAULT 0,
            unit_price decimal(15,2) DEFAULT 0,
            currency varchar(3) DEFAULT 'INR',
            tax_rate decimal(6,2) DEFAULT 0,
            active tinyint(1) DEFAULT 1,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY sku (sku)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['templates']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            channel varchar(30) DEFAULT 'whatsapp',
            subject varchar(255) DEFAULT '',
            body longtext NOT NULL,
            media_asset_id bigint(20) unsigned DEFAULT 0,
            active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY channel (channel), KEY active (active)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['media']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            description text,
            asset_type varchar(30) DEFAULT 'document',
            url text NOT NULL,
            thumbnail_url text,
            mime_type varchar(120) DEFAULT '',
            active tinyint(1) DEFAULT 1,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['conversations']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned DEFAULT 0,
            contact_id bigint(20) unsigned DEFAULT 0,
            channel varchar(30) DEFAULT 'whatsapp',
            external_contact varchar(255) DEFAULT '',
            phone varchar(50) DEFAULT '',
            subject varchar(255) DEFAULT '',
            status varchar(30) DEFAULT 'open',
            assigned_to bigint(20) unsigned DEFAULT 0,
            last_message text,
            last_message_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY lead_id (lead_id), KEY contact_id (contact_id), KEY channel (channel), KEY updated_at (updated_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['messages']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            sender_id bigint(20) unsigned DEFAULT 0,
            direction varchar(20) DEFAULT 'outbound',
            message_type varchar(30) DEFAULT 'text',
            body longtext NOT NULL,
            media_url text,
            external_id varchar(190) DEFAULT '',
            status varchar(30) DEFAULT 'prepared',
            metadata longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY conversation_id (conversation_id), KEY created_at (created_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['calls']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) unsigned DEFAULT 0,
            lead_id bigint(20) unsigned DEFAULT 0,
            direction varchar(20) DEFAULT 'outbound',
            status varchar(30) DEFAULT 'completed',
            phone varchar(50) DEFAULT '',
            duration_seconds int(11) DEFAULT 0,
            recording_url text,
            started_at datetime DEFAULT NULL,
            ended_at datetime DEFAULT NULL,
            agent_id bigint(20) unsigned DEFAULT 0,
            notes text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY contact_id (contact_id), KEY lead_id (lead_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['campaigns']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            channel varchar(30) DEFAULT 'whatsapp',
            status varchar(30) DEFAULT 'draft',
            audience_type varchar(50) DEFAULT 'leads',
            settings longtext,
            scheduled_at datetime DEFAULT NULL,
            sent_count int(11) DEFAULT 0,
            delivered_count int(11) DEFAULT 0,
            failed_count int(11) DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY status (status), KEY scheduled_at (scheduled_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['automations']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            trigger_type varchar(80) NOT NULL,
            active tinyint(1) DEFAULT 0,
            definition longtext,
            description text,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY trigger_type (trigger_type), KEY active (active)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['automation_jobs']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            automation_id bigint(20) unsigned DEFAULT 0,
            status varchar(30) DEFAULT 'queued',
            run_at datetime NOT NULL,
            payload longtext,
            error text,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY status (status), KEY run_at (run_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['notifications']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT 0,
            type varchar(50) DEFAULT 'info',
            title varchar(190) NOT NULL,
            body text,
            read_at datetime DEFAULT NULL,
            data longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY user_id (user_id), KEY read_at (read_at), KEY created_at (created_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['audit']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action varchar(100) NOT NULL,
            object_type varchar(80) DEFAULT '',
            object_id bigint(20) unsigned DEFAULT 0,
            details longtext,
            user_id bigint(20) unsigned DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY action (action), KEY object (object_type, object_id), KEY created_at (created_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['tags']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            color varchar(30) DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY name (name)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['entity_tags']} (
            tag_id bigint(20) unsigned NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (tag_id, entity_type, entity_id),
            KEY entity (entity_type, entity_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['quotes']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_number varchar(80) NOT NULL,
            company_id bigint(20) unsigned DEFAULT 0,
            contact_id bigint(20) unsigned DEFAULT 0,
            status varchar(30) DEFAULT 'draft',
            currency varchar(3) DEFAULT 'INR',
            subtotal decimal(15,2) DEFAULT 0,
            tax_total decimal(15,2) DEFAULT 0,
            total decimal(15,2) DEFAULT 0,
            valid_until date DEFAULT NULL,
            notes text,
            created_by bigint(20) unsigned DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY quote_number (quote_number), KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['quote_items']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned DEFAULT 0,
            description text NOT NULL,
            quantity decimal(12,2) DEFAULT 1,
            unit_price decimal(15,2) DEFAULT 0,
            tax_rate decimal(6,2) DEFAULT 0,
            total decimal(15,2) DEFAULT 0,
            PRIMARY KEY (id), KEY quote_id (quote_id), KEY product_id (product_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['orders']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_number varchar(80) NOT NULL,
            quote_id bigint(20) unsigned DEFAULT 0,
            company_id bigint(20) unsigned DEFAULT 0,
            contact_id bigint(20) unsigned DEFAULT 0,
            status varchar(30) DEFAULT 'pending',
            currency varchar(3) DEFAULT 'INR',
            subtotal decimal(15,2) DEFAULT 0,
            tax_total decimal(15,2) DEFAULT 0,
            total decimal(15,2) DEFAULT 0,
            notes text,
            created_by bigint(20) unsigned DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY order_number (order_number), KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['order_items']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned DEFAULT 0,
            description text NOT NULL,
            quantity decimal(12,2) DEFAULT 1,
            unit_price decimal(15,2) DEFAULT 0,
            tax_rate decimal(6,2) DEFAULT 0,
            total decimal(15,2) DEFAULT 0,
            PRIMARY KEY (id), KEY order_id (order_id), KEY product_id (product_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['invoices']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_number varchar(80) NOT NULL,
            order_id bigint(20) unsigned DEFAULT 0,
            company_id bigint(20) unsigned DEFAULT 0,
            status varchar(30) DEFAULT 'draft',
            currency varchar(3) DEFAULT 'INR',
            subtotal decimal(15,2) DEFAULT 0,
            tax_total decimal(15,2) DEFAULT 0,
            total decimal(15,2) DEFAULT 0,
            due_date date DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            notes text,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY invoice_number (invoice_number), KEY status (status), KEY due_date (due_date)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['payments']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) unsigned DEFAULT 0,
            amount decimal(15,2) DEFAULT 0,
            currency varchar(3) DEFAULT 'INR',
            method varchar(50) DEFAULT '',
            status varchar(30) DEFAULT 'pending',
            provider varchar(100) DEFAULT '',
            provider_reference varchar(190) DEFAULT '',
            paid_at datetime DEFAULT NULL,
            notes text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY invoice_id (invoice_id), KEY status (status), KEY paid_at (paid_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['plans']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(80) NOT NULL,
            name varchar(120) NOT NULL,
            description text,
            monthly_price decimal(15,2) DEFAULT 0,
            currency varchar(3) DEFAULT 'INR',
            limits longtext,
            features longtext,
            active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY code (code)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['subscriptions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            plan_id bigint(20) unsigned NOT NULL,
            status varchar(30) DEFAULT 'trialing',
            provider varchar(80) DEFAULT '',
            provider_subscription_id varchar(190) DEFAULT '',
            current_period_start datetime DEFAULT NULL,
            current_period_end datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY plan_id (plan_id), KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE {$t['integrations']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(80) NOT NULL,
            name varchar(190) NOT NULL,
            status varchar(30) DEFAULT 'disabled',
            config longtext,
            secrets_ref varchar(190) DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY type (type), KEY status (status)
        ) $charset;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        $now = current_time('mysql');

        $defaults = array(
            array('Introduction', 'Hello {first_name}, this is {{agent_name}} from OmniGoCRM. I wanted to connect with you.', 'whatsapp'),
            array('Product follow-up', 'Hello {first_name}, following up regarding your enquiry. Please let me know a convenient time to discuss.', 'whatsapp'),
            array('Brochure sharing', 'Hello {first_name}, sharing our product brochure with you: {media_url}', 'whatsapp')
        );
        foreach ($defaults as $item) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['templates']} WHERE name=%s LIMIT 1", $item[0]));
            if (!$exists) {
                $wpdb->insert($t['templates'], array(
                    'name'=>$item[0], 'channel'=>$item[2], 'body'=>$item[1],
                    'active'=>1, 'created_at'=>$now, 'updated_at'=>$now
                ));
            }
        }

        $pipeline_id = $wpdb->get_var("SELECT id FROM {$t['pipelines']} WHERE is_default=1 ORDER BY id LIMIT 1");
        if (!$pipeline_id) {
            $wpdb->insert($t['pipelines'], array(
                'name'=>'Default Sales Pipeline',
                'description'=>'Standard CRM sales pipeline',
                'is_default'=>1,
                'created_at'=>$now,
                'updated_at'=>$now
            ));
            $pipeline_id = $wpdb->insert_id;
        }
        $stage_names = array(
            array('New',10), array('Qualified',30), array('Proposal',60),
            array('Negotiation',80), array('Won',100), array('Lost',0)
        );
        foreach ($stage_names as $i => $stage) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['stages']} WHERE pipeline_id=%d AND position=%d", $pipeline_id, $i+1));
            if (!$exists) {
                $wpdb->insert($t['stages'], array(
                    'pipeline_id'=>$pipeline_id, 'name'=>$stage[0], 'position'=>$i+1,
                    'probability'=>$stage[1], 'stage_color'=>'',
                    'created_at'=>$now, 'updated_at'=>$now
                ));
            }
        }

        $plan = $wpdb->get_var("SELECT id FROM {$t['plans']} WHERE code='community' LIMIT 1");
        if (!$plan) {
            $wpdb->insert($t['plans'], array(
                'code'=>'community',
                'name'=>'Community',
                'description'=>'WordPress self-hosted CRM plan',
                'monthly_price'=>0,
                'currency'=>'INR',
                'limits'=>wp_json_encode(array('users'=>5,'leads'=>10000,'integrations'=>3)),
                'features'=>wp_json_encode(array('crm','inbox','automation','sales')),
                'active'=>1,
                'created_at'=>$now
            ));
        }

        update_option('omnigocrm_db_version', OMNIGOCRM_VERSION);
    }
}
