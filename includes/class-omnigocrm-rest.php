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

    private function clean($value) {
        return sanitize_text_field(wp_unslash($value));
    }

    private function phone($value) {
        $digits = preg_replace('/\D+/', '', (string)$value);
        if (strlen($digits) === 10) $digits = '91' . $digits;
        return $digits;
    }

    private function render_body($body, $lead, $media_url = '') {
        $name = trim($lead->first_name . ' ' . $lead->last_name);
        $body = str_ireplace(
            array('{first_name}', '{last_name}', '{name}', '{media_url}', '{{agent_name}}'),
            array($lead->first_name, $lead->last_name, $name, $media_url, wp_get_current_user()->display_name),
            $body
        );
        return $body;
    }

    public function routes() {
        register_rest_route('omnigocrm/v1', '/dashboard', array(
            'methods'=>'GET','callback'=>array($this,'dashboard'),'permission_callback'=>array($this,'permission')
        ));
        register_rest_route('omnigocrm/v1', '/leads', array(
            array('methods'=>'GET','callback'=>array($this,'leads'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'create_lead'),'permission_callback'=>array($this,'permission'))
        ));
        register_rest_route('omnigocrm/v1', '/leads/(?P<id>\d+)', array(
            array('methods'=>'GET','callback'=>array($this,'lead'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'update_lead'),'permission_callback'=>array($this,'permission'))
        ));
        register_rest_route('omnigocrm/v1', '/leads/(?P<id>\d+)/whatsapp', array(
            'methods'=>'POST','callback'=>array($this,'whatsapp'),'permission_callback'=>array($this,'permission')
        ));
        register_rest_route('omnigocrm/v1', '/templates', array(
            'methods'=>'GET','callback'=>array($this,'templates'),'permission_callback'=>array($this,'permission')
        ));
        register_rest_route('omnigocrm/v1', '/media', array(
            'methods'=>'GET','callback'=>array($this,'media'),'permission_callback'=>array($this,'permission')
        ));
        register_rest_route('omnigocrm/v1', '/resource/(?P<type>[a-z_]+)', array(
            'methods'=>'GET','callback'=>array($this,'resource'),'permission_callback'=>array($this,'permission')
        ));
    }

    public function dashboard() {
        global $wpdb;
        $t=$this->t;
        return rest_ensure_response(array(
            'leads'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['leads']}"),
            'contacts'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['contacts']}"),
            'opportunities'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['opportunities']}"),
            'revenue'=>(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$t['opportunities']}"),
            'tasks'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['tasks']} WHERE status <> 'completed'")
        ));
    }

    public function leads($request) {
        global $wpdb; $rows=$wpdb->get_results("SELECT * FROM {$this->t['leads']} ORDER BY id DESC LIMIT 250");
        return rest_ensure_response($rows);
    }

    public function lead($request) {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['leads']} WHERE id=%d",$request['id']));
        if (!$row) return new WP_Error('not_found','Lead not found',array('status'=>404));
        return rest_ensure_response($row);
    }

    public function create_lead($request) {
        global $wpdb; $p=$request->get_json_params(); $now=current_time('mysql');
        $data=array(
            'first_name'=>$this->clean($p['first_name'] ?? ''),'last_name'=>$this->clean($p['last_name'] ?? ''),
            'company'=>$this->clean($p['company'] ?? ''),'email'=>sanitize_email($p['email'] ?? ''),
            'phone'=>$this->clean($p['phone'] ?? ''),'source'=>$this->clean($p['source'] ?? ''),
            'status'=>$this->clean($p['status'] ?? 'new'),'value'=>(float)($p['value'] ?? 0),
            'notes'=>sanitize_textarea_field($p['notes'] ?? ''),'created_at'=>$now,'updated_at'=>$now
        );
        if (!$data['first_name']) return new WP_Error('validation','First name is required',array('status'=>400));
        $wpdb->insert($this->t['leads'],$data); return rest_ensure_response(array('id'=>$wpdb->insert_id)+$data);
    }

    public function update_lead($request) {
        global $wpdb; $p=$request->get_json_params(); $id=(int)$request['id'];
        $data=array();
        foreach(array('first_name','last_name','company','phone','source','status') as $key) if(isset($p[$key])) $data[$key]=$this->clean($p[$key]);
        if(isset($p['email'])) $data['email']=sanitize_email($p['email']);
        if(isset($p['value'])) $data['value']=(float)$p['value'];
        if(isset($p['notes'])) $data['notes']=sanitize_textarea_field($p['notes']);
        $data['updated_at']=current_time('mysql');
        $wpdb->update($this->t['leads'],$data,array('id'=>$id)); return $this->lead($request);
    }

    public function templates() {
        global $wpdb; return rest_ensure_response($wpdb->get_results("SELECT * FROM {$this->t['templates']} WHERE active=1 ORDER BY name"));
    }

    public function media() {
        global $wpdb; return rest_ensure_response($wpdb->get_results("SELECT * FROM {$this->t['media']} WHERE active=1 ORDER BY name"));
    }

    public function resource($request) {
        global $wpdb;
        $allowed=array('contacts','companies','opportunities','tasks','products');
        $type=$request['type']; if(!in_array($type,$allowed,true)) return new WP_Error('invalid_resource','Invalid resource',array('status'=>400));
        $table=$this->t[$type]; return rest_ensure_response($wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 250"));
    }

    public function whatsapp($request) {
        global $wpdb;
        $lead=$this->lead($request); if(is_wp_error($lead)) return $lead;
        $lead=$lead->get_data(); $phone=$this->phone($lead->phone);
        if (!$phone) return new WP_Error('missing_phone','Lead has no valid phone number',array('status'=>400));
        $p=$request->get_json_params(); $template_id=(int)($p['template_id'] ?? 0); $media_id=(int)($p['media_id'] ?? 0);
        $body=sanitize_textarea_field($p['message'] ?? '');
        $media_url='';
        if($media_id) $media_url=(string)$wpdb->get_var($wpdb->prepare("SELECT url FROM {$this->t['media']} WHERE id=%d AND active=1",$media_id));
        if(!$body && $template_id) $body=(string)$wpdb->get_var($wpdb->prepare("SELECT body FROM {$this->t['templates']} WHERE id=%d AND active=1",$template_id));
        $body=$this->render_body($body,$lead,$media_url);
        $url='https://wa.me/'.$phone.'?text='.rawurlencode($body);
        $now=current_time('mysql');
        $conv=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['conversations']} WHERE lead_id=%d AND channel='whatsapp' LIMIT 1",$lead->id));
        if(!$conv){$wpdb->insert($this->t['conversations'],array('lead_id'=>$lead->id,'channel'=>'whatsapp','phone'=>$phone,'last_message'=>$body,'updated_at'=>$now));$conv=$wpdb->insert_id;}
        else $wpdb->update($this->t['conversations'],array('last_message'=>$body,'phone'=>$phone,'updated_at'=>$now),array('id'=>$conv));
        $wpdb->insert($this->t['messages'],array('conversation_id'=>$conv,'direction'=>'outbound','body'=>$body,'status'=>'prepared','metadata'=>wp_json_encode(array('delivery_method'=>'click_to_chat','template_id'=>$template_id,'media_id'=>$media_id,'url'=>$url)),'created_at'=>$now));
        $wpdb->insert($this->t['audit'],array('action'=>'whatsapp_click_to_chat','object_type'=>'lead','object_id'=>$lead->id,'details'=>wp_json_encode(array('phone'=>$phone)),'user_id'=>get_current_user_id(),'created_at'=>$now));
        return rest_ensure_response(array('url'=>$url,'phone'=>$phone,'message'=>$body,'note'=>'WhatsApp opens with the message prepared. The user must press Send in WhatsApp.'));
    }
}
