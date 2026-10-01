<?php
if (!defined('ABSPATH')) exit;

class OmniGoCRM_REST {
    private $t;

    public function __construct() {
        $this->t = OmniGoCRM_DB::tables();
        add_action('rest_api_init', array($this, 'routes'));
    }

    private function permission() {
        return current_user_can('read');
    }

    private function manage_permission() {
        return current_user_can('edit_posts');
    }

    private function clean($value) {
        return sanitize_text_field(wp_unslash($value));
    }

    private function phone($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) === 10) $digits = '91' . $digits;
        return $digits;
    }

    private function render_body($body, $lead, $media_url = '') {
        $name = trim($lead->first_name . ' ' . $lead->last_name);
        return str_ireplace(
            array('{first_name}', '{last_name}', '{name}', '{media_url}', '{{agent_name}}'),
            array($lead->first_name, $lead->last_name, $name, $media_url, wp_get_current_user()->display_name),
            $body
        );
    }

    private function resource_config($type) {
        $config = array(
            'contacts' => array(
                'table' => 'contacts',
                'fields' => array('first_name','last_name','company','email','phone')
            ),
            'companies' => array(
                'table' => 'companies',
                'fields' => array('name','email','phone','website','address')
            ),
            'opportunities' => array(
                'table' => 'opportunities',
                'fields' => array('name','company','stage','amount','close_date')
            ),
            'tasks' => array(
                'table' => 'tasks',
                'fields' => array('title','description','status','due_date','related_type','related_id')
            ),
            'products' => array(
                'table' => 'products',
                'fields' => array('name','sku','price','description','active')
            )
        );
        return isset($config[$type]) ? $config[$type] : null;
    }

    private function sanitize_resource($type, $input, $partial = false) {
        $config = $this->resource_config($type);
        if (!$config) return new WP_Error('invalid_resource', 'Invalid resource.', array('status' => 400));

        $data = array();
        foreach ($config['fields'] as $field) {
            if ($partial && !array_key_exists($field, $input)) continue;
            $value = isset($input[$field]) ? $input[$field] : '';

            if (in_array($field, array('amount','price','related_id'), true)) {
                $data[$field] = in_array($field, array('amount','price'), true) ? (float) $value : (int) $value;
            } elseif ($field === 'active') {
                $data[$field] = !empty($value) ? 1 : 0;
            } elseif ($field === 'email') {
                $data[$field] = sanitize_email($value);
            } elseif ($field === 'description' || $field === 'address') {
                $data[$field] = sanitize_textarea_field($value);
            } else {
                $data[$field] = $this->clean($value);
            }
        }

        $required = array(
            'contacts' => 'first_name',
            'companies' => 'name',
            'opportunities' => 'name',
            'tasks' => 'title',
            'products' => 'name'
        );
        if (!$partial && isset($required[$type]) && empty($data[$required[$type]])) {
            return new WP_Error('validation', ucfirst($required[$type]) . ' is required.', array('status' => 400));
        }
        return $data;
    }

    public function routes() {
        register_rest_route('omnigocrm/v1', '/dashboard', array(
            'methods' => 'GET', 'callback' => array($this, 'dashboard'), 'permission_callback' => array($this, 'permission')
        ));

        register_rest_route('omnigocrm/v1', '/leads', array(
            array('methods' => 'GET', 'callback' => array($this, 'leads'), 'permission_callback' => array($this, 'permission')),
            array('methods' => 'POST', 'callback' => array($this, 'create_lead'), 'permission_callback' => array($this, 'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1', '/leads/(?P<id>\d+)', array(
            array('methods' => 'GET', 'callback' => array($this, 'lead'), 'permission_callback' => array($this, 'permission')),
            array('methods' => 'POST', 'callback' => array($this, 'update_lead'), 'permission_callback' => array($this, 'manage_permission')),
            array('methods' => 'DELETE', 'callback' => array($this, 'delete_lead'), 'permission_callback' => array($this, 'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1', '/leads/(?P<id>\d+)/whatsapp', array(
            'methods' => 'POST', 'callback' => array($this, 'whatsapp'), 'permission_callback' => array($this, 'manage_permission')
        ));

        register_rest_route('omnigocrm/v1', '/resource/(?P<type>[a-z_]+)', array(
            array('methods' => 'GET', 'callback' => array($this, 'resource'), 'permission_callback' => array($this, 'permission')),
            array('methods' => 'POST', 'callback' => array($this, 'create_resource'), 'permission_callback' => array($this, 'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1', '/resource/(?P<type>[a-z_]+)/(?P<id>\d+)', array(
            array('methods' => 'GET', 'callback' => array($this, 'resource_item'), 'permission_callback' => array($this, 'permission')),
            array('methods' => 'POST', 'callback' => array($this, 'update_resource'), 'permission_callback' => array($this, 'manage_permission')),
            array('methods' => 'DELETE', 'callback' => array($this, 'delete_resource'), 'permission_callback' => array($this, 'manage_permission'))
        ));

        register_rest_route('omnigocrm/v1', '/templates', array(
            'methods' => 'GET', 'callback' => array($this, 'templates'), 'permission_callback' => array($this, 'permission')
        ));
        register_rest_route('omnigocrm/v1', '/media', array(
            'methods' => 'GET', 'callback' => array($this, 'media'), 'permission_callback' => array($this, 'permission')
        ));
    }

    public function dashboard() {
        global $wpdb;
        $t = $this->t;
        return rest_ensure_response(array(
            'leads' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['leads']}"),
            'contacts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['contacts']}"),
            'opportunities' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['opportunities']}"),
            'revenue' => (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$t['opportunities']}"),
            'tasks' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['tasks']} WHERE status <> 'completed'"),
            'companies' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['companies']}"),
            'products' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['products']}")
        ));
    }

    public function leads($request) {
        global $wpdb;
        $search = sanitize_text_field($request->get_param('search'));
        $limit = min(250, max(1, (int) $request->get_param('per_page') ?: 100));
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->t['leads']} WHERE first_name LIKE %s OR last_name LIKE %s OR company LIKE %s OR email LIKE %s OR phone LIKE %s ORDER BY id DESC LIMIT %d",
                $like, $like, $like, $like, $like, $limit
            ));
        } else {
            $rows = $wpdb->get_results("SELECT * FROM {$this->t['leads']} ORDER BY id DESC LIMIT {$limit}");
        }
        return rest_ensure_response($rows);
    }

    public function lead($request) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['leads']} WHERE id=%d", (int) $request['id']));
        if (!$row) return new WP_Error('not_found', 'Lead not found.', array('status' => 404));
        return rest_ensure_response($row);
    }

    public function create_lead($request) {
        global $wpdb;
        $p = $request->get_json_params();
        $now = current_time('mysql');
        $data = array(
            'first_name' => $this->clean($p['first_name'] ?? ''),
            'last_name' => $this->clean($p['last_name'] ?? ''),
            'company' => $this->clean($p['company'] ?? ''),
            'email' => sanitize_email($p['email'] ?? ''),
            'phone' => $this->clean($p['phone'] ?? ''),
            'source' => $this->clean($p['source'] ?? ''),
            'status' => $this->clean($p['status'] ?? 'new'),
            'value' => (float) ($p['value'] ?? 0),
            'notes' => sanitize_textarea_field($p['notes'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now
        );
        if (!$data['first_name']) return new WP_Error('validation', 'First name is required.', array('status' => 400));
        $wpdb->insert($this->t['leads'], $data);
        return rest_ensure_response(array('id' => $wpdb->insert_id) + $data);
    }

    public function update_lead($request) {
        global $wpdb;
        $p = $request->get_json_params();
        $id = (int) $request['id'];
        $data = array();
        foreach (array('first_name','last_name','company','phone','source','status') as $key) {
            if (isset($p[$key])) $data[$key] = $this->clean($p[$key]);
        }
        if (isset($p['email'])) $data['email'] = sanitize_email($p['email']);
        if (isset($p['value'])) $data['value'] = (float) $p['value'];
        if (isset($p['notes'])) $data['notes'] = sanitize_textarea_field($p['notes']);
        $data['updated_at'] = current_time('mysql');
        $wpdb->update($this->t['leads'], $data, array('id' => $id));
        return $this->lead($request);
    }

    public function delete_lead($request) {
        global $wpdb;
        $id = (int) $request['id'];
        if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['leads']} WHERE id=%d", $id))) {
            return new WP_Error('not_found', 'Lead not found.', array('status' => 404));
        }
        $wpdb->delete($this->t['leads'], array('id' => $id));
        $wpdb->delete($this->t['notes'], array('lead_id' => $id));
        $wpdb->delete($this->t['conversations'], array('lead_id' => $id));
        return rest_ensure_response(array('deleted' => true, 'id' => $id));
    }

    public function resource($request) {
        global $wpdb;
        $config = $this->resource_config($request['type']);
        if (!$config) return new WP_Error('invalid_resource', 'Invalid resource.', array('status' => 400));
        $search = sanitize_text_field($request->get_param('search'));
        $limit = min(250, max(1, (int) $request->get_param('per_page') ?: 100));
        $table = $this->t[$config['table']];
        if ($search) {
            $field = $config['fields'][0];
            $like = '%' . $wpdb->esc_like($search) . '%';
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$field} LIKE %s ORDER BY id DESC LIMIT %d", $like, $limit));
        } else {
            $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}");
        }
        return rest_ensure_response($rows);
    }

    public function resource_item($request) {
        global $wpdb;
        $config = $this->resource_config($request['type']);
        if (!$config) return new WP_Error('invalid_resource', 'Invalid resource.', array('status' => 400));
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t[$config['table']]} WHERE id=%d", (int) $request['id']));
        if (!$row) return new WP_Error('not_found', 'Record not found.', array('status' => 404));
        return rest_ensure_response($row);
    }

    public function create_resource($request) {
        global $wpdb;
        $type = $request['type'];
        $data = $this->sanitize_resource($type, $request->get_json_params(), false);
        if (is_wp_error($data)) return $data;
        $now = current_time('mysql');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $wpdb->insert($this->t[$this->resource_config($type)['table']], $data);
        return $this->resource_item(new WP_REST_Request('GET', '/omnigocrm/v1/resource/' . $type . '/' . $wpdb->insert_id));
    }

    public function update_resource($request) {
        global $wpdb;
        $type = $request['type'];
        $config = $this->resource_config($type);
        if (!$config) return new WP_Error('invalid_resource', 'Invalid resource.', array('status' => 400));
        $data = $this->sanitize_resource($type, $request->get_json_params(), true);
        if (is_wp_error($data)) return $data;
        $data['updated_at'] = current_time('mysql');
        $wpdb->update($this->t[$config['table']], $data, array('id' => (int) $request['id']));
        return $this->resource_item($request);
    }

    public function delete_resource($request) {
        global $wpdb;
        $config = $this->resource_config($request['type']);
        if (!$config) return new WP_Error('invalid_resource', 'Invalid resource.', array('status' => 400));
        $id = (int) $request['id'];
        $wpdb->delete($this->t[$config['table']], array('id' => $id));
        return rest_ensure_response(array('deleted' => true, 'id' => $id));
    }

    public function templates() {
        global $wpdb;
        return rest_ensure_response($wpdb->get_results("SELECT * FROM {$this->t['templates']} WHERE active=1 ORDER BY name"));
    }

    public function media() {
        global $wpdb;
        return rest_ensure_response($wpdb->get_results("SELECT * FROM {$this->t['media']} WHERE active=1 ORDER BY name"));
    }

    public function whatsapp($request) {
        global $wpdb;
        $lead = $this->lead($request);
        if (is_wp_error($lead)) return $lead;
        $lead = $lead->get_data();
        $phone = $this->phone($lead->phone);
        if (!$phone) return new WP_Error('missing_phone', 'Lead has no valid phone number.', array('status' => 400));

        $p = $request->get_json_params();
        $template_id = (int) ($p['template_id'] ?? 0);
        $media_id = (int) ($p['media_id'] ?? 0);
        $body = sanitize_textarea_field($p['message'] ?? '');
        $media_url = '';

        if ($media_id) {
            $media_url = (string) $wpdb->get_var($wpdb->prepare("SELECT url FROM {$this->t['media']} WHERE id=%d AND active=1", $media_id));
        }
        if (!$body && $template_id) {
            $body = (string) $wpdb->get_var($wpdb->prepare("SELECT body FROM {$this->t['templates']} WHERE id=%d AND active=1", $template_id));
        }
        $body = $this->render_body($body, $lead, $media_url);
        if (!$body) return new WP_Error('empty_message', 'Message cannot be empty.', array('status' => 400));

        $url = 'https://wa.me/' . $phone . '?text=' . rawurlencode($body);
        $now = current_time('mysql');
        $conv = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['conversations']} WHERE lead_id=%d AND channel='whatsapp' LIMIT 1", $lead->id));
        if (!$conv) {
            $wpdb->insert($this->t['conversations'], array('lead_id'=>$lead->id,'channel'=>'whatsapp','phone'=>$phone,'last_message'=>$body,'updated_at'=>$now));
            $conv = $wpdb->insert_id;
        } else {
            $wpdb->update($this->t['conversations'], array('last_message'=>$body,'phone'=>$phone,'updated_at'=>$now), array('id'=>$conv));
        }
        $wpdb->insert($this->t['messages'], array(
            'conversation_id'=>$conv,'direction'=>'outbound','body'=>$body,'status'=>'prepared',
            'metadata'=>wp_json_encode(array('delivery_method'=>'click_to_chat','template_id'=>$template_id,'media_id'=>$media_id,'url'=>$url)),
            'created_at'=>$now
        ));
        $wpdb->insert($this->t['audit'], array(
            'action'=>'whatsapp_click_to_chat','object_type'=>'lead','object_id'=>$lead->id,
            'details'=>wp_json_encode(array('phone'=>$phone)),'user_id'=>get_current_user_id(),'created_at'=>$now
        ));
        return rest_ensure_response(array(
            'url'=>$url,'phone'=>$phone,'message'=>$body,
            'note'=>'WhatsApp opens with the message prepared. The user must press Send in WhatsApp.'
        ));
    }
}
