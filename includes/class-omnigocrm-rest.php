<?php
if (!defined('ABSPATH')) exit;

class OmniGoCRM_REST {
    private $t;

    public function __construct() {
        $this->t = OmniGoCRM_DB::tables();
        add_action('rest_api_init', array($this, 'routes'));
        add_action('omnigocrm_process_jobs', array($this, 'process_jobs'));
    }

    private function permission() {
        return current_user_can('read');
    }

    private function manage_permission() {
        return current_user_can('edit_posts');
    }

    private function admin_permission() {
        return current_user_can('manage_options');
    }

    private function role_permission() {
        return current_user_can('edit_posts');
    }

    private function clean($value) {
        return sanitize_text_field(wp_unslash($value));
    }

    private function textarea($value) {
        return sanitize_textarea_field(wp_unslash($value));
    }

    private function json_value($value) {
        if (is_array($value) || is_object($value)) return wp_json_encode($value);
        $value = wp_unslash($value);
        $decoded = json_decode($value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? wp_json_encode($decoded) : wp_json_encode((string) $value);
    }

    private function bool_value($value) {
        return !empty($value) ? 1 : 0;
    }

    private function phone($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) === 10) $digits = '91' . $digits;
        if (strlen($digits) === 11 && substr($digits, 0, 1) === '0') $digits = '91' . substr($digits, 1);
        return (strlen($digits) >= 10 && strlen($digits) <= 15) ? $digits : '';
    }

    private function render_body($body, $lead, $media_url = '') {
        $name = trim($lead->first_name . ' ' . $lead->last_name);
        return str_ireplace(
            array('{first_name}', '{last_name}', '{name}', '{media_url}', '{{agent_name}}'),
            array($lead->first_name, $lead->last_name, $name, $media_url, wp_get_current_user()->display_name),
            $body
        );
    }

    private function audit($action, $type = '', $id = 0, $details = '') {
        global $wpdb;
        $wpdb->insert($this->t['audit'], array(
            'action'=>$action,
            'object_type'=>$type,
            'object_id'=>(int) $id,
            'details'=>is_string($details) ? $details : wp_json_encode($details),
            'user_id'=>get_current_user_id(),
            'created_at'=>current_time('mysql')
        ));
    }

    private function cfg($type) {
        $map = array(
            'leads'=>array(
                'table'=>'leads','fields'=>array('first_name','last_name','company','email','phone','job_title','website','location','industry','source','status','score','value','expected_close_date','owner_id','custom_fields','notes'),
                'search'=>array('first_name','last_name','company','email','phone','job_title','source','status'),'required'=>'first_name','numbers'=>array('score','value','owner_id'),'json'=>array('custom_fields')
            ),
            'contacts'=>array(
                'table'=>'contacts','fields'=>array('first_name','last_name','company','email','phone','job_title','website','location','account_id','owner_id','custom_fields'),
                'search'=>array('first_name','last_name','company','email','phone','job_title'),'required'=>'first_name','numbers'=>array('account_id','owner_id'),'json'=>array('custom_fields')
            ),
            'companies'=>array(
                'table'=>'companies','fields'=>array('name','email','phone','website','industry','address','owner_id','custom_fields'),
                'search'=>array('name','email','phone','website','industry'),'required'=>'name','numbers'=>array('owner_id'),'json'=>array('custom_fields')
            ),
            'accounts'=>array(
                'table'=>'companies','fields'=>array('name','email','phone','website','industry','address','owner_id','custom_fields'),
                'search'=>array('name','email','phone','website','industry'),'required'=>'name','numbers'=>array('owner_id'),'json'=>array('custom_fields')
            ),
            'opportunities'=>array(
                'table'=>'opportunities','fields'=>array('name','company','amount','currency','stage','probability','close_date','expected_close_date','pipeline_id','account_id','contact_id','owner_id','description'),
                'search'=>array('name','company','stage'),'required'=>'name','numbers'=>array('amount','probability','pipeline_id','account_id','contact_id','owner_id')
            ),
            'tasks'=>array(
                'table'=>'tasks','fields'=>array('title','description','status','priority','due_date','due_at','assigned_to','related_type','related_id'),
                'search'=>array('title','description','status','priority'),'required'=>'title','numbers'=>array('assigned_to','related_id')
            ),
            'notes'=>array(
                'table'=>'notes','fields'=>array('body','lead_id','related_type','related_id','created_by'),
                'search'=>array('body','related_type'),'required'=>'body','numbers'=>array('lead_id','related_id','created_by')
            ),
            'products'=>array(
                'table'=>'products','fields'=>array('name','sku','description','price','unit_price','currency','tax_rate','active','is_active'),
                'search'=>array('name','sku','description'),'required'=>'name','numbers'=>array('price','unit_price','tax_rate'),'bools'=>array('active','is_active')
            ),
            'calls'=>array(
                'table'=>'calls','fields'=>array('contact_id','lead_id','direction','status','phone','duration_seconds','recording_url','started_at','ended_at','agent_id','notes'),
                'search'=>array('phone','direction','status','notes'),'numbers'=>array('contact_id','lead_id','duration_seconds','agent_id')
            ),
            'campaigns'=>array(
                'table'=>'campaigns','fields'=>array('name','channel','status','audience_type','settings','scheduled_at','sent_count','delivered_count','failed_count'),
                'search'=>array('name','channel','status','audience_type'),'required'=>'name','numbers'=>array('sent_count','delivered_count','failed_count'),'json'=>array('settings')
            ),
            'automations'=>array(
                'table'=>'automations','fields'=>array('name','trigger_type','active','definition','description'),
                'search'=>array('name','trigger_type','description'),'required'=>'name','bools'=>array('active'),'json'=>array('definition')
            ),
            'integrations'=>array(
                'table'=>'integrations','fields'=>array('type','name','status','config','secrets_ref'),
                'search'=>array('type','name','status'),'required'=>'name','json'=>array('config')
            ),
            'quotes'=>array(
                'table'=>'quotes','fields'=>array('quote_number','company_id','contact_id','status','currency','subtotal','tax_total','total','valid_until','notes','created_by'),
                'search'=>array('quote_number','status'),'required'=>'quote_number','numbers'=>array('company_id','contact_id','subtotal','tax_total','total','created_by')
            ),
            'orders'=>array(
                'table'=>'orders','fields'=>array('order_number','quote_id','company_id','contact_id','status','currency','subtotal','tax_total','total','notes','created_by'),
                'search'=>array('order_number','status'),'required'=>'order_number','numbers'=>array('quote_id','company_id','contact_id','subtotal','tax_total','total','created_by')
            ),
            'invoices'=>array(
                'table'=>'invoices','fields'=>array('invoice_number','order_id','company_id','status','currency','subtotal','tax_total','total','due_date','paid_at','notes'),
                'search'=>array('invoice_number','status'),'required'=>'invoice_number','numbers'=>array('order_id','company_id','subtotal','tax_total','total')
            ),
            'payments'=>array(
                'table'=>'payments','fields'=>array('invoice_id','amount','currency','method','status','provider','provider_reference','paid_at','notes'),
                'search'=>array('method','status','provider','provider_reference'),'numbers'=>array('invoice_id','amount')
            )
        );
        return isset($map[$type]) ? $map[$type] : null;
    }

    private function sanitize_data($type, $input, $partial = false) {
        $cfg = $this->cfg($type);
        if (!$cfg) return new WP_Error('invalid_resource','Invalid resource.',array('status'=>400));
        $data = array();
        foreach ($cfg['fields'] as $field) {
            if ($partial && !array_key_exists($field, $input)) continue;
            $value = isset($input[$field]) ? $input[$field] : '';
            if (isset($cfg['json']) && in_array($field, $cfg['json'], true)) {
                $data[$field] = $this->json_value($value);
            } elseif (isset($cfg['bools']) && in_array($field, $cfg['bools'], true)) {
                $data[$field] = $this->bool_value($value);
            } elseif (isset($cfg['numbers']) && in_array($field, $cfg['numbers'], true)) {
                $data[$field] = (float) $value;
                if (in_array($field, array('owner_id','account_id','contact_id','pipeline_id','assigned_to','related_id','created_by','lead_id','company_id','quote_id','order_id','invoice_id','agent_id'), true)) {
                    $data[$field] = (int) $value;
                }
            } elseif (in_array($field, array('email'), true)) {
                $data[$field] = sanitize_email($value);
            } elseif (in_array($field, array('description','address','notes','body'), true)) {
                $data[$field] = $this->textarea($value);
            } elseif (in_array($field, array('source','status','stage','priority','channel','type','direction','method','provider','currency','scheduled_at','due_date','due_at','started_at','ended_at','paid_at','valid_until','expected_close_date','close_date','job_title','website','location','industry','company','name','first_name','last_name','phone','title','sku','recording_url','related_type','audience_type','trigger_type','secrets_ref'), true)) {
                $data[$field] = $this->clean($value);
            } else {
                $data[$field] = $this->clean($value);
            }
        }
        if (!$partial && !empty($cfg['required']) && empty($data[$cfg['required']])) {
            return new WP_Error('validation', ucfirst(str_replace('_',' ',$cfg['required'])) . ' is required.', array('status'=>400));
        }
        return $data;
    }

    public function routes() {
        register_rest_route('omnigocrm/v1','/dashboard',array('methods'=>'GET','callback'=>array($this,'dashboard'),'permission_callback'=>array($this,'permission')));
        register_rest_route('omnigocrm/v1','/reports/summary',array('methods'=>'GET','callback'=>array($this,'reports'),'permission_callback'=>array($this,'permission')));

        foreach (array_keys(array_filter(array(
            'leads'=>$this->cfg('leads'),'contacts'=>$this->cfg('contacts'),'companies'=>$this->cfg('companies'),'accounts'=>$this->cfg('accounts'),
            'opportunities'=>$this->cfg('opportunities'),'tasks'=>$this->cfg('tasks'),'notes'=>$this->cfg('notes'),'products'=>$this->cfg('products'),
            'calls'=>$this->cfg('calls'),'campaigns'=>$this->cfg('campaigns'),'automations'=>$this->cfg('automations'),'integrations'=>$this->cfg('integrations'),
            'quotes'=>$this->cfg('quotes'),'orders'=>$this->cfg('orders'),'invoices'=>$this->cfg('invoices'),'payments'=>$this->cfg('payments')
        )))) as $type) {
            register_rest_route('omnigocrm/v1','/'.$type,array(
                array('methods'=>'GET','callback'=>array($this,'resource'),'permission_callback'=>array($this,'permission')),
                array('methods'=>'POST','callback'=>array($this,'create_resource'),'permission_callback'=>array($this,'manage_permission'))
            ));
            register_rest_route('omnigocrm/v1','/'.$type.'/(?P<id>\d+)',array(
                array('methods'=>'GET','callback'=>array($this,'resource_item'),'permission_callback'=>array($this,'permission')),
                array('methods'=>'POST','callback'=>array($this,'update_resource'),'permission_callback'=>array($this,'manage_permission')),
                array('methods'=>'PATCH','callback'=>array($this,'update_resource'),'permission_callback'=>array($this,'manage_permission')),
                array('methods'=>'DELETE','callback'=>array($this,'delete_resource'),'permission_callback'=>array($this,'manage_permission'))
            ));
        }

        register_rest_route('omnigocrm/v1','/leads/(?P<id>\d+)/whatsapp/prepare',array('methods'=>'POST','callback'=>array($this,'whatsapp'),'permission_callback'=>array($this,'manage_permission')));
        register_rest_route('omnigocrm/v1','/whatsapp/templates',array(
            array('methods'=>'GET','callback'=>array($this,'templates'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'create_template'),'permission_callback'=>array($this,'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1','/whatsapp/templates/(?P<id>\d+)',array(
            array('methods'=>'POST','callback'=>array($this,'update_template'),'permission_callback'=>array($this,'manage_permission')),
            array('methods'=>'DELETE','callback'=>array($this,'delete_template'),'permission_callback'=>array($this,'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1','/whatsapp/assets',array(
            array('methods'=>'GET','callback'=>array($this,'media'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'create_media'),'permission_callback'=>array($this,'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1','/whatsapp/assets/(?P<id>\d+)',array(
            array('methods'=>'POST','callback'=>array($this,'update_media'),'permission_callback'=>array($this,'manage_permission')),
            array('methods'=>'DELETE','callback'=>array($this,'delete_media'),'permission_callback'=>array($this,'manage_permission'))
        ));

        register_rest_route('omnigocrm/v1','/conversations',array(
            array('methods'=>'GET','callback'=>array($this,'conversations'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'create_conversation'),'permission_callback'=>array($this,'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1','/conversations/(?P<id>\d+)/messages',array(
            array('methods'=>'GET','callback'=>array($this,'messages'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'create_message'),'permission_callback'=>array($this,'manage_permission'))
        ));

        register_rest_route('omnigocrm/v1','/tags',array(
            array('methods'=>'GET','callback'=>array($this,'tags'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'create_tag'),'permission_callback'=>array($this,'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1','/tags/(?P<id>\\d+)',array('methods'=>'DELETE','callback'=>array($this,'delete_tag'),'permission_callback'=>array($this,'manage_permission')));
        register_rest_route('omnigocrm/v1','/pipelines',array(
            array('methods'=>'GET','callback'=>array($this,'pipelines'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'create_pipeline'),'permission_callback'=>array($this,'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1','/pipelines/(?P<id>\d+)/stages',array(
            array('methods'=>'GET','callback'=>array($this,'stages'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'create_stage'),'permission_callback'=>array($this,'manage_permission'))
        ));

        register_rest_route('omnigocrm/v1','/quotes/(?P<id>\d+)/items',array(
            array('methods'=>'GET','callback'=>array($this,'quote_items'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'add_quote_item'),'permission_callback'=>array($this,'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1','/quotes/(?P<quote_id>\d+)/items/(?P<id>\d+)',array('methods'=>'DELETE','callback'=>array($this,'delete_quote_item'),'permission_callback'=>array($this,'manage_permission')));
        register_rest_route('omnigocrm/v1','/orders/(?P<id>\d+)/items',array(
            array('methods'=>'GET','callback'=>array($this,'order_items'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'add_order_item'),'permission_callback'=>array($this,'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1','/orders/(?P<order_id>\d+)/items/(?P<id>\d+)',array('methods'=>'DELETE','callback'=>array($this,'delete_order_item'),'permission_callback'=>array($this,'manage_permission')));

        register_rest_route('omnigocrm/v1','/calendar',array('methods'=>'GET','callback'=>array($this,'calendar'),'permission_callback'=>array($this,'permission')));
        register_rest_route('omnigocrm/v1','/notifications',array('methods'=>'GET','callback'=>array($this,'notifications'),'permission_callback'=>array($this,'permission')));
        register_rest_route('omnigocrm/v1','/notifications/(?P<id>\d+)/read',array('methods'=>'POST','callback'=>array($this,'notification_read'),'permission_callback'=>array($this,'permission')));
        register_rest_route('omnigocrm/v1','/audit-logs',array('methods'=>'GET','callback'=>array($this,'audit_logs'),'permission_callback'=>array($this,'admin_permission')));
        register_rest_route('omnigocrm/v1','/users',array('methods'=>'GET','callback'=>array($this,'users'),'permission_callback'=>array($this,'role_permission')));
        register_rest_route('omnigocrm/v1','/settings',array(
            array('methods'=>'GET','callback'=>array($this,'settings'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'save_settings'),'permission_callback'=>array($this,'admin_permission'))
        ));
        register_rest_route('omnigocrm/v1','/plans',array('methods'=>'GET','callback'=>array($this,'plans'),'permission_callback'=>array($this,'permission')));
        register_rest_route('omnigocrm/v1','/subscription',array(
            array('methods'=>'GET','callback'=>array($this,'subscription'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'save_subscription'),'permission_callback'=>array($this,'admin_permission'))
        ));
        register_rest_route('omnigocrm/v1','/automations/(?P<id>\d+)/run',array('methods'=>'POST','callback'=>array($this,'run_automation'),'permission_callback'=>array($this,'manage_permission')));
        register_rest_route('omnigocrm/v1','/public/leads',array('methods'=>'POST','callback'=>array($this,'public_lead'),'permission_callback'=>'__return_true'));
    }

    public function dashboard() {
        global $wpdb;
        $t=$this->t;
        return rest_ensure_response(array(
            'leads'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['leads']}"),
            'contacts'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['contacts']}"),
            'companies'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['companies']}"),
            'opportunities'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['opportunities']} WHERE status IS NULL OR status<>''"),
            'pipeline_value'=>(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$t['opportunities']} WHERE stage NOT IN ('Won','Lost','won','lost')"),
            'tasks'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['tasks']} WHERE status NOT IN ('completed','cancelled')"),
            'invoices_outstanding'=>(float)$wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$t['invoices']} WHERE status NOT IN ('paid','cancelled')"),
            'paid_revenue'=>(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$t['payments']} WHERE status='paid'")
        ));
    }

    public function reports() {
        global $wpdb;
        $t=$this->t;
        $sources=$wpdb->get_results("SELECT source,COUNT(*) count FROM {$t['leads']} GROUP BY source ORDER BY count DESC");
        $stages=$wpdb->get_results("SELECT stage,COUNT(*) count,COALESCE(SUM(amount),0) value FROM {$t['opportunities']} GROUP BY stage ORDER BY count DESC");
        $payments=$wpdb->get_results("SELECT DATE_FORMAT(created_at,'%Y-%m') month,COALESCE(SUM(amount),0) revenue FROM {$t['payments']} WHERE status='paid' GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month DESC LIMIT 12");
        return rest_ensure_response(array(
            'won'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['opportunities']} WHERE LOWER(stage)='won'"),
            'lost'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['opportunities']} WHERE LOWER(stage)='lost'"),
            'paid_revenue'=>(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$t['payments']} WHERE status='paid'"),
            'outstanding'=>(float)$wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$t['invoices']} WHERE status NOT IN ('paid','cancelled')"),
            'leads_by_source'=>$sources,
            'pipeline_by_stage'=>$stages,
            'monthly_revenue'=>$payments
        ));
    }

    public function resource($request) {
        global $wpdb;
        $type=$request['type'] ?? '';
        if ($type==='') $type=$request->get_route_params()['type'] ?? '';
        $cfg=$this->cfg($type);
        if(!$cfg) return new WP_Error('invalid_resource','Invalid resource.',array('status'=>400));
        $limit=min(250,max(1,(int)$request->get_param('limit')?:100));
        $offset=max(0,(int)$request->get_param('offset'));
        $search=sanitize_text_field($request->get_param('search'));
        $status=sanitize_text_field($request->get_param('status'));
        $where=array('1=1'); $values=array(); $like_fields=$cfg['search'];
        if($search){
            $parts=array();
            foreach($like_fields as $field){$parts[]="$field LIKE %s";$values[]='%'.$wpdb->esc_like($search).'%';}
            $where[]='('.implode(' OR ',$parts).')';
        }
        if($status && in_array('status',$cfg['fields'],true)){$where[]='status=%s';$values[]=$status;}
        $table=$this->t[$cfg['table']];
        $sql="SELECT * FROM {$table} WHERE ".implode(' AND ',$where)." ORDER BY id DESC LIMIT %d OFFSET %d";
        $values[]=$limit;$values[]=$offset;
        $rows=$wpdb->get_results($wpdb->prepare($sql,$values));
        return rest_ensure_response(array('data'=>$rows,'pagination'=>array('limit'=>$limit,'offset'=>$offset,'count'=>count($rows))));
    }

    public function resource_item($request) {
        global $wpdb;
        $type=$request['type'];$cfg=$this->cfg($type);
        if(!$cfg) return new WP_Error('invalid_resource','Invalid resource.',array('status'=>400));
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t[$cfg['table']]} WHERE id=%d",(int)$request['id']));
        if(!$row) return new WP_Error('not_found','Record not found.',array('status'=>404));
        return rest_ensure_response(array('data'=>$row));
    }

    public function create_resource($request) {
        global $wpdb;
        $type=$request['type'];$cfg=$this->cfg($type);
        if(!$cfg) return new WP_Error('invalid_resource','Invalid resource.',array('status'=>400));
        $input=$request->get_json_params(); if(!is_array($input))$input=array();
        $data=$this->sanitize_data($type,$input,false);if(is_wp_error($data))return $data;
        if($type==='leads' && empty($data['source']))$data['source']='manual';
        if(in_array($type,array('leads','contacts','companies','opportunities'),true) && empty($data['owner_id']))$data['owner_id']=get_current_user_id();
        if($type==='tasks' && empty($data['assigned_to']))$data['assigned_to']=get_current_user_id();
        if($type==='notes' && empty($data['created_by']))$data['created_by']=get_current_user_id();
        if(in_array($type,array('quotes','orders','payments'),true) && empty($data['created_by']) && isset($data['created_by']))$data['created_by']=get_current_user_id();
        $now=current_time('mysql');
        if(!isset($data['created_at']))$data['created_at']=$now;
        if(!isset($data['updated_at']))$data['updated_at']=$now;
        $wpdb->insert($this->t[$cfg['table']],$data);
        if(!$wpdb->insert_id)return new WP_Error('db_error',$wpdb->last_error?:'Could not create record.',array('status'=>500));
        $id=$wpdb->insert_id;$this->audit('create',$type,$id);
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t[$cfg['table']]} WHERE id=%d",$id));
        return new WP_REST_Response(array('data'=>$row),201);
    }

    public function update_resource($request) {
        global $wpdb;
        $type=$request['type'];$cfg=$this->cfg($type);
        if(!$cfg)return new WP_Error('invalid_resource','Invalid resource.',array('status'=>400));
        $input=$request->get_json_params();if(!is_array($input))$input=array();
        $data=$this->sanitize_data($type,$input,true);if(is_wp_error($data))return $data;
        $data['updated_at']=current_time('mysql');
        $id=(int)$request['id'];
        $wpdb->update($this->t[$cfg['table']],$data,array('id'=>$id));
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t[$cfg['table']]} WHERE id=%d",$id));
        if(!$row)return new WP_Error('not_found','Record not found.',array('status'=>404));
        $this->audit('update',$type,$id);
        return rest_ensure_response(array('data'=>$row));
    }

    public function delete_resource($request) {
        global $wpdb;
        $type=$request['type'];$cfg=$this->cfg($type);if(!$cfg)return new WP_Error('invalid_resource','Invalid resource.',array('status'=>400));
        $id=(int)$request['id'];
        $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t[$cfg['table']]} WHERE id=%d",$id));
        if(!$exists)return new WP_Error('not_found','Record not found.',array('status'=>404));
        $wpdb->delete($this->t[$cfg['table']],array('id'=>$id));
        if($type==='quotes')$wpdb->delete($this->t['quote_items'],array('quote_id'=>$id));
        if($type==='orders')$wpdb->delete($this->t['order_items'],array('order_id'=>$id));
        $this->audit('delete',$type,$id);
        return rest_ensure_response(array('success'=>true,'id'=>$id));
    }

    public function templates($request) {
        global $wpdb;$channel=$request->get_param('channel');
        $where="active=1";$params=array();
        if($channel){$where.=" AND channel=%s";$params[]=$channel;}
        $sql="SELECT * FROM {$this->t['templates']} WHERE {$where} ORDER BY name";
        $rows=$params?$wpdb->get_results($wpdb->prepare($sql,$params)):$wpdb->get_results($sql);
        return rest_ensure_response(array('data'=>$rows));
    }

    public function create_template($request) {
        global $wpdb;$p=$request->get_json_params();
        if(empty($p['name'])||empty($p['body']))return new WP_Error('validation','Template name and body are required.',array('status'=>400));
        $now=current_time('mysql');
        $data=array('name'=>$this->clean($p['name']),'channel'=>$this->clean($p['channel']??'whatsapp'),'subject'=>$this->clean($p['subject']??''),'body'=>$this->textarea($p['body']),'media_asset_id'=>(int)($p['media_asset_id']??0),'active'=>1,'created_at'=>$now,'updated_at'=>$now);
        $wpdb->insert($this->t['templates'],$data);$this->audit('create','message_template',$wpdb->insert_id);
        return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['templates']} WHERE id=%d",$wpdb->insert_id))),201);
    }

    public function update_template($request) {
        global $wpdb;$id=(int)$request['id'];$p=$request->get_json_params();$data=array();
        foreach(array('name','channel','subject') as $k)if(isset($p[$k]))$data[$k]=$this->clean($p[$k]);
        if(isset($p['body']))$data['body']=$this->textarea($p['body']);
        if(isset($p['media_asset_id']))$data['media_asset_id']=(int)$p['media_asset_id'];
        if(isset($p['active']))$data['active']=$this->bool_value($p['active']);
        $data['updated_at']=current_time('mysql');$wpdb->update($this->t['templates'],$data,array('id'=>$id));
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['templates']} WHERE id=%d",$id));if(!$row)return new WP_Error('not_found','Template not found.',array('status'=>404));
        $this->audit('update','message_template',$id);return rest_ensure_response(array('data'=>$row));
    }

    public function delete_template($request){global $wpdb;$id=(int)$request['id'];$wpdb->update($this->t['templates'],array('active'=>0,'updated_at'=>current_time('mysql')),array('id'=>$id));$this->audit('delete','message_template',$id);return rest_ensure_response(array('success'=>true));}

    public function media($request) {
        global $wpdb;$rows=$wpdb->get_results("SELECT * FROM {$this->t['media']} WHERE active=1 ORDER BY name");return rest_ensure_response(array('data'=>$rows));
    }

    public function create_media($request) {
        global $wpdb;$p=$request->get_json_params();
        if(empty($p['name'])||empty($p['url']))return new WP_Error('validation','Media name and URL are required.',array('status'=>400));
        $now=current_time('mysql');$data=array('name'=>$this->clean($p['name']),'description'=>$this->textarea($p['description']??''),'asset_type'=>$this->clean($p['asset_type']??'document'),'url'=>esc_url_raw($p['url']),'thumbnail_url'=>esc_url_raw($p['thumbnail_url']??''),'mime_type'=>$this->clean($p['mime_type']??''),'active'=>1,'is_active'=>1,'created_at'=>$now,'updated_at'=>$now);
        $wpdb->insert($this->t['media'],$data);$this->audit('create','media_asset',$wpdb->insert_id);return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['media']} WHERE id=%d",$wpdb->insert_id))),201);
    }

    public function update_media($request) {
        global $wpdb;$id=(int)$request['id'];$p=$request->get_json_params();$data=array();
        foreach(array('name','asset_type','mime_type') as $k)if(isset($p[$k]))$data[$k]=$this->clean($p[$k]);
        if(isset($p['description']))$data['description']=$this->textarea($p['description']);
        foreach(array('url','thumbnail_url') as $k)if(isset($p[$k]))$data[$k]=esc_url_raw($p[$k]);
        if(isset($p['active'])){$data['active']=$this->bool_value($p['active']);$data['is_active']=$data['active'];}
        $data['updated_at']=current_time('mysql');$wpdb->update($this->t['media'],$data,array('id'=>$id));$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['media']} WHERE id=%d",$id));if(!$row)return new WP_Error('not_found','Media asset not found.',array('status'=>404));$this->audit('update','media_asset',$id);return rest_ensure_response(array('data'=>$row));
    }

    public function delete_media($request){global $wpdb;$id=(int)$request['id'];$wpdb->update($this->t['media'],array('active'=>0,'is_active'=>0,'updated_at'=>current_time('mysql')),array('id'=>$id));$this->audit('delete','media_asset',$id);return rest_ensure_response(array('success'=>true));}

    public function convert_lead($request) {
        global $wpdb;
        $id=(int)$request['id'];
        $lead=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['leads']} WHERE id=%d",$id));
        if(!$lead)return new WP_Error('not_found','Lead not found.',array('status'=>404));
        $now=current_time('mysql');
        $contact_id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['contacts']} WHERE email=%s OR phone=%s ORDER BY id DESC LIMIT 1",$lead->email,$lead->phone));
        if(!$contact_id){
            $wpdb->insert($this->t['contacts'],array('first_name'=>$lead->first_name,'last_name'=>$lead->last_name,'company'=>$lead->company,'email'=>$lead->email,'phone'=>$lead->phone,'job_title'=>$lead->job_title,'website'=>$lead->website,'location'=>$lead->location,'owner_id'=>$lead->owner_id?:get_current_user_id(),'created_at'=>$now,'updated_at'=>$now));
            $contact_id=$wpdb->insert_id;
        }
        $opp_id=0;
        if(!empty($lead->value) || !empty($lead->company)){
            $wpdb->insert($this->t['opportunities'],array('name'=>trim($lead->first_name.' '.$lead->last_name).' Opportunity','company'=>$lead->company,'amount'=>(float)$lead->value,'currency'=>'INR','stage'=>'New','probability'=>10,'close_date'=>$lead->expected_close_date,'expected_close_date'=>$lead->expected_close_date,'contact_id'=>$contact_id,'owner_id'=>$lead->owner_id?:get_current_user_id(),'description'=>'Converted from lead #'.$id,'created_at'=>$now,'updated_at'=>$now));
            $opp_id=$wpdb->insert_id;
        }
        $wpdb->update($this->t['leads'],array('status'=>'converted','updated_at'=>$now),array('id'=>$id));
        $this->audit('convert','lead',$id,array('contact_id'=>$contact_id,'opportunity_id'=>$opp_id));
        return rest_ensure_response(array('success'=>true,'lead_id'=>$id,'contact_id'=>$contact_id,'opportunity_id'=>$opp_id));
    }

    public function whatsapp($request) {
        global $wpdb;$lead_id=(int)$request['id'];$lead=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['leads']} WHERE id=%d",$lead_id));
        if(!$lead)return new WP_Error('not_found','Lead not found.',array('status'=>404));
        $p=$request->get_json_params();$body=$this->textarea($p['body']??'');$template_id=(int)($p['template_id']??0);$media_id=(int)($p['media_asset_id']??$p['media_id']??0);$asset=null;
        if($media_id)$asset=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['media']} WHERE id=%d AND active=1",$media_id));
        if(!$body&&$template_id)$body=(string)$wpdb->get_var($wpdb->prepare("SELECT body FROM {$this->t['templates']} WHERE id=%d AND active=1",$template_id));
        if($asset && strpos($body,$asset->url)===false)$body=trim($body."\n\n".$asset->name.": ".$asset->url);
        $body=$this->render_body($body,$lead,$asset?$asset->url:'');
        if(!$body)return new WP_Error('empty_message','Message cannot be empty.',array('status'=>400));
        $phone=$this->phone($lead->phone);if(!$phone)return new WP_Error('missing_phone','Lead does not have a valid WhatsApp phone number.',array('status'=>400));
        $now=current_time('mysql');$conv=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['conversations']} WHERE lead_id=%d AND channel='whatsapp' ORDER BY updated_at DESC LIMIT 1",$lead_id));
        if(!$conv){$wpdb->insert($this->t['conversations'],array('lead_id'=>$lead_id,'channel'=>'whatsapp','external_contact'=>$phone,'phone'=>$phone,'assigned_to'=>get_current_user_id(),'status'=>'open','last_message'=>$body,'last_message_at'=>$now,'created_at'=>$now,'updated_at'=>$now));$conv=$wpdb->insert_id;}
        else $wpdb->update($this->t['conversations'],array('last_message'=>$body,'last_message_at'=>$now,'updated_at'=>$now),array('id'=>$conv));
        $wpdb->insert($this->t['messages'],array('conversation_id'=>$conv,'sender_id'=>get_current_user_id(),'direction'=>'outbound','message_type'=>$asset?'file':'text','body'=>$body,'media_url'=>$asset?$asset->url:'','status'=>'prepared','metadata'=>wp_json_encode(array('method'=>'click_to_chat','template_id'=>$template_id,'media_asset_id'=>$media_id)),'created_at'=>$now));
        $this->audit('whatsapp_redirect','lead',$lead_id,array('phone'=>$phone));
        $encoded=rawurlencode($body);
        return rest_ensure_response(array('urls'=>array(
            'mobile'=>'https://wa.me/'.$phone.'?text='.$encoded,
            'web'=>'https://web.whatsapp.com/send?phone='.$phone.'&text='.$encoded,
            'desktop'=>'whatsapp://send?phone='.$phone.'&text='.$encoded
        ),'message'=>$body,'phone'=>$phone,'note'=>'WhatsApp opens with the message prepared. Press Send in WhatsApp.'));
    }

    public function conversations() {
        global $wpdb;
        $rows=$wpdb->get_results("SELECT *,(SELECT COUNT(*) FROM {$this->t['messages']} m WHERE m.conversation_id=c.id) message_count FROM {$this->t['conversations']} c ORDER BY c.updated_at DESC LIMIT 250");
        return rest_ensure_response(array('data'=>$rows));
    }

    public function create_conversation($request) {
        global $wpdb;$p=$request->get_json_params();$now=current_time('mysql');
        $data=array('lead_id'=>(int)($p['lead_id']??0),'contact_id'=>(int)($p['contact_id']??0),'channel'=>$this->clean($p['channel']??'whatsapp'),'external_contact'=>$this->clean($p['external_contact']??''),'phone'=>$this->phone($p['phone']??''),'subject'=>$this->clean($p['subject']??''),'status'=>'open','assigned_to'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now);
        if(!in_array($data['channel'],array('whatsapp','sms','email','call','web'),true))$data['channel']='whatsapp';
        $wpdb->insert($this->t['conversations'],$data);$this->audit('create','conversation',$wpdb->insert_id);return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['conversations']} WHERE id=%d",$wpdb->insert_id))),201);
    }

    public function messages($request){global $wpdb;$id=(int)$request['id'];$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['messages']} WHERE conversation_id=%d ORDER BY created_at ASC",$id));return rest_ensure_response(array('data'=>$rows));}

    public function create_message($request) {
        global $wpdb;$id=(int)$request['id'];$p=$request->get_json_params();$body=$this->textarea($p['body']??'');if(!$body)return new WP_Error('validation','Message body is required.',array('status'=>400));$now=current_time('mysql');
        $wpdb->insert($this->t['messages'],array('conversation_id'=>$id,'sender_id'=>get_current_user_id(),'direction'=>$this->clean($p['direction']??'outbound'),'message_type'=>$this->clean($p['message_type']??'text'),'body'=>$body,'media_url'=>esc_url_raw($p['media_url']??''),'status'=>'prepared','metadata'=>wp_json_encode($p['metadata']??array()),'created_at'=>$now));
        $this->audit('create','message',$wpdb->insert_id);$wpdb->update($this->t['conversations'],array('last_message'=>$body,'last_message_at'=>$now,'updated_at'=>$now),array('id'=>$id));
        return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['messages']} WHERE id=%d",$wpdb->insert_id))),201);
    }

    public function tags(){global $wpdb;return rest_ensure_response(array('data'=>$wpdb->get_results("SELECT * FROM {$this->t['tags']} ORDER BY name")));}

    public function create_tag($request){
        global $wpdb;$p=$request->get_json_params();$name=$this->clean($p['name']??'');
        if(!$name)return new WP_Error('validation','Tag name is required.',array('status'=>400));
        $now=current_time('mysql');$wpdb->insert($this->t['tags'],array('name'=>$name,'color'=>$this->clean($p['color']??''),'created_at'=>$now));
        if(!$wpdb->insert_id)return new WP_Error('db_error',$wpdb->last_error?:'Could not create tag.',array('status'=>500));
        $this->audit('create','tag',$wpdb->insert_id);return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['tags']} WHERE id=%d",$wpdb->insert_id))),201);
    }

    public function delete_tag($request){global $wpdb;$id=(int)$request['id'];$wpdb->delete($this->t['entity_tags'],array('tag_id'=>$id));$wpdb->delete($this->t['tags'],array('id'=>$id));$this->audit('delete','tag',$id);return rest_ensure_response(array('success'=>true));}

    public function pipelines() {
        global $wpdb;$pipes=$wpdb->get_results("SELECT * FROM {$this->t['pipelines']} ORDER BY is_default DESC,name");
        foreach($pipes as $p)$p->stages=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['stages']} WHERE pipeline_id=%d ORDER BY position",$p->id));
        return rest_ensure_response(array('data'=>$pipes));
    }

    public function create_pipeline($request) {
        global $wpdb;$p=$request->get_json_params();$now=current_time('mysql');if(empty($p['name']))return new WP_Error('validation','Pipeline name is required.',array('status'=>400));
        $wpdb->insert($this->t['pipelines'],array('name'=>$this->clean($p['name']),'description'=>$this->textarea($p['description']??''),'is_default'=>!empty($p['is_default'])?1:0,'created_at'=>$now,'updated_at'=>$now));$id=$wpdb->insert_id;
        $this->audit('create','pipeline',$id);return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['pipelines']} WHERE id=%d",$id))),201);
    }

    public function stages($request){global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['stages']} WHERE pipeline_id=%d ORDER BY position",(int)$request['id']));return rest_ensure_response(array('data'=>$rows));}

    public function create_stage($request) {
        global $wpdb;$p=$request->get_json_params();$now=current_time('mysql');if(empty($p['name']))return new WP_Error('validation','Stage name is required.',array('status'=>400));
        $pos=(int)($p['position']??1);$wpdb->insert($this->t['stages'],array('pipeline_id'=>(int)$request['id'],'name'=>$this->clean($p['name']),'position'=>$pos,'probability'=>(int)($p['probability']??0),'stage_color'=>$this->clean($p['stage_color']??''),'created_at'=>$now,'updated_at'=>$now));$this->audit('create','pipeline_stage',$wpdb->insert_id);return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['stages']} WHERE id=%d",$wpdb->insert_id))),201);
    }

    private function recalc_items($type,$id) {
        global $wpdb;
        $items_table=$type==='quote'?$this->t['quote_items']:$this->t['order_items'];
        $parent_table=$type==='quote'?$this->t['quotes']:$this->t['orders'];
        $fk=$type==='quote'?'quote_id':'order_id';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT quantity,unit_price,tax_rate,total FROM {$items_table} WHERE {$fk}=%d",$id));
        $subtotal=0;$tax=0;
        foreach($rows as $r){$line=(float)$r->quantity*(float)$r->unit_price;$line_tax=$line*((float)$r->tax_rate/100);$subtotal+=$line;$tax+=$line_tax;$wpdb->update($items_table,array('total'=>$line+$line_tax),array($fk=>$id));}
        $wpdb->update($parent_table,array('subtotal'=>$subtotal,'tax_total'=>$tax,'total'=>$subtotal+$tax,'updated_at'=>current_time('mysql')),array('id'=>$id));
    }

    public function quote_items($request){global $wpdb;return rest_ensure_response(array('data'=>$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['quote_items']} WHERE quote_id=%d ORDER BY id",(int)$request['id']))));}
    public function add_quote_item($request){return $this->add_line_item('quote',$request);}
    public function delete_quote_item($request){global $wpdb;$id=(int)$request['id'];$parent=(int)$request['quote_id'];$wpdb->delete($this->t['quote_items'],array('id'=>$id,'quote_id'=>$parent));$this->recalc_items('quote',$parent);$this->audit('delete','quote_item',$id);return rest_ensure_response(array('success'=>true));}
    public function order_items($request){global $wpdb;return rest_ensure_response(array('data'=>$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['order_items']} WHERE order_id=%d ORDER BY id",(int)$request['id']))));}
    public function add_order_item($request){return $this->add_line_item('order',$request);}
    public function delete_order_item($request){global $wpdb;$id=(int)$request['id'];$parent=(int)$request['order_id'];$wpdb->delete($this->t['order_items'],array('id'=>$id,'order_id'=>$parent));$this->recalc_items('order',$parent);$this->audit('delete','order_item',$id);return rest_ensure_response(array('success'=>true));}

    private function add_line_item($type,$request){
        global $wpdb;$p=$request->get_json_params();$parent=$type==='quote'?'quote_id':'order_id';$table=$type==='quote'?$this->t['quote_items']:$this->t['order_items'];$id=(int)$request['id'];
        if(empty($p['description']))return new WP_Error('validation','Item description is required.',array('status'=>400));
        $qty=max(0.01,(float)($p['quantity']??1));$price=max(0,(float)($p['unit_price']??0));$tax=max(0,(float)($p['tax_rate']??0));$total=$qty*$price*(1+$tax/100);
        $wpdb->insert($table,array($parent=>$id,'product_id'=>(int)($p['product_id']??0),'description'=>$this->textarea($p['description']),'quantity'=>$qty,'unit_price'=>$price,'tax_rate'=>$tax,'total'=>$total));
        $this->recalc_items($type,$id);$this->audit('create',$type.'_item',$wpdb->insert_id);return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$wpdb->insert_id))),201);
    }

    public function calendar() {
        global $wpdb;$from=current_time('mysql');$to=date('Y-m-d H:i:s',strtotime('+60 days'));$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['tasks']} WHERE due_at IS NOT NULL AND due_at BETWEEN %s AND %s ORDER BY due_at",$from,$to));return rest_ensure_response(array('data'=>$rows));
    }

    public function notifications() {
        global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['notifications']} WHERE user_id=%d ORDER BY created_at DESC LIMIT 100",get_current_user_id()));return rest_ensure_response(array('data'=>$rows));
    }

    public function notification_read($request) {
        global $wpdb;$id=(int)$request['id'];$wpdb->update($this->t['notifications'],array('read_at'=>current_time('mysql')),array('id'=>$id,'user_id'=>get_current_user_id()));return rest_ensure_response(array('success'=>true));
    }

    public function audit_logs(){global $wpdb;return rest_ensure_response(array('data'=>$wpdb->get_results("SELECT * FROM {$this->t['audit']} ORDER BY created_at DESC LIMIT 250")));}

    public function users(){
        $users=get_users(array('fields'=>array('ID','display_name','user_email','roles')));
        return rest_ensure_response(array('data'=>array_map(function($u){return array('id'=>$u->ID,'name'=>$u->display_name,'email'=>$u->user_email,'roles'=>$u->roles);},$users)));
    }

    public function settings(){
        $defaults=array('business_name'=>get_bloginfo('name'),'currency'=>'INR','timezone'=>wp_timezone_string(),'lead_default_status'=>'new','whatsapp_default_template'=>'','company_website'=>home_url(),'notifications'=>1);
        return rest_ensure_response(array('data'=>wp_parse_args(get_option('omnigocrm_settings',array()),$defaults)));
    }

    public function save_settings($request){
        $p=$request->get_json_params();$current=(array)get_option('omnigocrm_settings',array());$allowed=array('business_name','currency','timezone','lead_default_status','whatsapp_default_template','company_website','notifications');
        foreach($allowed as $k)if(array_key_exists($k,$p))$current[$k]=in_array($k,array('notifications'),true)?$this->bool_value($p[$k]):$this->clean($p[$k]);
        update_option('omnigocrm_settings',$current);$this->audit('update','settings',0,$current);return rest_ensure_response(array('data'=>$current));
    }

    public function plans(){global $wpdb;return rest_ensure_response(array('data'=>$wpdb->get_results("SELECT id,code,name,description,monthly_price,currency,limits,features FROM {$this->t['plans']} WHERE active=1 ORDER BY monthly_price")));}

    public function subscription(){global $wpdb;$row=$wpdb->get_row("SELECT s.*,p.code plan_code,p.name plan_name,p.monthly_price FROM {$this->t['subscriptions']} s LEFT JOIN {$this->t['plans']} p ON p.id=s.plan_id ORDER BY s.id DESC LIMIT 1");return rest_ensure_response(array('data'=>$row));}

    public function save_subscription($request){global $wpdb;$p=$request->get_json_params();$plan=(int)($p['plan_id']??0);if(!$plan)return new WP_Error('validation','Plan is required.',array('status'=>400));$now=current_time('mysql');$existing=$wpdb->get_var("SELECT id FROM {$this->t['subscriptions']} ORDER BY id DESC LIMIT 1");$data=array('plan_id'=>$plan,'status'=>$this->clean($p['status']??'trialing'),'provider'=>$this->clean($p['provider']??''),'provider_subscription_id'=>$this->clean($p['provider_subscription_id']??''),'current_period_start'=>$p['current_period_start']??null,'current_period_end'=>$p['current_period_end']??null,'updated_at'=>$now);if($existing)$wpdb->update($this->t['subscriptions'],$data,array('id'=>$existing));else{$data['created_at']=$now;$wpdb->insert($this->t['subscriptions'],$data);}$this->audit('update','subscription',$existing?:$wpdb->insert_id);return $this->subscription($request);}
    
    public function run_automation($request){
        global $wpdb;$id=(int)$request['id'];$automation=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['automations']} WHERE id=%d",$id));if(!$automation)return new WP_Error('not_found','Automation not found.',array('status'=>404));$now=current_time('mysql');$wpdb->insert($this->t['automation_jobs'],array('automation_id'=>$id,'status'=>'queued','run_at'=>$now,'payload'=>wp_json_encode(array('requested_by'=>get_current_user_id())),'created_at'=>$now,'updated_at'=>$now));$this->audit('enqueue','automation',$id);if(!wp_next_scheduled('omnigocrm_process_jobs'))wp_schedule_single_event(time()+10,'omnigocrm_process_jobs');return rest_ensure_response(array('queued'=>true,'job_id'=>$wpdb->insert_id));
    }

    public function process_jobs(){
        global $wpdb;$now=current_time('mysql');$jobs=$wpdb->get_results($wpdb->prepare("SELECT j.*,a.name automation_name,a.definition,a.trigger_type,a.active FROM {$this->t['automation_jobs']} j LEFT JOIN {$this->t['automations']} a ON a.id=j.automation_id WHERE j.status='queued' AND j.run_at<=%s ORDER BY j.id ASC LIMIT 25",$now));
        foreach($jobs as $job){
            $wpdb->update($this->t['automation_jobs'],array('status'=>'running','updated_at'=>$now),array('id'=>$job->id));
            $definition=json_decode($job->definition?:'{}',true);$ok=true;$error='';
            if(isset($definition['actions'])&&is_array($definition['actions'])){
                foreach($definition['actions'] as $action){
                    $type=$action['type']??'notify';
                    if($type==='notify'){
                        $user_id=(int)($action['user_id']??get_current_user_id());$wpdb->insert($this->t['notifications'],array('user_id'=>$user_id,'type'=>'automation','title'=>$action['title']??$job->automation_name,'body'=>$action['body']??'Automation completed.','data'=>wp_json_encode($action['data']??array()),'created_at'=>$now));
                    }elseif($type==='create_task'){
                        $wpdb->insert($this->t['tasks'],array('title'=>$action['title']??$job->automation_name,'description'=>$this->textarea($action['description']??''),'status'=>'open','priority'=>$this->clean($action['priority']??'normal'),'assigned_to'=>(int)($action['assigned_to']??0),'created_at'=>$now,'updated_at'=>$now));
                    }
                }
            }
            if($ok)$wpdb->update($this->t['automation_jobs'],array('status'=>'completed','updated_at'=>$now),array('id'=>$job->id));
            else $wpdb->update($this->t['automation_jobs'],array('status'=>'failed','error'=>$error,'updated_at'=>$now),array('id'=>$job->id));
        }
    }

    public function public_lead($request){
        global $wpdb;$p=$request->get_json_params();$first=$this->clean($p['first_name']??'');if(!$first)return new WP_Error('validation','First name is required.',array('status'=>400));
        $now=current_time('mysql');$data=array('first_name'=>$first,'last_name'=>$this->clean($p['last_name']??''),'company'=>$this->clean($p['company']??''),'email'=>sanitize_email($p['email']??''),'phone'=>$this->clean($p['phone']??''),'source'=>$this->clean($p['source']??'website'),'status'=>'new','score'=>0,'created_at'=>$now,'updated_at'=>$now);
        $wpdb->insert($this->t['leads'],$data);return new WP_REST_Response(array('success'=>true,'id'=>$wpdb->insert_id),201);
    }
}
