<?php
if (!defined('ABSPATH')) exit;

class OmniGoCRM_REST {
    private $t;

    public function __construct() {
        $this->t = OmniGoCRM_DB::tables();
        add_action('rest_api_init', array($this, 'routes'));
        add_action('omnigocrm_process_jobs', array($this, 'process_jobs'));
    }

    public function permission() {
        return current_user_can('omnigocrm_access') || current_user_can('manage_options');
    }

    public function manage_permission() {
        return current_user_can('omnigocrm_manage') || current_user_can('manage_options');
    }

    public function delete_permission() {
        return current_user_can('omnigocrm_delete') || current_user_can('manage_options');
    }

    public function admin_permission() {
        return current_user_can('omnigocrm_settings') || current_user_can('manage_options');
    }

    public function role_permission() {
        return current_user_can('omnigocrm_access') || current_user_can('manage_options');
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
            } elseif (in_array($field, array('due_date','due_at','started_at','ended_at','paid_at','valid_until','expected_close_date','close_date','scheduled_at'), true)) {
                $data[$field] = ($value === '' || $value === null) ? null : $this->clean($value);
            } elseif (in_array($field, array('source','status','stage','priority','channel','type','direction','method','provider','currency','job_title','website','location','industry','company','name','first_name','last_name','phone','title','sku','recording_url','related_type','audience_type','trigger_type','secrets_ref'), true)) {
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
        ))) as $type) {
            register_rest_route('omnigocrm/v1','/(?P<type>'.$type.')',array(
                array('methods'=>'GET','callback'=>array($this,'resource'),'permission_callback'=>array($this,'permission')),
                array('methods'=>'POST','callback'=>array($this,'create_resource'),'permission_callback'=>array($this,'manage_permission'))
            ));
            register_rest_route('omnigocrm/v1','/(?P<type>'.$type.')/(?P<id>\d+)',array(
                array('methods'=>'GET','callback'=>array($this,'resource_item'),'permission_callback'=>array($this,'permission')),
                array('methods'=>'POST','callback'=>array($this,'update_resource'),'permission_callback'=>array($this,'manage_permission')),
                array('methods'=>'PATCH','callback'=>array($this,'update_resource'),'permission_callback'=>array($this,'manage_permission')),
                array('methods'=>'DELETE','callback'=>array($this,'delete_resource'),'permission_callback'=>array($this,'delete_permission'))
            ));
        }

        register_rest_route('omnigocrm/v1','/leads/(?P<id>\d+)/convert',array('methods'=>'POST','callback'=>array($this,'convert_lead'),'permission_callback'=>array($this,'manage_permission')));
        register_rest_route('omnigocrm/v1','/leads/(?P<id>\d+)/whatsapp/prepare',array('methods'=>'POST','callback'=>array($this,'whatsapp'),'permission_callback'=>array($this,'manage_permission')));
        register_rest_route('omnigocrm/v1','/whatsapp/prepare',array('methods'=>'POST','callback'=>array($this,'whatsapp_prepare'),'permission_callback'=>array($this,'manage_permission')));
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

        register_rest_route('omnigocrm/v1','/integrations/providers',array('methods'=>'GET','callback'=>array($this,'integration_providers'),'permission_callback'=>array($this,'permission')));
        register_rest_route('omnigocrm/v1','/integrations/(?P<id>\d+)/credentials',array('methods'=>'POST','callback'=>array($this,'save_integration_credentials'),'permission_callback'=>array($this,'admin_permission')));
        register_rest_route('omnigocrm/v1','/integrations/(?P<id>\d+)/oauth/start',array('methods'=>'POST','callback'=>array($this,'integration_oauth_start'),'permission_callback'=>array($this,'admin_permission')));
        register_rest_route('omnigocrm/v1','/integrations/(?P<id>\d+)/disconnect',array('methods'=>'POST','callback'=>array($this,'disconnect_integration'),'permission_callback'=>array($this,'admin_permission')));
        register_rest_route('omnigocrm/v1','/integrations/oauth/callback',array('methods'=>'GET','callback'=>array($this,'integration_oauth_callback'),'permission_callback'=>'__return_true'));

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
        register_rest_route('omnigocrm/v1','/tags/(?P<id>\d+)',array('methods'=>'DELETE','callback'=>array($this,'delete_tag'),'permission_callback'=>array($this,'manage_permission')));
        register_rest_route('omnigocrm/v1','/tags/(?P<id>\d+)/entities',array(
            array('methods'=>'GET','callback'=>array($this,'tag_entities'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'assign_tag_entity'),'permission_callback'=>array($this,'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1','/tags/(?P<tag_id>\d+)/entities/(?P<entity_type>[a-zA-Z0-9_-]+)/(?P<entity_id>\d+)',array(
            array('methods'=>'DELETE','callback'=>array($this,'remove_tag_entity'),'permission_callback'=>array($this,'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1','/pipelines',array(
            array('methods'=>'GET','callback'=>array($this,'pipelines'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'create_pipeline'),'permission_callback'=>array($this,'manage_permission'))
        ));
        register_rest_route('omnigocrm/v1','/pipelines/(?P<id>\d+)/stages',array(
            array('methods'=>'GET','callback'=>array($this,'stages'),'permission_callback'=>array($this,'permission')),
            array('methods'=>'POST','callback'=>array($this,'create_stage'),'permission_callback'=>array($this,'manage_permission'))
        ));

        register_rest_route('omnigocrm/v1','/quotes/(?P<id>\d+)/convert-order',array('methods'=>'POST','callback'=>array($this,'quote_to_order'),'permission_callback'=>array($this,'manage_permission')));
        register_rest_route('omnigocrm/v1','/orders/(?P<id>\d+)/create-invoice',array('methods'=>'POST','callback'=>array($this,'order_to_invoice'),'permission_callback'=>array($this,'manage_permission')));
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
        register_rest_route('omnigocrm/v1','/users',array('methods'=>'POST','callback'=>array($this,'create_user'),'permission_callback'=>array($this,'admin_permission')));
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

    private function reconcile_invoice($invoice_id) {
        global $wpdb;
        if (!$invoice_id) return;
        $invoice=$wpdb->get_row($wpdb->prepare("SELECT id,total,status FROM {$this->t['invoices']} WHERE id=%d",$invoice_id));
        if(!$invoice)return;
        $paid=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$this->t['payments']} WHERE invoice_id=%d AND status='paid'",$invoice_id));
        $total=(float)$invoice->total;
        $status=$invoice->status;
        $paid_at=null;
        if($total>0 && $paid >= $total){$status='paid';$paid_at=current_time('mysql');}
        elseif($paid>0){$status='partial';}
        elseif(in_array($status,array('paid','partial'),true)){$status='sent';}
        if($status==='paid')$wpdb->update($this->t['invoices'],array('status'=>$status,'paid_at'=>$paid_at,'updated_at'=>current_time('mysql')),array('id'=>$invoice_id));
        else $wpdb->update($this->t['invoices'],array('status'=>$status,'updated_at'=>current_time('mysql')),array('id'=>$invoice_id));
    }

    public function dashboard() {
        global $wpdb;
        $t=$this->t;
        return rest_ensure_response(array(
            'leads'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['leads']}"),
            'contacts'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['contacts']}"),
            'companies'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['companies']}"),
            'opportunities'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['opportunities']} WHERE LOWER(stage) NOT IN ('won','lost')"),
            'pipeline_value'=>(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$t['opportunities']} WHERE LOWER(stage) NOT IN ('won','lost')"),
            'tasks'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['tasks']} WHERE status NOT IN ('completed','cancelled')"),
            'invoices_outstanding'=>(float)$wpdb->get_var("SELECT COALESCE(SUM(GREATEST(i.total-COALESCE((SELECT SUM(p.amount) FROM {$t['payments']} p WHERE p.invoice_id=i.id AND p.status='paid'),0),0)),0) FROM {$t['invoices']} i WHERE i.status NOT IN ('paid','cancelled')"),
            'paid_revenue'=>(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$t['payments']} WHERE status='paid'")
        ));
    }

    public function reports() {
        global $wpdb;
        $t=$this->t;

        $total_leads=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['leads']}");
        $converted_leads=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['leads']} WHERE LOWER(status)='converted'");
        $open_leads=max(0,$total_leads-$converted_leads);
        $conversion_rate=$total_leads?round(($converted_leads/$total_leads)*100,1):0;

        $won=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['opportunities']} WHERE LOWER(stage)='won'");
        $lost=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['opportunities']} WHERE LOWER(stage)='lost'");
        $open_opps=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['opportunities']} WHERE LOWER(stage) NOT IN ('won','lost')");
        $pipeline=(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$t['opportunities']} WHERE LOWER(stage) NOT IN ('won','lost')");
        $weighted_pipeline=(float)$wpdb->get_var("SELECT COALESCE(SUM(amount*probability/100),0) FROM {$t['opportunities']} WHERE LOWER(stage) NOT IN ('won','lost')");
        $won_value=(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$t['opportunities']} WHERE LOWER(stage)='won'");
        $lost_value=(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$t['opportunities']} WHERE LOWER(stage)='lost'");
        $win_rate=($won+$lost)?round(($won/($won+$lost))*100,1):0;
        $avg_deal=$won?$won_value/$won:0;

        $paid_revenue=(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$t['payments']} WHERE status='paid'");
        $outstanding=(float)$wpdb->get_var("SELECT COALESCE(SUM(GREATEST(i.total-COALESCE((SELECT SUM(p.amount) FROM {$t['payments']} p WHERE p.invoice_id=i.id AND p.status='paid'),0),0)),0) FROM {$t['invoices']} i WHERE i.status NOT IN ('paid','cancelled')");
        $overdue=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(GREATEST(i.total-COALESCE((SELECT SUM(p.amount) FROM {$t['payments']} p WHERE p.invoice_id=i.id AND p.status='paid'),0),0)),0) FROM {$t['invoices']} i WHERE i.status NOT IN ('paid','cancelled') AND i.due_date IS NOT NULL AND i.due_date < %s",current_time('Y-m-d')));

        $cycle_days=(float)$wpdb->get_var("SELECT COALESCE(AVG(DATEDIFF(updated_at,created_at)),0) FROM {$t['opportunities']} WHERE LOWER(stage)='won'");
        $sources=$wpdb->get_results("SELECT source,COUNT(*) count,SUM(CASE WHEN LOWER(status)='converted' THEN 1 ELSE 0 END) converted FROM {$t['leads']} GROUP BY source ORDER BY count DESC");
        foreach($sources as $row){$row->conversion_rate=$row->count?round(((int)$row->converted/(int)$row->count)*100,1):0;}

        $stages=$wpdb->get_results("SELECT stage,COUNT(*) count,COALESCE(SUM(amount),0) value,COALESCE(SUM(amount*probability/100),0) weighted_value FROM {$t['opportunities']} GROUP BY stage ORDER BY count DESC");
        $months=$wpdb->get_results("SELECT DATE_FORMAT(created_at,'%Y-%m') month,COUNT(*) leads,SUM(CASE WHEN LOWER(status)='converted' THEN 1 ELSE 0 END) converted FROM {$t['leads']} WHERE created_at >= DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month ASC");
        $revenue=$wpdb->get_results("SELECT DATE_FORMAT(created_at,'%Y-%m') month,COALESCE(SUM(amount),0) revenue FROM {$t['payments']} WHERE status='paid' AND created_at >= DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month ASC");
        $activity=$wpdb->get_results("SELECT 'calls' type,COUNT(*) count FROM {$t['calls']} UNION ALL SELECT 'tasks' type,COUNT(*) count FROM {$t['tasks']} UNION ALL SELECT 'messages' type,COUNT(*) count FROM {$t['messages']} UNION ALL SELECT 'notes' type,COUNT(*) count FROM {$t['notes']}");

        $top_opportunities=$wpdb->get_results("SELECT id,name,company,amount,currency,stage,probability,close_date FROM {$t['opportunities']} WHERE LOWER(stage) NOT IN ('won','lost') ORDER BY amount DESC LIMIT 10");
        $forecast=array(
            'pipeline'=>$pipeline,
            'weighted_pipeline'=>$weighted_pipeline,
            'won_value'=>$won_value,
            'open_opportunities'=>$open_opps
        );

        return rest_ensure_response(array(
            'summary'=>array(
                'total_leads'=>$total_leads,
                'converted_leads'=>$converted_leads,
                'open_leads'=>$open_leads,
                'conversion_rate'=>$conversion_rate,
                'won'=>$won,
                'lost'=>$lost,
                'win_rate'=>$win_rate,
                'open_opportunities'=>$open_opps,
                'pipeline_value'=>$pipeline,
                'weighted_pipeline'=>$weighted_pipeline,
                'won_value'=>$won_value,
                'lost_value'=>$lost_value,
                'average_won_deal'=>$avg_deal,
                'sales_cycle_days'=>round($cycle_days,1),
                'paid_revenue'=>$paid_revenue,
                'outstanding'=>$outstanding,
                'overdue'=>$overdue
            ),
            'leads_by_source'=>$sources,
            'pipeline_by_stage'=>$stages,
            'monthly_leads'=>$months,
            'monthly_revenue'=>$revenue,
            'activity'=>$activity,
            'top_opportunities'=>$top_opportunities,
            'forecast'=>$forecast
        ));
    }

    public function resource($request) {
        global $wpdb;
        $type=isset($request['type']) ? sanitize_key($request['type']) : '';
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
        if($type==='integrations') foreach($rows as $row) $this->safe_integration_row($row);
        return rest_ensure_response(array('data'=>$rows,'pagination'=>array('limit'=>$limit,'offset'=>$offset,'count'=>count($rows))));
    }

    public function resource_item($request) {
        global $wpdb;
        $type=$request['type'];$cfg=$this->cfg($type);
        if(!$cfg) return new WP_Error('invalid_resource','Invalid resource.',array('status'=>400));
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t[$cfg['table']]} WHERE id=%d",(int)$request['id']));
        if(!$row) return new WP_Error('not_found','Record not found.',array('status'=>404));
        if($type==='integrations') $this->safe_integration_row($row);
        return rest_ensure_response(array('data'=>$row));
    }

    public function create_resource($request) {
        global $wpdb;
        $type=$request['type'];$cfg=$this->cfg($type);
        if(!$cfg) return new WP_Error('invalid_resource','Invalid resource.',array('status'=>400));
        $input=$request->get_json_params(); if(!is_array($input))$input=array();
        $data=$this->sanitize_data($type,$input,false);if(is_wp_error($data))return $data;
        if($type==='leads' && empty($data['source']))$data['source']='manual';
        if($type==='quotes' && !empty($data['company_id']) && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['companies']} WHERE id=%d",(int)$data['company_id'])))return new WP_Error('not_found','Company not found.',array('status'=>404));
        if($type==='quotes' && !empty($data['contact_id']) && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['contacts']} WHERE id=%d",(int)$data['contact_id'])))return new WP_Error('not_found','Contact not found.',array('status'=>404));
        if($type==='orders' && !empty($data['quote_id']) && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['quotes']} WHERE id=%d",(int)$data['quote_id'])))return new WP_Error('not_found','Quote not found.',array('status'=>404));
        if($type==='orders' && !empty($data['company_id']) && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['companies']} WHERE id=%d",(int)$data['company_id'])))return new WP_Error('not_found','Company not found.',array('status'=>404));
        if($type==='orders' && !empty($data['contact_id']) && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['contacts']} WHERE id=%d",(int)$data['contact_id'])))return new WP_Error('not_found','Contact not found.',array('status'=>404));
        if($type==='invoices' && !empty($data['order_id']) && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['orders']} WHERE id=%d",(int)$data['order_id'])))return new WP_Error('not_found','Order not found.',array('status'=>404));
        if($type==='payments' && !empty($data['invoice_id'])){
            $invoice_exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['invoices']} WHERE id=%d",(int)$data['invoice_id']));
            if(!$invoice_exists)return new WP_Error('not_found','Invoice not found.',array('status'=>404));
        }
        if(in_array($type,array('leads','contacts','companies','opportunities'),true) && empty($data['owner_id']))$data['owner_id']=get_current_user_id();
        if($type==='tasks' && empty($data['assigned_to']))$data['assigned_to']=get_current_user_id();
        if($type==='notes' && empty($data['created_by']))$data['created_by']=get_current_user_id();
        if(in_array($type,array('quotes','orders','payments'),true) && empty($data['created_by']) && isset($data['created_by']))$data['created_by']=get_current_user_id();
        $now=current_time('mysql');
        if(!isset($data['created_at']))$data['created_at']=$now;
        if(!isset($data['updated_at']))$data['updated_at']=$now;
        $wpdb->insert($this->t[$cfg['table']],$data);
        if(!$wpdb->insert_id)return new WP_Error('db_error',$wpdb->last_error?:'Could not create record.',array('status'=>500));
        $id=$wpdb->insert_id;
        if($type==='payments') $this->reconcile_invoice((int)($data['invoice_id']??0));
        $this->audit('create',$type,$id);
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t[$cfg['table']]} WHERE id=%d",$id));
        return new WP_REST_Response(array('data'=>$row),201);
    }

    public function update_resource($request) {
        global $wpdb;
        $type=$request['type'];$cfg=$this->cfg($type);
        if(!$cfg)return new WP_Error('invalid_resource','Invalid resource.',array('status'=>400));
        $input=$request->get_json_params();if(!is_array($input))$input=array();
        $data=$this->sanitize_data($type,$input,true);if(is_wp_error($data))return $data;
        if($type==='payments' && array_key_exists('invoice_id',$data) && $data['invoice_id']){
            $invoice_exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['invoices']} WHERE id=%d",(int)$data['invoice_id']));
            if(!$invoice_exists)return new WP_Error('not_found','Invoice not found.',array('status'=>404));
        }
        $data['updated_at']=current_time('mysql');
        $id=(int)$request['id'];
        $record_exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t[$cfg['table']]} WHERE id=%d",$id));
        if(!$record_exists)return new WP_Error('not_found','Record not found.',array('status'=>404));
        $old_invoice_id=0;
        if($type==='payments')$old_invoice_id=(int)$wpdb->get_var($wpdb->prepare("SELECT invoice_id FROM {$this->t['payments']} WHERE id=%d",$id));
        $updated=$wpdb->update($this->t[$cfg['table']],$data,array('id'=>$id));
        if($updated===false)return new WP_Error('db_error',$wpdb->last_error?:'Could not update record.',array('status'=>500));
        if($type==='payments'){
            $new_invoice_id=(int)$wpdb->get_var($wpdb->prepare("SELECT invoice_id FROM {$this->t['payments']} WHERE id=%d",$id));
            $this->reconcile_invoice($new_invoice_id);
            if($old_invoice_id && $old_invoice_id!==$new_invoice_id)$this->reconcile_invoice($old_invoice_id);
        }
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
        if($type==='quotes'){
            $linked_order=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['orders']} WHERE quote_id=%d LIMIT 1",$id));
            if($linked_order)return new WP_Error('conflict','Quote has an order and cannot be deleted.',array('status'=>409));
        }
        if($type==='orders'){
            $linked_invoice=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['invoices']} WHERE order_id=%d LIMIT 1",$id));
            if($linked_invoice)return new WP_Error('conflict','Order has an invoice and cannot be deleted.',array('status'=>409));
        }
        $payment_invoice_id=0;
        if($type==='payments')$payment_invoice_id=(int)$wpdb->get_var($wpdb->prepare("SELECT invoice_id FROM {$this->t['payments']} WHERE id=%d",$id));
        $deleted=$wpdb->delete($this->t[$cfg['table']],array('id'=>$id));
        if($deleted===false)return new WP_Error('db_error',$wpdb->last_error?:'Could not delete record.',array('status'=>500));
        if($type==='payments' && $payment_invoice_id)$this->reconcile_invoice($payment_invoice_id);
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
        $media_id=(int)($p['media_asset_id']??0);
        if($media_id && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['media']} WHERE id=%d AND active=1",$media_id)))return new WP_Error('not_found','Media asset not found.',array('status'=>404));
        $data=array('name'=>$this->clean($p['name']),'channel'=>$this->clean($p['channel']??'whatsapp'),'subject'=>$this->clean($p['subject']??''),'body'=>$this->textarea($p['body']),'media_asset_id'=>$media_id,'active'=>1,'created_at'=>$now,'updated_at'=>$now);
        if(!$wpdb->insert($this->t['templates'],$data))return new WP_Error('db_error',$wpdb->last_error?:'Could not create template.',array('status'=>500));
        $this->audit('create','message_template',$wpdb->insert_id);
        return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['templates']} WHERE id=%d",$wpdb->insert_id))),201);
    }

    public function update_template($request) {
        global $wpdb;$id=(int)$request['id'];$p=$request->get_json_params();$data=array();
        foreach(array('name','channel','subject') as $k)if(isset($p[$k]))$data[$k]=$this->clean($p[$k]);
        if(isset($p['body']))$data['body']=$this->textarea($p['body']);
        if(isset($p['media_asset_id'])){
            $media_id=(int)$p['media_asset_id'];
            if($media_id && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['media']} WHERE id=%d AND active=1",$media_id)))return new WP_Error('not_found','Media asset not found.',array('status'=>404));
            $data['media_asset_id']=$media_id;
        }
        if(isset($p['active']))$data['active']=$this->bool_value($p['active']);
        $data['updated_at']=current_time('mysql');$wpdb->update($this->t['templates'],$data,array('id'=>$id));
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['templates']} WHERE id=%d",$id));if(!$row)return new WP_Error('not_found','Template not found.',array('status'=>404));
        $this->audit('update','message_template',$id);return rest_ensure_response(array('data'=>$row));
    }

    public function delete_template($request){
        global $wpdb;
        $id=(int)$request['id'];
        $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['templates']} WHERE id=%d",$id));
        if(!$exists)return new WP_Error('not_found','Template not found.',array('status'=>404));
        $wpdb->update($this->t['templates'],array('active'=>0,'updated_at'=>current_time('mysql')),array('id'=>$id));
        $this->audit('delete','message_template',$id);
        return rest_ensure_response(array('success'=>true,'id'=>$id));
    }

    public function media($request) {
        global $wpdb;$rows=$wpdb->get_results("SELECT * FROM {$this->t['media']} WHERE active=1 ORDER BY name");return rest_ensure_response(array('data'=>$rows));
    }

    public function create_media($request) {
        global $wpdb;$p=$request->get_json_params();
        if(empty($p['name'])||empty($p['url']))return new WP_Error('validation','Media name and URL are required.',array('status'=>400));
        $now=current_time('mysql');$data=array('name'=>$this->clean($p['name']),'description'=>$this->textarea($p['description']??''),'asset_type'=>$this->clean($p['asset_type']??'document'),'url'=>esc_url_raw($p['url']),'thumbnail_url'=>esc_url_raw($p['thumbnail_url']??''),'mime_type'=>$this->clean($p['mime_type']??''),'active'=>1,'is_active'=>1,'created_at'=>$now,'updated_at'=>$now);
        if(!$wpdb->insert($this->t['media'],$data))return new WP_Error('db_error',$wpdb->last_error?:'Could not create media asset.',array('status'=>500));
        $this->audit('create','media_asset',$wpdb->insert_id);return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['media']} WHERE id=%d",$wpdb->insert_id))),201);
    }

    public function update_media($request) {
        global $wpdb;$id=(int)$request['id'];$p=$request->get_json_params();$data=array();
        foreach(array('name','asset_type','mime_type') as $k)if(isset($p[$k]))$data[$k]=$this->clean($p[$k]);
        if(isset($p['description']))$data['description']=$this->textarea($p['description']);
        foreach(array('url','thumbnail_url') as $k)if(isset($p[$k]))$data[$k]=esc_url_raw($p[$k]);
        if(isset($p['active'])){$data['active']=$this->bool_value($p['active']);$data['is_active']=$data['active'];}
        $data['updated_at']=current_time('mysql');$wpdb->update($this->t['media'],$data,array('id'=>$id));$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['media']} WHERE id=%d",$id));if(!$row)return new WP_Error('not_found','Media asset not found.',array('status'=>404));$this->audit('update','media_asset',$id);return rest_ensure_response(array('data'=>$row));
    }

    public function delete_media($request){
        global $wpdb;
        $id=(int)$request['id'];
        $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['media']} WHERE id=%d",$id));
        if(!$exists)return new WP_Error('not_found','Media asset not found.',array('status'=>404));
        $wpdb->update($this->t['media'],array('active'=>0,'is_active'=>0,'updated_at'=>current_time('mysql')),array('id'=>$id));
        $this->audit('delete','media_asset',$id);
        return rest_ensure_response(array('success'=>true,'id'=>$id));
    }

    public function convert_lead($request) {
        global $wpdb;
        $id=(int)$request['id'];
        $lead=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['leads']} WHERE id=%d",$id));
        if(!$lead)return new WP_Error('not_found','Lead not found.',array('status'=>404));
        $now=current_time('mysql');
        $contact_id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['contacts']} WHERE email=%s OR phone=%s ORDER BY id DESC LIMIT 1",$lead->email,$lead->phone));
        $existing_opp_id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['opportunities']} WHERE description=%s ORDER BY id DESC LIMIT 1",'Converted from lead #'.$id));
        if($lead->status==='converted' && $contact_id){
            return rest_ensure_response(array('success'=>true,'lead_id'=>$id,'contact_id'=>$contact_id,'opportunity_id'=>$existing_opp_id,'already_converted'=>true));
        }
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

    public function whatsapp_prepare($request) {
        global $wpdb;
        $p=$request->get_json_params();
        $entity_type=sanitize_key($p['entity_type']??'lead');
        $entity_id=(int)($p['entity_id']??0);
        $map=array('lead'=>'leads','leads'=>'leads','contact'=>'contacts','contacts'=>'contacts','company'=>'companies','companies'=>'companies');
        if(!isset($map[$entity_type]))return new WP_Error('invalid_entity','WhatsApp is currently available for leads, contacts and companies.',array('status'=>400));
        $table=$this->t[$map[$entity_type]];
        $record=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$entity_id));
        if(!$record)return new WP_Error('not_found','CRM record not found.',array('status'=>404));

        $body=$this->textarea($p['body']??'');
        $template_id=(int)($p['template_id']??0);
        $media_id=(int)($p['media_asset_id']??$p['media_id']??0);
        $asset=$media_id?$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['media']} WHERE id=%d AND active=1",$media_id)):null;
        if(!$body && $template_id)$body=(string)$wpdb->get_var($wpdb->prepare("SELECT body FROM {$this->t['templates']} WHERE id=%d AND active=1",$template_id));
        if($asset && strpos($body,$asset->url)===false)$body=trim($body."

".$asset->name.": ".$asset->url);
        $body=$this->render_body($body,(object)array(
            'first_name'=>$record->first_name??'',
            'last_name'=>$record->last_name??'',
            'phone'=>$record->phone??'',
            'company'=>$record->company??$record->name??''
        ),$asset?$asset->url:'');
        if(!$body)return new WP_Error('empty_message','Message cannot be empty.',array('status'=>400));
        $phone=$this->phone($record->phone??'');
        if(!$phone)return new WP_Error('missing_phone','This CRM record does not have a valid WhatsApp phone number.',array('status'=>400));

        $now=current_time('mysql');
        $conv=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['conversations']} WHERE channel='whatsapp' AND ((lead_id=%d AND %s='leads') OR (contact_id=%d AND %s='contacts')) ORDER BY updated_at DESC LIMIT 1",$entity_id,$map[$entity_type],$entity_id,$map[$entity_type]));
        if(!$conv){
            $wpdb->insert($this->t['conversations'],array(
                'lead_id'=>$map[$entity_type]==='leads'?$entity_id:0,
                'contact_id'=>$map[$entity_type]==='contacts'?$entity_id:0,
                'channel'=>'whatsapp','external_contact'=>$phone,'phone'=>$phone,
                'assigned_to'=>get_current_user_id(),'status'=>'open','last_message'=>$body,
                'last_message_at'=>$now,'created_at'=>$now,'updated_at'=>$now
            ));
            $conv=$wpdb->insert_id;
        } else {
            $wpdb->update($this->t['conversations'],array('last_message'=>$body,'last_message_at'=>$now,'updated_at'=>$now),array('id'=>$conv));
        }
        $wpdb->insert($this->t['messages'],array(
            'conversation_id'=>$conv,'sender_id'=>get_current_user_id(),'direction'=>'outbound',
            'message_type'=>$asset?'file':'text','body'=>$body,'media_url'=>$asset?$asset->url:'',
            'status'=>'prepared','metadata'=>wp_json_encode(array('method'=>'click_to_chat','entity_type'=>$entity_type,'entity_id'=>$entity_id,'template_id'=>$template_id,'media_asset_id'=>$media_id)),
            'created_at'=>$now
        ));
        $this->audit('whatsapp_redirect',$entity_type,$entity_id,array('phone'=>$phone,'media_asset_id'=>$media_id));
        $encoded=rawurlencode($body);
        return rest_ensure_response(array(
            'urls'=>array(
                'mobile'=>'https://wa.me/'.$phone.'?text='.$encoded,
                'web'=>'https://web.whatsapp.com/send?phone='.$phone.'&text='.$encoded,
                'desktop'=>'whatsapp://send?phone='.$phone.'&text='.$encoded,
                'mobile_personal'=>'intent://send?phone='.$phone.'&text='.$encoded.'#Intent;scheme=whatsapp;package=com.whatsapp;end',
                'mobile_business'=>'intent://send?phone='.$phone.'&text='.$encoded.'#Intent;scheme=whatsapp;package=com.whatsapp.w4b;end'
            ),
            'message'=>$body,'phone'=>$phone,
            'targets'=>array('web','desktop','mobile_personal','mobile_business'),
            'note'=>'The message is prepared; WhatsApp still requires the user to press Send.'
        ));
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
            'desktop'=>'whatsapp://send?phone='.$phone.'&text='.$encoded,
            'mobile_personal'=>'intent://send?phone='.$phone.'&text='.$encoded.'#Intent;scheme=whatsapp;package=com.whatsapp;end',
            'mobile_business'=>'intent://send?phone='.$phone.'&text='.$encoded.'#Intent;scheme=whatsapp;package=com.whatsapp.w4b;end'
        ),'message'=>$body,'phone'=>$phone,'note'=>'WhatsApp opens with the message prepared. Press Send in WhatsApp.',
            'targets'=>array('web','desktop','mobile_personal','mobile_business')));
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
        if(!$wpdb->insert($this->t['conversations'],$data))return new WP_Error('db_error',$wpdb->last_error?:'Could not create conversation.',array('status'=>500));
        $this->audit('create','conversation',$wpdb->insert_id);return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['conversations']} WHERE id=%d",$wpdb->insert_id))),201);
    }

    public function messages($request){global $wpdb;$id=(int)$request['id'];$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['messages']} WHERE conversation_id=%d ORDER BY created_at ASC",$id));return rest_ensure_response(array('data'=>$rows));}

    public function create_message($request) {
        global $wpdb;$id=(int)$request['id'];$conversation_exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['conversations']} WHERE id=%d",$id));if(!$conversation_exists)return new WP_Error('not_found','Conversation not found.',array('status'=>404));$p=$request->get_json_params();$body=$this->textarea($p['body']??'');if(!$body)return new WP_Error('validation','Message body is required.',array('status'=>400));$now=current_time('mysql');
        $direction=$this->clean($p['direction']??'outbound');
        if(!in_array($direction,array('inbound','outbound'),true))return new WP_Error('validation','Direction must be inbound or outbound.',array('status'=>400));
        if(!$wpdb->insert($this->t['messages'],array('conversation_id'=>$id,'sender_id'=>get_current_user_id(),'direction'=>$direction,'message_type'=>$this->clean($p['message_type']??'text'),'body'=>$body,'media_url'=>esc_url_raw($p['media_url']??''),'status'=>'prepared','metadata'=>wp_json_encode($p['metadata']??array()),'created_at'=>$now)))return new WP_Error('db_error',$wpdb->last_error?:'Could not create message.',array('status'=>500));
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

    public function tag_entities($request){
        global $wpdb;
        $tag_id=(int)$request['id'];
        $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['tags']} WHERE id=%d",$tag_id));
        if(!$exists)return new WP_Error('not_found','Tag not found.',array('status'=>404));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT entity_type,entity_id FROM {$this->t['entity_tags']} WHERE tag_id=%d ORDER BY entity_type,entity_id",$tag_id));
        return rest_ensure_response(array('data'=>$rows));
    }

    public function assign_tag_entity($request){
        global $wpdb;
        $tag_id=(int)$request['id'];
        $p=$request->get_json_params();
        $entity_type=sanitize_key($p['entity_type']??'');
        $entity_id=(int)($p['entity_id']??0);
        if(!$entity_type||!$entity_id)return new WP_Error('validation','Entity type and entity ID are required.',array('status'=>400));
        $tag_exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['tags']} WHERE id=%d",$tag_id));
        if(!$tag_exists)return new WP_Error('not_found','Tag not found.',array('status'=>404));
        $allowed=array('lead'=>'leads','contact'=>'contacts','company'=>'companies','opportunity'=>'opportunities','task'=>'tasks','product'=>'products','call'=>'calls','campaign'=>'campaigns','automation'=>'automations','quote'=>'quotes','order'=>'orders','invoice'=>'invoices','payment'=>'payments');
        if(!isset($allowed[$entity_type]))return new WP_Error('validation','Unsupported entity type.',array('status'=>400));
        $entity_exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t[$allowed[$entity_type]]} WHERE id=%d",$entity_id));
        if(!$entity_exists)return new WP_Error('not_found','Entity not found.',array('status'=>404));
        $inserted=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$this->t['entity_tags']} (tag_id,entity_type,entity_id) VALUES (%d,%s,%d)",$tag_id,$entity_type,$entity_id));
        if($inserted===false)return new WP_Error('db_error',$wpdb->last_error?:'Could not assign tag.',array('status'=>500));
        if($inserted===0)return rest_ensure_response(array('success'=>true,'existing'=>true,'tag_id'=>$tag_id,'entity_type'=>$entity_type,'entity_id'=>$entity_id));
        $this->audit('assign','tag',$tag_id,array('entity_type'=>$entity_type,'entity_id'=>$entity_id));
        return rest_ensure_response(array('success'=>true,'tag_id'=>$tag_id,'entity_type'=>$entity_type,'entity_id'=>$entity_id));
    }

    public function remove_tag_entity($request){
        global $wpdb;
        $tag_id=(int)$request['tag_id'];
        $entity_type=sanitize_key($request['entity_type']);
        $entity_id=(int)$request['entity_id'];
        $deleted=$wpdb->delete($this->t['entity_tags'],array('tag_id'=>$tag_id,'entity_type'=>$entity_type,'entity_id'=>$entity_id));
        if(!$deleted)return new WP_Error('not_found','Tag assignment not found.',array('status'=>404));
        $this->audit('unassign','tag',$tag_id,array('entity_type'=>$entity_type,'entity_id'=>$entity_id));
        return rest_ensure_response(array('success'=>true));
    }

    public function delete_tag($request){
        global $wpdb;
        $id=(int)$request['id'];
        $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['tags']} WHERE id=%d",$id));
        if(!$exists)return new WP_Error('not_found','Tag not found.',array('status'=>404));
        $wpdb->delete($this->t['entity_tags'],array('tag_id'=>$id));
        $wpdb->delete($this->t['tags'],array('id'=>$id));
        $this->audit('delete','tag',$id);
        return rest_ensure_response(array('success'=>true,'id'=>$id));
    }

    public function pipelines() {
        global $wpdb;$pipes=$wpdb->get_results("SELECT * FROM {$this->t['pipelines']} ORDER BY is_default DESC,name");
        foreach($pipes as $p)$p->stages=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['stages']} WHERE pipeline_id=%d ORDER BY position",$p->id));
        return rest_ensure_response(array('data'=>$pipes));
    }

    public function create_pipeline($request) {
        global $wpdb;$p=$request->get_json_params();$now=current_time('mysql');if(empty($p['name']))return new WP_Error('validation','Pipeline name is required.',array('status'=>400));
        if(!$wpdb->insert($this->t['pipelines'],array('name'=>$this->clean($p['name']),'description'=>$this->textarea($p['description']??''),'is_default'=>!empty($p['is_default'])?1:0,'created_at'=>$now,'updated_at'=>$now)))return new WP_Error('db_error',$wpdb->last_error?:'Could not create pipeline.',array('status'=>500));
        $id=$wpdb->insert_id;
        $this->audit('create','pipeline',$id);return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['pipelines']} WHERE id=%d",$id))),201);
    }

    public function stages($request){global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['stages']} WHERE pipeline_id=%d ORDER BY position",(int)$request['id']));return rest_ensure_response(array('data'=>$rows));}

    public function create_stage($request) {
        global $wpdb;
        $pipeline_id=(int)$request['id'];
        $pipeline_exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['pipelines']} WHERE id=%d",$pipeline_id));
        if(!$pipeline_exists)return new WP_Error('not_found','Pipeline not found.',array('status'=>404));
        $p=$request->get_json_params();$now=current_time('mysql');if(empty($p['name']))return new WP_Error('validation','Stage name is required.',array('status'=>400));
        $pos=max(1,(int)($p['position']??1));
        $probability=min(100,max(0,(int)($p['probability']??0)));
        if(!$wpdb->insert($this->t['stages'],array('pipeline_id'=>(int)$request['id'],'name'=>$this->clean($p['name']),'position'=>$pos,'probability'=>$probability,'stage_color'=>$this->clean($p['stage_color']??''),'created_at'=>$now,'updated_at'=>$now)))return new WP_Error('db_error',$wpdb->last_error?:'Could not create pipeline stage.',array('status'=>500));
        $this->audit('create','pipeline_stage',$wpdb->insert_id);return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['stages']} WHERE id=%d",$wpdb->insert_id))),201);
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

    public function quote_to_order($request){
        global $wpdb;
        $quote_id=(int)$request['id'];
        $quote=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['quotes']} WHERE id=%d",$quote_id));
        if(!$quote)return new WP_Error('not_found','Quote not found.',array('status'=>404));
        $now=current_time('mysql');
        $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['orders']} WHERE quote_id=%d LIMIT 1",$quote_id));
        if($existing)return rest_ensure_response(array('success'=>true,'order_id'=>(int)$existing,'existing'=>true));
        $number='ORD-'.current_time('Ymd').'-'.$quote_id;
        $wpdb->insert($this->t['orders'],array('order_number'=>$number,'quote_id'=>$quote_id,'company_id'=>$quote->company_id,'contact_id'=>$quote->contact_id,'status'=>'pending','currency'=>$quote->currency,'subtotal'=>$quote->subtotal,'tax_total'=>$quote->tax_total,'total'=>$quote->total,'notes'=>$quote->notes,'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now));
        if(!$wpdb->insert_id)return new WP_Error('db_error',$wpdb->last_error?:'Could not create order.',array('status'=>500));
        $order_id=$wpdb->insert_id;
        $items=$wpdb->get_results($wpdb->prepare("SELECT product_id,description,quantity,unit_price,tax_rate,total FROM {$this->t['quote_items']} WHERE quote_id=%d",$quote_id));
        foreach($items as $item){
            $ok=$wpdb->insert($this->t['order_items'],array('order_id'=>$order_id,'product_id'=>$item->product_id,'description'=>$item->description,'quantity'=>$item->quantity,'unit_price'=>$item->unit_price,'tax_rate'=>$item->tax_rate,'total'=>$item->total));
            if(!$ok){
                $wpdb->delete($this->t['orders'],array('id'=>$order_id));
                return new WP_Error('db_error',$wpdb->last_error?:'Could not copy quote line items to order.',array('status'=>500));
            }
        }
        $wpdb->update($this->t['quotes'],array('status'=>'accepted','updated_at'=>$now),array('id'=>$quote_id));
        $this->audit('convert','quote',$quote_id,array('order_id'=>$order_id));
        return new WP_REST_Response(array('success'=>true,'order_id'=>$order_id),201);
    }

    public function order_to_invoice($request){
        global $wpdb;
        $order_id=(int)$request['id'];
        $order=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['orders']} WHERE id=%d",$order_id));
        if(!$order)return new WP_Error('not_found','Order not found.',array('status'=>404));
        $now=current_time('mysql');
        $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['invoices']} WHERE order_id=%d LIMIT 1",$order_id));
        if($existing)return rest_ensure_response(array('success'=>true,'invoice_id'=>(int)$existing,'existing'=>true));
        $number='INV-'.current_time('Ymd').'-'.$order_id;
        $wpdb->insert($this->t['invoices'],array('invoice_number'=>$number,'order_id'=>$order_id,'company_id'=>$order->company_id,'status'=>'draft','currency'=>$order->currency,'subtotal'=>$order->subtotal,'tax_total'=>$order->tax_total,'total'=>$order->total,'due_date'=>wp_date('Y-m-d',current_time('timestamp')+(30*DAY_IN_SECONDS)),'created_at'=>$now,'updated_at'=>$now));
        if(!$wpdb->insert_id)return new WP_Error('db_error',$wpdb->last_error?:'Could not create invoice.',array('status'=>500));
        $invoice_id=$wpdb->insert_id;
        $wpdb->update($this->t['orders'],array('status'=>'confirmed','updated_at'=>$now),array('id'=>$order_id));
        $this->audit('convert','order',$order_id,array('invoice_id'=>$invoice_id));
        return new WP_REST_Response(array('success'=>true,'invoice_id'=>$invoice_id),201);
    }

    public function quote_items($request){global $wpdb;return rest_ensure_response(array('data'=>$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['quote_items']} WHERE quote_id=%d ORDER BY id",(int)$request['id']))));}
    public function add_quote_item($request){return $this->add_line_item('quote',$request);}
    public function delete_quote_item($request){global $wpdb;$id=(int)$request['id'];$parent=(int)$request['quote_id'];$exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['quote_items']} WHERE id=%d AND quote_id=%d",$id,$parent));if(!$exists)return new WP_Error('not_found','Quote line item not found.',array('status'=>404));$wpdb->delete($this->t['quote_items'],array('id'=>$id,'quote_id'=>$parent));$this->recalc_items('quote',$parent);$this->audit('delete','quote_item',$id);return rest_ensure_response(array('success'=>true,'id'=>$id));}
    public function order_items($request){global $wpdb;return rest_ensure_response(array('data'=>$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['order_items']} WHERE order_id=%d ORDER BY id",(int)$request['id']))));}
    public function add_order_item($request){return $this->add_line_item('order',$request);}
    public function delete_order_item($request){global $wpdb;$id=(int)$request['id'];$parent=(int)$request['order_id'];$exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['order_items']} WHERE id=%d AND order_id=%d",$id,$parent));if(!$exists)return new WP_Error('not_found','Order line item not found.',array('status'=>404));$wpdb->delete($this->t['order_items'],array('id'=>$id,'order_id'=>$parent));$this->recalc_items('order',$parent);$this->audit('delete','order_item',$id);return rest_ensure_response(array('success'=>true,'id'=>$id));}

    private function add_line_item($type,$request){
        global $wpdb;$p=$request->get_json_params();$parent=$type==='quote'?'quote_id':'order_id';$table=$type==='quote'?$this->t['quote_items']:$this->t['order_items'];$parent_table=$type==='quote'?$this->t['quotes']:$this->t['orders'];$id=(int)$request['id'];
        $parent_exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$parent_table} WHERE id=%d",$id));
        if(!$parent_exists)return new WP_Error('not_found',ucfirst($type).' not found.',array('status'=>404));
        if(empty($p['description']))return new WP_Error('validation','Item description is required.',array('status'=>400));
        $qty=max(0.01,(float)($p['quantity']??1));$price=max(0,(float)($p['unit_price']??0));$tax=max(0,(float)($p['tax_rate']??0));$total=$qty*$price*(1+$tax/100);
        $wpdb->insert($table,array($parent=>$id,'product_id'=>(int)($p['product_id']??0),'description'=>$this->textarea($p['description']),'quantity'=>$qty,'unit_price'=>$price,'tax_rate'=>$tax,'total'=>$total));
        if(!$wpdb->insert_id)return new WP_Error('db_error',$wpdb->last_error?:'Could not create line item.',array('status'=>500));
        $item_id=(int)$wpdb->insert_id;
        $this->recalc_items($type,$id);
        $this->audit('create',$type.'_item',$item_id);
        return new WP_REST_Response(array('data'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$item_id))),201);
    }

    public function calendar() {
        global $wpdb;
        $from=current_time('mysql');
        $to=wp_date('Y-m-d H:i:s',current_time('timestamp')+(60*DAY_IN_SECONDS));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['tasks']} WHERE due_at IS NOT NULL AND due_at BETWEEN %s AND %s ORDER BY due_at",$from,$to));
        return rest_ensure_response(array('data'=>$rows));
    }

    public function notifications() {
        global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t['notifications']} WHERE user_id=%d ORDER BY created_at DESC LIMIT 100",get_current_user_id()));return rest_ensure_response(array('data'=>$rows));
    }

    public function notification_read($request) {
        global $wpdb;
        $id=(int)$request['id'];
        $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['notifications']} WHERE id=%d AND user_id=%d",$id,get_current_user_id()));
        if(!$exists)return new WP_Error('not_found','Notification not found.',array('status'=>404));
        $wpdb->update($this->t['notifications'],array('read_at'=>current_time('mysql')),array('id'=>$id,'user_id'=>get_current_user_id()));
        return rest_ensure_response(array('success'=>true,'id'=>$id));
    }

    public function audit_logs(){global $wpdb;return rest_ensure_response(array('data'=>$wpdb->get_results("SELECT * FROM {$this->t['audit']} ORDER BY created_at DESC LIMIT 250")));}

    public function users(){
        $users=get_users(array('fields'=>array('ID','display_name','user_email','roles')));
        return rest_ensure_response(array('data'=>array_map(function($u){return array('id'=>$u->ID,'name'=>$u->display_name,'email'=>$u->user_email,'roles'=>$u->roles);},$users)));
    }

    public function create_user($request){
        $p=$request->get_json_params();
        $email=sanitize_email($p['email']??'');$name=$this->clean($p['name']??'');$password=(string)($p['password']??wp_generate_password(16,true,true));$role=$this->clean($p['role']??'agent');
        if(!$email||!is_email($email)||!$name)return new WP_Error('validation','Name and valid email are required.',array('status'=>400));
        if(email_exists($email))return new WP_Error('exists','A user with this email already exists.',array('status'=>409));
        $username=sanitize_user(current(explode('@',$email)),true);
        if(username_exists($username))$username=$username.'_'.wp_rand(100,999);
        $wp_role=array('owner'=>'omnigocrm_owner','admin'=>'omnigocrm_admin','manager'=>'omnigocrm_manager','agent'=>'omnigocrm_agent','viewer'=>'omnigocrm_viewer');
        $user_id=wp_insert_user(array('user_login'=>$username,'user_email'=>$email,'display_name'=>$name,'user_pass'=>$password,'role'=>$wp_role[$role]??'omnigocrm_agent'));
        if(is_wp_error($user_id))return $user_id;
        $this->audit('create','user',$user_id,array('crm_role'=>$role));
        return new WP_REST_Response(array('data'=>array('id'=>$user_id,'name'=>$name,'email'=>$email,'role'=>$role)),201);
    }

    private function integration_providers_map() {
        return array(
            'google_calendar'=>array(
                'name'=>'Google Calendar',
                'category'=>'Calendar',
                'auth_type'=>'oauth2',
                'auth_url'=>'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url'=>'https://oauth2.googleapis.com/token',
                'scopes'=>array('https://www.googleapis.com/auth/calendar.events'),
                'description'=>'Create and sync CRM meetings, tasks and follow-ups with Google Calendar.'
            ),
            'gmail'=>array(
                'name'=>'Google Gmail',
                'category'=>'Email',
                'auth_type'=>'oauth2',
                'auth_url'=>'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url'=>'https://oauth2.googleapis.com/token',
                'scopes'=>array('https://www.googleapis.com/auth/gmail.send'),
                'description'=>'Send CRM email from Gmail using delegated OAuth access.'
            ),
            'zoho_mail'=>array(
                'name'=>'Zoho Mail',
                'category'=>'Email',
                'auth_type'=>'oauth2',
                'auth_url'=>'https://accounts.zoho.com/oauth/v2/auth',
                'token_url'=>'https://accounts.zoho.com/oauth/v2/token',
                'scopes'=>array('ZohoMail.messages.CREATE,ZohoMail.messages.READ'),
                'description'=>'Connect a Zoho Mail mailbox. The Zoho Accounts data center can be changed in setup.'
            ),
            'microsoft_365'=>array(
                'name'=>'Microsoft 365',
                'category'=>'Calendar & Email',
                'auth_type'=>'oauth2',
                'auth_url'=>'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'token_url'=>'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'scopes'=>array('offline_access','User.Read','Calendars.ReadWrite','Mail.Send'),
                'description'=>'Connect Outlook mail and calendar through Microsoft identity.'
            ),
            'slack'=>array(
                'name'=>'Slack',
                'category'=>'Messaging',
                'auth_type'=>'oauth2',
                'auth_url'=>'https://slack.com/oauth/v2/authorize',
                'token_url'=>'https://slack.com/api/oauth.v2.access',
                'scopes'=>array('chat:write','channels:read'),
                'description'=>'Send CRM notifications and automation messages to Slack.'
            ),
            'zoom'=>array(
                'name'=>'Zoom',
                'category'=>'Meetings',
                'auth_type'=>'oauth2',
                'auth_url'=>'https://zoom.us/oauth/authorize',
                'token_url'=>'https://zoom.us/oauth/token',
                'scopes'=>array(),
                'description'=>'Prepare a Zoom meeting integration for CRM appointments.'
            ),
            'webhook'=>array(
                'name'=>'Custom Webhook',
                'category'=>'Automation',
                'auth_type'=>'webhook',
                'auth_url'=>'',
                'token_url'=>'',
                'scopes'=>array(),
                'description'=>'Connect any external tool that accepts HTTPS webhooks.'
            )
        );
    }

    public function integration_providers() {
        $providers=$this->integration_providers_map();
        $rows=array();
        foreach($providers as $code=>$provider){
            $provider['code']=$code;
            $provider['redirect_uri']=rest_url('omnigocrm/v1/integrations/oauth/callback');
            $rows[]=$provider;
        }
        return rest_ensure_response(array('data'=>$rows));
    }

    private function integration_secret_key() {
        return hash('sha256',wp_salt('auth'),true);
    }

    private function encrypt_integration_secret($value) {
        if($value==='') return '';
        $iv=random_bytes(16);
        $cipher=openssl_encrypt($value,'AES-256-CBC',$this->integration_secret_key(),OPENSSL_RAW_DATA,$iv);
        return base64_encode($iv.$cipher);
    }

    private function decrypt_integration_secret($value) {
        if(!$value) return '';
        $raw=base64_decode($value,true);
        if(!$raw || strlen($raw)<=16) return '';
        return (string)openssl_decrypt(substr($raw,16),'AES-256-CBC',$this->integration_secret_key(),OPENSSL_RAW_DATA,substr($raw,0,16));
    }

    private function integration_secrets() {
        return (array)get_option('omnigocrm_integration_secrets',array());
    }

    private function safe_integration_row($row) {
        if(!$row) return $row;
        $row->config=json_decode($row->config?:'{}');
        if(is_object($row->config)) {
            unset($row->config->client_secret);
            unset($row->config->access_token);
            unset($row->config->refresh_token);
        }
        $secrets=$this->integration_secrets();
        $entry=isset($secrets[$row->id])?(array)$secrets[$row->id]:array();
        $row->connection=array(
            'has_client_credentials'=>!empty($entry['client_secret']),
            'has_access_token'=>!empty($entry['access_token']),
            'has_refresh_token'=>!empty($entry['refresh_token'])
        );
        return $row;
    }

    public function save_integration_credentials($request) {
        global $wpdb;
        $id=(int)$request['id'];
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['integrations']} WHERE id=%d",$id));
        if(!$row) return new WP_Error('not_found','Integration not found.',array('status'=>404));
        $p=$request->get_json_params();
        $secrets=$this->integration_secrets();
        $entry=isset($secrets[$id])?(array)$secrets[$id]:array();
        foreach(array('client_secret','api_key','access_token','refresh_token') as $key) {
            if(array_key_exists($key,$p) && $p[$key]!=='') $entry[$key]=$this->encrypt_integration_secret((string)$p[$key]);
        }
        $secrets[$id]=$entry;
        update_option('omnigocrm_integration_secrets',$secrets,false);
        $config=json_decode($row->config?:'{}',true);
        if(!is_array($config))$config=array();
        foreach(array('client_id','scope','auth_url','token_url','data_center') as $key) {
            if(array_key_exists($key,$p))$config[$key]=is_string($p[$key])?sanitize_text_field($p[$key]):$p[$key];
        }
        $wpdb->update($this->t['integrations'],array('config'=>wp_json_encode($config),'updated_at'=>current_time('mysql')),array('id'=>$id));
        $this->audit('update','integration',$id);
        return rest_ensure_response(array('success'=>true,'data'=>$this->safe_integration_row($wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['integrations']} WHERE id=%d",$id)))));
    }

    public function disconnect_integration($request) {
        global $wpdb;
        $id=(int)$request['id'];
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['integrations']} WHERE id=%d",$id));
        if(!$row)return new WP_Error('not_found','Integration not found.',array('status'=>404));

        $secrets=$this->integration_secrets();
        if(isset($secrets[$id])){
            $entry=(array)$secrets[$id];
            unset($entry['access_token'],$entry['refresh_token'],$entry['expires_at']);
            $secrets[$id]=$entry;
            update_option('omnigocrm_integration_secrets',$secrets,false);
        }

        $wpdb->update($this->t['integrations'],array(
            'status'=>'disabled',
            'updated_at'=>current_time('mysql')
        ),array('id'=>$id));

        $this->audit('disconnect','integration',$id);
        return rest_ensure_response(array(
            'success'=>true,
            'data'=>$this->safe_integration_row($wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['integrations']} WHERE id=%d",$id)))
        ));
    }

    public function integration_oauth_start($request) {
        global $wpdb;
        $id=(int)$request['id'];
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['integrations']} WHERE id=%d",$id));
        if(!$row)return new WP_Error('not_found','Integration not found.',array('status'=>404));
        $provider=$this->integration_providers_map()[$row->type]??null;
        if(!$provider || $provider['auth_type']!=='oauth2')return new WP_Error('unsupported','This integration does not use OAuth2.',array('status'=>400));
        $config=json_decode($row->config?:'{}',true);if(!is_array($config))$config=array();
        $client_id=sanitize_text_field($config['client_id']??'');
        if(!$client_id)return new WP_Error('missing_client_id','Add the OAuth Client ID before connecting.',array('status'=>400));
        $auth_url=$config['auth_url']??$provider['auth_url'];
        $scope=$config['scope']??implode(' ',$provider['scopes']);
        $state=wp_generate_password(48,false,false);
        set_transient('omnigocrm_oauth_'.$state,array('integration_id'=>$id,'user_id'=>get_current_user_id(),'provider'=>$row->type),10*MINUTE_IN_SECONDS);
        $redirect=rest_url('omnigocrm/v1/integrations/oauth/callback');
        $params=array(
            'client_id'=>$client_id,
            'redirect_uri'=>$redirect,
            'response_type'=>'code',
            'scope'=>$scope,
            'state'=>$state,
            'access_type'=>'offline',
            'prompt'=>'consent'
        );
        return rest_ensure_response(array('url'=>add_query_arg($params,$auth_url),'redirect_uri'=>$redirect));
    }

    public function integration_oauth_callback($request) {
        global $wpdb;
        $state=sanitize_text_field($request->get_param('state'));
        $code=sanitize_text_field($request->get_param('code'));
        $state_data=$state?get_transient('omnigocrm_oauth_'.$state):false;
        if(!$state_data || empty($state_data['integration_id']) || !$code) {
            return new WP_Error('oauth_state','Invalid or expired OAuth state.',array('status'=>400));
        }
        delete_transient('omnigocrm_oauth_'.$state);
        $id=(int)$state_data['integration_id'];
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['integrations']} WHERE id=%d",$id));
        if(!$row)return new WP_Error('not_found','Integration not found.',array('status'=>404));
        $provider=$this->integration_providers_map()[$row->type]??null;
        $config=json_decode($row->config?:'{}',true);if(!is_array($config))$config=array();
        $secrets=$this->integration_secrets();$secret_entry=isset($secrets[$id])?(array)$secrets[$id]:array();
        $client_secret=$this->decrypt_integration_secret($secret_entry['client_secret']??'');
        $token_url=$config['token_url']??($provider['token_url']??'');
        if(!$client_secret || !$token_url)return new WP_Error('oauth_credentials','OAuth credentials are incomplete.',array('status'=>400));
        $response=wp_safe_remote_post($token_url,array('timeout'=>20,'body'=>array(
            'client_id'=>$config['client_id'],
            'client_secret'=>$client_secret,
            'code'=>$code,
            'grant_type'=>'authorization_code',
            'redirect_uri'=>rest_url('omnigocrm/v1/integrations/oauth/callback')
        )));
        if(is_wp_error($response))return $response;
        $body=json_decode(wp_remote_retrieve_body($response),true);
        if(!is_array($body) || empty($body['access_token'])) {
            return new WP_Error('oauth_token','OAuth token exchange failed.',array('status'=>400,'provider_response'=>$body));
        }
        if(!empty($body['access_token']))$secret_entry['access_token']=$this->encrypt_integration_secret($body['access_token']);
        if(!empty($body['refresh_token']))$secret_entry['refresh_token']=$this->encrypt_integration_secret($body['refresh_token']);
        if(isset($body['expires_in']))$secret_entry['expires_at']=time()+(int)$body['expires_in'];
        $secrets[$id]=$secret_entry;update_option('omnigocrm_integration_secrets',$secrets,false);
        $wpdb->update($this->t['integrations'],array('status'=>'connected','updated_at'=>current_time('mysql')),array('id'=>$id));
        $target=admin_url('admin.php?page=omnigocrm');
        wp_safe_redirect(add_query_arg(array('integration'=>'connected','integration_id'=>$id),$target));
        exit;
    }

    public function settings(){
        $defaults=array('business_name'=>get_bloginfo('name'),'currency'=>'INR','timezone'=>wp_timezone_string(),'lead_default_status'=>'new','whatsapp_default_template'=>'','whatsapp_default_target'=>'web','whatsapp_mobile_target'=>'personal','company_website'=>home_url(),'notifications'=>1);
        return rest_ensure_response(array('data'=>wp_parse_args(get_option('omnigocrm_settings',array()),$defaults)));
    }

    public function save_settings($request){
        $p=$request->get_json_params();$current=(array)get_option('omnigocrm_settings',array());$allowed=array('business_name','currency','timezone','lead_default_status','whatsapp_default_template','whatsapp_default_target','whatsapp_mobile_target','company_website','notifications');
        foreach($allowed as $k)if(array_key_exists($k,$p))$current[$k]=in_array($k,array('notifications'),true)?$this->bool_value($p[$k]):$this->clean($p[$k]);
        update_option('omnigocrm_settings',$current);$this->audit('update','settings',0,$current);return rest_ensure_response(array('data'=>$current));
    }

    public function plans(){global $wpdb;return rest_ensure_response(array('data'=>$wpdb->get_results("SELECT id,code,name,description,monthly_price,currency,limits,features FROM {$this->t['plans']} WHERE active=1 ORDER BY monthly_price")));}

    public function subscription(){global $wpdb;$row=$wpdb->get_row("SELECT s.*,p.code plan_code,p.name plan_name,p.monthly_price FROM {$this->t['subscriptions']} s LEFT JOIN {$this->t['plans']} p ON p.id=s.plan_id ORDER BY s.id DESC LIMIT 1");return rest_ensure_response(array('data'=>$row));}

    public function save_subscription($request){global $wpdb;$p=$request->get_json_params();$plan=(int)($p['plan_id']??0);if(!$plan)return new WP_Error('validation','Plan is required.',array('status'=>400));$plan_exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t['plans']} WHERE id=%d AND active=1",$plan));if(!$plan_exists)return new WP_Error('not_found','Active plan not found.',array('status'=>404));$now=current_time('mysql');$existing=$wpdb->get_var("SELECT id FROM {$this->t['subscriptions']} ORDER BY id DESC LIMIT 1");$data=array('plan_id'=>$plan,'status'=>$this->clean($p['status']??'trialing'),'provider'=>$this->clean($p['provider']??''),'provider_subscription_id'=>$this->clean($p['provider_subscription_id']??''),'current_period_start'=>$p['current_period_start']??null,'current_period_end'=>$p['current_period_end']??null,'updated_at'=>$now);if($existing)$wpdb->update($this->t['subscriptions'],$data,array('id'=>$existing));else{$data['created_at']=$now;$wpdb->insert($this->t['subscriptions'],$data);}$this->audit('update','subscription',$existing?:$wpdb->insert_id);return $this->subscription($request);}
    
    public function run_automation($request){
        global $wpdb;$id=(int)$request['id'];$automation=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t['automations']} WHERE id=%d",$id));if(!$automation)return new WP_Error('not_found','Automation not found.',array('status'=>404));if(!(int)$automation->active)return new WP_Error('inactive_automation','Automation is inactive.',array('status'=>409));$now=current_time('mysql');$wpdb->insert($this->t['automation_jobs'],array('automation_id'=>$id,'status'=>'queued','run_at'=>$now,'payload'=>wp_json_encode(array('requested_by'=>get_current_user_id())),'created_at'=>$now,'updated_at'=>$now));$this->audit('enqueue','automation',$id);if(!wp_next_scheduled('omnigocrm_process_jobs'))wp_schedule_single_event(time()+10,'omnigocrm_process_jobs');return rest_ensure_response(array('queued'=>true,'job_id'=>$wpdb->insert_id));
    }

    public function process_jobs(){
        global $wpdb;$now=current_time('mysql');$jobs=$wpdb->get_results($wpdb->prepare("SELECT j.*,a.name automation_name,a.definition,a.trigger_type,a.active FROM {$this->t['automation_jobs']} j LEFT JOIN {$this->t['automations']} a ON a.id=j.automation_id WHERE j.status='queued' AND j.run_at<=%s ORDER BY j.id ASC LIMIT 25",$now));
        foreach($jobs as $job){
            $wpdb->update($this->t['automation_jobs'],array('status'=>'running','updated_at'=>$now),array('id'=>$job->id));
            $definition=json_decode($job->definition?:'{}',true);$ok=true;$error='';
            if(!$job->active){
                $ok=false;$error='Automation is inactive.';
            } elseif(json_last_error()!==JSON_ERROR_NONE || !is_array($definition)){
                $ok=false;$error='Invalid automation definition.';
            }
            if($ok && isset($definition['actions'])&&is_array($definition['actions'])){
                foreach($definition['actions'] as $action){
                    $type=$action['type']??'notify';
                    if($type==='notify'){
                        $user_id=(int)($action['user_id']??get_current_user_id());
                        $ok=(bool)$wpdb->insert($this->t['notifications'],array('user_id'=>$user_id,'type'=>'automation','title'=>$action['title']??$job->automation_name,'body'=>$action['body']??'Automation completed.','data'=>wp_json_encode($action['data']??array()),'created_at'=>$now));
                        if(!$ok)$error=$wpdb->last_error?:'Could not create automation notification.';
                    }elseif($type==='create_task'){
                        $ok=(bool)$wpdb->insert($this->t['tasks'],array('title'=>$action['title']??$job->automation_name,'description'=>$this->textarea($action['description']??''),'status'=>'open','priority'=>$this->clean($action['priority']??'normal'),'assigned_to'=>(int)($action['assigned_to']??0),'created_at'=>$now,'updated_at'=>$now));
                        if(!$ok)$error=$wpdb->last_error?:'Could not create automation task.';
                    }else{
                        $ok=false;$error='Unsupported automation action: '.$type;
                    }
                    if(!$ok)break;
                }
            }
            if($ok)$wpdb->update($this->t['automation_jobs'],array('status'=>'completed','updated_at'=>$now),array('id'=>$job->id));
            else $wpdb->update($this->t['automation_jobs'],array('status'=>'failed','error'=>$error,'updated_at'=>$now),array('id'=>$job->id));
        }
    }

    public function public_lead($request){
        global $wpdb;
        $p=$request->get_json_params();
        if(!empty($p['website_url'])) return new WP_REST_Response(array('success'=>true),201);
        $ip=isset($_SERVER['REMOTE_ADDR'])?sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])):'unknown';
        $key='omnigocrm_public_lead_'.md5($ip);
        $attempts=(int)get_transient($key);
        if($attempts>=20)return new WP_Error('rate_limited','Too many lead submissions. Try again later.',array('status'=>429));
        $first=$this->clean($p['first_name']??'');if(!$first)return new WP_Error('validation','First name is required.',array('status'=>400));
        set_transient($key,$attempts+1,HOUR_IN_SECONDS);
        $now=current_time('mysql');$data=array('first_name'=>$first,'last_name'=>$this->clean($p['last_name']??''),'company'=>$this->clean($p['company']??''),'email'=>sanitize_email($p['email']??''),'phone'=>$this->clean($p['phone']??''),'source'=>$this->clean($p['source']??'website'),'status'=>'new','score'=>0,'created_at'=>$now,'updated_at'=>$now);
        $wpdb->insert($this->t['leads'],$data);
        if(!$wpdb->insert_id)return new WP_Error('db_error','Unable to save lead.',array('status'=>500));
        return new WP_REST_Response(array('success'=>true,'id'=>$wpdb->insert_id),201);
    }
}
