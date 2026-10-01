(() => {
const C = window.OmniGoCRMConfig;
const app = document.getElementById('omnigocrm-app');

const nav = [
  ['dashboard','⌂','Dashboard'],
  ['leads','♙','Leads'],
  ['contacts','♙','Contacts'],
  ['companies','▣','Companies'],
  ['opportunities','◉','Opportunities'],
  ['quotes','▤','Quotes'],
  ['orders','▱','Orders'],
  ['invoices','▧','Invoices'],
  ['products','◇','Products'],
  ['tasks','✓','Tasks'],
  ['calendar','□','Calendar'],
  ['automation','⌘','Automation'],
  ['conversations','✉','Email & SMS'],
  ['campaigns','◌','Campaigns'],
  ['calls','☎','Calls'],
  ['reports','◔','Reports'],
  ['settings','⚙','Settings']
];

const resourceMap = {
  contacts:{label:'Contacts',singular:'Contact',fields:[
    ['first_name','First name','text'],['last_name','Last name','text'],['company','Company','text'],
    ['email','Email','email'],['phone','Phone','text'],['job_title','Job title','text'],
    ['website','Website','url'],['location','Location','text'],['account_id','Company ID','number'],['owner_id','Owner ID','number']
  ]},
  companies:{label:'Companies',singular:'Company',fields:[
    ['name','Company name','text'],['email','Email','email'],['phone','Phone','text'],['website','Website','url'],
    ['industry','Industry','text'],['address','Address','textarea'],['owner_id','Owner ID','number']
  ]},
  opportunities:{label:'Opportunities',singular:'Opportunity',fields:[
    ['name','Opportunity name','text'],['company','Company','text'],['amount','Amount','number'],['currency','Currency','text'],
    ['stage','Stage','select:new|New,qualified|Qualified,proposal|Proposal,negotiation|Negotiation,won|Won,lost|Lost'],
    ['probability','Probability %','number'],['expected_close_date','Expected close date','date'],
    ['pipeline_id','Pipeline ID','number'],['account_id','Company ID','number'],['contact_id','Contact ID','number'],['owner_id','Owner ID','number'],
    ['description','Description','textarea']
  ]},
  quotes:{label:'Quotes',singular:'Quote',fields:[
    ['quote_number','Quote number','text'],['company_id','Company ID','number'],['contact_id','Contact ID','number'],
    ['status','Status','select:draft|Draft,sent|Sent,accepted|Accepted,rejected|Rejected,expired|Expired'],
    ['currency','Currency','text'],['valid_until','Valid until','date'],['notes','Notes','textarea']
  ]},
  orders:{label:'Orders',singular:'Order',fields:[
    ['order_number','Order number','text'],['quote_id','Quote ID','number'],['company_id','Company ID','number'],['contact_id','Contact ID','number'],
    ['status','Status','select:pending|Pending,confirmed|Confirmed,processing|Processing,completed|Completed,cancelled|Cancelled'],
    ['currency','Currency','text'],['notes','Notes','textarea']
  ]},
  invoices:{label:'Invoices',singular:'Invoice',fields:[
    ['invoice_number','Invoice number','text'],['order_id','Order ID','number'],['company_id','Company ID','number'],
    ['status','Status','select:draft|Draft,sent|Sent,partial|Partial,paid|Paid,overdue|Overdue,cancelled|Cancelled'],
    ['currency','Currency','text'],['due_date','Due date','date'],['notes','Notes','textarea']
  ]},
  products:{label:'Products',singular:'Product',fields:[
    ['name','Product name','text'],['sku','SKU','text'],['description','Description','textarea'],
    ['unit_price','Unit price','number'],['tax_rate','Tax rate %','number'],['currency','Currency','text'],['is_active','Active','checkbox']
  ]},
  tasks:{label:'Tasks',singular:'Task',fields:[
    ['title','Task title','text'],['description','Description','textarea'],
    ['status','Status','select:open|Open,in_progress|In progress,completed|Completed,cancelled|Cancelled'],
    ['priority','Priority','select:low|Low,normal|Normal,high|High,urgent|Urgent'],
    ['due_at','Due date & time','datetime-local'],['assigned_to','Assigned user ID','number'],
    ['related_type','Related type','text'],['related_id','Related ID','number']
  ]},
  campaigns:{label:'Campaigns',singular:'Campaign',fields:[
    ['name','Campaign name','text'],['channel','Channel','select:whatsapp|WhatsApp,sms|SMS,email|Email'],
    ['status','Status','select:draft|Draft,scheduled|Scheduled,running|Running,completed|Completed,paused|Paused'],
    ['audience_type','Audience','select:leads|Leads,contacts|Contacts,companies|Companies'],
    ['scheduled_at','Scheduled at','datetime-local'],['settings','Settings JSON','textarea']
  ]},
  calls:{label:'Calls',singular:'Call',fields:[
    ['lead_id','Lead ID','number'],['contact_id','Contact ID','number'],['direction','Direction','select:inbound|Inbound,outbound|Outbound'],
    ['status','Status','select:completed|Completed,missed|Missed,scheduled|Scheduled,cancelled|Cancelled'],
    ['phone','Phone','text'],['duration_seconds','Duration seconds','number'],['recording_url','Recording URL','url'],
    ['started_at','Started at','datetime-local'],['ended_at','Ended at','datetime-local'],['agent_id','Agent ID','number'],['notes','Notes','textarea']
  ]},
  payments:{label:'Payments',singular:'Payment',fields:[
    ['invoice_id','Invoice ID','number'],['amount','Amount','number'],['currency','Currency','text'],
    ['method','Method','select:cash|Cash,bank|Bank transfer,upi|UPI,card|Card,online|Online,other|Other'],
    ['status','Status','select:pending|Pending,paid|Paid,failed|Failed,refunded|Refunded'],
    ['provider','Provider','text'],['provider_reference','Provider reference','text'],['paid_at','Paid at','datetime-local'],['notes','Notes','textarea']
  ]},
  notes:{label:'Notes',singular:'Note',fields:[
    ['body','Note','textarea'],['related_type','Related type','text'],['related_id','Related ID','number']
  ]}
};

const state = {
  view:'dashboard', rows:[], leads:[], selected:null, selectedType:null,
  search:'', status:'', loading:false, modal:null, modalData:{},
  leadTab:'Overview', templates:[], assets:[], conversations:[], thread:null,
  notice:'', error:'', reports:null, settings:null, users:[], integrations:[], plans:[],
  subscription:null, notifications:[], pipelines:[]
};

async function api(path,opt){
  opt = opt || {};
  const headers = Object.assign({'Content-Type':'application/json','X-WP-Nonce':C.nonce}, opt.headers || {});
  const res = await fetch(C.restUrl + path, Object.assign({},opt,{headers:headers}));
  const data = await res.json().catch(function(){return {};});
  if(!res.ok) throw new Error(data.message || data.error || 'Request failed');
  return data;
}
function esc(v){
  return String(v == null ? '' : v).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];});
}
function money(v){ return '₹' + Number(v || 0).toLocaleString('en-IN',{maximumFractionDigits:2}); }
function title(v){ return String(v || '').replaceAll('_',' ').replace(/\b\w/g,function(x){return x.toUpperCase();}); }
function dateFmt(v){ if(!v)return '—'; var d=new Date(v); return isNaN(d.getTime())?esc(v):d.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'}); }
function fieldControl(f,val){
  var key=f[0],label=f[1],type=f[2],v=val == null ? '' : val;
  if(type==='textarea') return '<label>'+esc(label)+'<textarea name="'+esc(key)+'" rows="3">'+esc(v)+'</textarea></label>';
  if(type==='checkbox') return '<label class="check-field"><input type="checkbox" name="'+esc(key)+'" '+(v?'checked':'')+'> '+esc(label)+'</label>';
  if(type.indexOf('select:')===0){
    var opts=type.slice(7).split(',');
    return '<label>'+esc(label)+'<select name="'+esc(key)+'">'+opts.map(function(o){var p=o.split('|');return '<option value="'+esc(p[0])+'" '+(String(v)===p[0]?'selected':'')+'>'+esc(p[1]||p[0])+'</option>';}).join('')+'</select></label>';
  }
  return '<label>'+esc(label)+'<input type="'+esc(type)+'" name="'+esc(key)+'" value="'+esc(v)+'"></label>';
}
function schemaFor(type){ return resourceMap[type] ? resourceMap[type].fields : []; }
function formData(form,fields){
  var out={};
  fields.forEach(function(f){
    var el=form.elements[f[0]];
    if(!el)return;
    if(f[2]==='checkbox') out[f[0]]=el.checked;
    else if(f[2]==='number') out[f[0]]=el.value===''?'':Number(el.value);
    else out[f[0]]=el.value;
  });
  return out;
}
function resourceValue(type,row,key){
  if(key==='amount'||key==='total'||key==='subtotal'||key==='tax_total'||key==='unit_price'||key==='price') return money(row[key]);
  if(key.indexOf('_at')>0||key.indexOf('_date')>0||key==='due_date'||key==='valid_until') return dateFmt(row[key]);
  if(key==='is_active'||key==='active') return row[key] ? 'Yes':'No';
  return String(row[key] == null ? '—' : row[key]).slice(0,80);
}

function renderShell(){
  var navHtml=nav.map(function(n){return '<button data-nav="'+n[0]+'" class="'+(state.view===n[0]?'active':'')+'"><i>'+n[1]+'</i><span>'+n[2]+'</span></button>';}).join('');
  var heading = nav.find(function(n){return n[0]===state.view;});
  app.innerHTML =
    '<div class="og-shell"><aside class="og-side"><div class="og-brand"><span>OG</span><div><b>OmniGoCRM</b><small>WordPress Edition</small></div></div>' +
    '<div class="og-workspace"><div class="workspace-avatar">'+esc((C.userId||'U').toString().slice(0,1))+'</div><div><b>CRM Workspace</b><small>Administrator</small></div></div>' +
    '<nav>'+navHtml+'</nav><div class="og-side-bottom"><div class="og-system">CRM v'+esc(C.version||'0.3.0')+'<br><small>Self-hosted on WordPress</small></div></div></aside>' +
    '<main class="og-main"><header class="og-top"><div><span class="eyebrow">CRM WORKSPACE</span><h1>'+esc(heading?heading[2]:'Dashboard')+'</h1></div>' +
    '<div class="top-tools"><div class="global-search">⌕<input id="global-search" placeholder="Search CRM..." value="'+esc(state.search)+'"></div><button class="icon-btn" id="notify-btn">♢</button><span class="status-chip"><i></i> Connected</span></div></header>' +
    (state.notice?'<div class="og-notice">'+esc(state.notice)+'</div>':'')+(state.error?'<div class="og-error">'+esc(state.error)+'</div>':'') +
    '<section id="og-content"></section></main></div>';
  document.querySelectorAll('[data-nav]').forEach(function(b){b.onclick=function(){state.view=b.dataset.nav;state.selected=null;state.selectedType=null;state.error='';state.notice='';render();};});
  var gs=document.getElementById('global-search');
  if(gs)gs.onchange=function(){state.search=this.value; if(resourceMap[state.view])renderResource(state.view);};
  var nb=document.getElementById('notify-btn'); if(nb)nb.onclick=openNotifications;
}

async function render(){
  renderShell();
  try{
    if(state.view==='dashboard')return renderDashboard();
    if(state.view==='leads')return renderLeads();
    if(state.view==='conversations')return renderConversations();
    if(state.view==='calendar')return renderCalendar();
    if(state.view==='automation')return renderAutomation();
    if(state.view==='reports')return renderReports();
    if(state.view==='settings')return renderSettings();
    return renderResource(state.view);
  }catch(e){state.error=e.message;document.getElementById('og-content').innerHTML='<div class="panel"><div class="empty">'+esc(e.message)+'</div></div>';}
}

async function renderDashboard(){
  var el=document.getElementById('og-content'),d=await api('/dashboard');
  var leads=await api('/leads?limit=5');
  var tasks=await api('/tasks?limit=5&search=');
  el.innerHTML =
    '<div class="page-head"><div><h2>Customer operations overview</h2><p>Monitor leads, pipeline, sales documents and team tasks.</p></div><div class="page-actions"><button class="ghost" data-go="reports">Reports</button><button class="primary" data-go="leads">＋ Add Lead</button></div></div>' +
    '<div class="stat-grid">' +
      metric('Total Leads',d.leads,'CRM database','blue')+metric('Contacts',d.contacts,'People','purple')+metric('Companies',d.companies,'Accounts','green')+metric('Pipeline Value',money(d.pipeline_value),'Open opportunities','orange')+metric('Outstanding',money(d.invoices_outstanding),'Invoices','red')+
    '</div>' +
    '<div class="dashboard-grid"><div class="panel wide"><div class="panel-head"><div><h3>Recent Leads</h3><p>Latest records in your CRM</p></div><button class="link-btn" data-go="leads">View all →</button></div>' +
    miniLeads(leads.data||[])+'</div><div class="panel"><div class="panel-head"><div><h3>Open Tasks</h3><p>Upcoming work</p></div><button class="link-btn" data-go="tasks">View all →</button></div>'+miniTasks(tasks.data||[])+'</div>' +
    '<div class="panel"><div class="panel-head"><div><h3>Sales Snapshot</h3><p>Current document totals</p></div></div><div class="snapshot">'+snap('Paid revenue',money(d.paid_revenue))+snap('Open tasks',d.tasks)+snap('Opportunities',d.opportunities)+snap('Companies',d.companies)+'</div></div>' +
    '<div class="panel wide"><div class="panel-head"><div><h3>CRM Capabilities</h3><p>Feature parity with the SaaS edition</p></div></div><div class="cap-grid">'+['Leads & scoring','Contacts & companies','Sales pipeline','Quotes & orders','Invoices & payments','Omnichannel inbox','WhatsApp composer','Campaigns','Automation + WP-Cron','Reports','Calendar','Users + audit logs'].map(function(x){return '<div>✓ <span>'+x+'</span></div>';}).join('')+'</div></div></div>';
  bindGo();
}
function metric(a,b,c,cls){return '<div class="metric '+cls+'"><div class="metric-icon">◉</div><div><small>'+esc(a)+'</small><strong>'+esc(b)+'</strong><span>'+esc(c)+'</span></div></div>';}
function snap(a,b){return '<div><small>'+esc(a)+'</small><b>'+esc(b)+'</b></div>';}
function miniLeads(rows){if(!rows.length)return '<div class="empty">No leads yet.</div>';return '<div class="mini-table"><div class="mini-head"><span>Name</span><span>Company</span><span>Status</span><span>Value</span></div>'+rows.map(function(r){return '<div class="mini-row"><span class="person"><div class="avatar">'+esc((r.first_name||'?').slice(0,1)+(r.last_name||'').slice(0,1))+'</div><b>'+esc((r.first_name||'')+' '+(r.last_name||''))+'</b></span><span>'+esc(r.company||'—')+'</span><span><label class="badge '+esc(r.status||'new')+'">'+esc(title(r.status||'new'))+'</label></span><span>'+money(r.value)+'</span></div>';}).join('')+'</div>';}
function miniTasks(rows){if(!rows.length)return '<div class="empty">No tasks yet.</div>';return '<div class="task-list">'+rows.map(function(r){return '<div class="task-row"><div class="task-check">'+(r.status==='completed'?'✓':'•')+'</div><div><b>'+esc(r.title)+'</b><small>'+esc(title(r.priority||'normal'))+' · '+esc(dateFmt(r.due_at||r.due_date))+'</small></div></div>';}).join('')+'</div>';}
function bindGo(){document.querySelectorAll('[data-go]').forEach(function(b){b.onclick=function(){state.view=b.dataset.go;render();};});}

async function renderLeads(){
  var res=await api('/leads?limit=100'+(state.search?'&search='+encodeURIComponent(state.search):'')+(state.status?'&status='+encodeURIComponent(state.status):''));
  state.leads=res.data||[];
  var el=document.getElementById('og-content');
  var totals={total:state.leads.length,new:0,qualified:0,converted:0,lost:0};state.leads.forEach(function(l){if(totals[l.status]!=null)totals[l.status]++;});
  el.innerHTML='<div class="page-head"><div><h2>Lead management</h2><p>Capture, qualify, convert and follow up with every lead.</p></div><div class="page-actions"><button class="ghost" id="export-leads">⇩ Export</button><button class="ghost" id="import-leads">⇧ Import</button><button class="primary" id="add-lead">＋ Add Lead</button><input id="csv-file" type="file" accept=".csv" hidden></div></div>' +
    '<div class="lead-kpis">'+[['Total Leads',totals.total],['New Leads',totals.new],['Qualified',totals.qualified],['Converted',totals.converted],['Lost Leads',totals.lost]].map(function(x,i){return '<div class="lead-kpi k'+i+'"><small>'+x[0]+'</small><strong>'+x[1]+'</strong></div>';}).join('')+'</div>' +
    '<div class="filterbar"><div class="table-search">⌕<input id="lead-search" placeholder="Search name, company, email or phone..." value="'+esc(state.search)+'"></div><select id="lead-status"><option value="">All statuses</option>'+['new','contacted','qualified','proposal','negotiation','converted','lost'].map(function(s){return '<option value="'+s+'" '+(state.status===s?'selected':'')+'>'+title(s)+'</option>';}).join('')+'</select><button class="ghost" id="clear-filter">Reset</button></div>' +
    '<div class="panel table-panel"><div class="lead-table"><div class="lead-head"><span>Name</span><span>Company</span><span>Email</span><span>Phone</span><span>Source</span><span>Status</span><span>Value</span><span>Action</span></div>'+ (state.leads.length?state.leads.map(function(r){return '<div class="lead-row" data-lead="'+r.id+'"><span class="person"><div class="avatar">'+esc((r.first_name||'?').slice(0,1)+(r.last_name||'').slice(0,1))+'</div><b>'+esc((r.first_name||'')+' '+(r.last_name||''))+'</b></span><span>'+esc(r.company||'—')+'</span><span>'+esc(r.email||'—')+'</span><span>'+esc(r.phone||'—')+'</span><span><label class="badge source">'+esc(title(r.source||'manual'))+'</label></span><span><label class="badge '+esc(r.status||'new')+'">'+esc(title(r.status||'new'))+'</label></span><span>'+money(r.value)+'</span><span><button class="link-btn open-lead" data-id="'+r.id+'">Open</button></span></div>';}).join(''):'<div class="empty">No leads found.</div>')+'</div></div>';
  document.getElementById('add-lead').onclick=function(){openLeadForm();};
  document.getElementById('export-leads').onclick=function(){exportCSV(state.leads,'omnigocrm-leads.csv');};
  document.getElementById('import-leads').onclick=function(){document.getElementById('csv-file').click();};
  document.getElementById('csv-file').onchange=function(){importCSV(this.files[0]);};
  document.getElementById('lead-search').onchange=function(){state.search=this.value;renderLeads();};
  document.getElementById('lead-status').onchange=function(){state.status=this.value;renderLeads();};
  document.getElementById('clear-filter').onclick=function(){state.search='';state.status='';renderLeads();};
  document.querySelectorAll('.open-lead').forEach(function(b){b.onclick=function(e){e.stopPropagation();openLead(Number(b.dataset.id));};});
  document.querySelectorAll('.lead-row').forEach(function(b){b.onclick=function(){openLead(Number(b.dataset.lead));};});
  if(state.selectedType==='lead'&&state.selected)openLeadPanel(state.selected);
}

async function openLead(id){
  var r=await api('/leads/'+id);state.selected=r.data;state.selectedType='lead';
  state.templates=(await api('/whatsapp/templates?channel=whatsapp')).data||[];
  state.assets=(await api('/whatsapp/assets')).data||[];
  state.modal=null;renderLeads();
}
function openLeadForm(initial){
  state.modal={kind:'lead',mode:initial?'edit':'create',id:initial?initial.id:0,data:initial||{}};
  renderModal();
}
function leadPanel(l){
  return '<aside class="side-detail"><div class="detail-top"><button class="close" id="close-detail">×</button><div class="detail-person"><div class="big-avatar">'+esc((l.first_name||'?').slice(0,1)+(l.last_name||'').slice(0,1))+'</div><div><h2>'+esc((l.first_name||'')+' '+(l.last_name||''))+'</h2><p>'+esc(l.job_title||'Lead')+' · '+esc(l.company||'No company')+'</p></div><label class="badge '+esc(l.status||'new')+'">'+esc(title(l.status||'new'))+'</label></div><div class="detail-actions"><button class="ghost" id="edit-lead">✎ Edit</button><button class="primary" id="convert-lead">₹ Convert</button></div></div>' +
  '<div class="detail-meta"><span>✉ '+esc(l.email||'No email')+'</span><span>☎ '+esc(l.phone||'No phone')+'</span><span>◎ '+esc(title(l.source||'manual'))+'</span></div>' +
  '<div class="detail-score"><div><small>Lead Score</small><strong>'+Number(l.score||0)+'</strong></div><div><small>Value</small><b>'+money(l.value)+'</b></div><div><small>Created</small><b>'+dateFmt(l.created_at)+'</b></div></div>' +
  '<div class="tabs">'+['Overview','Activity','WhatsApp','Notes','Emails','Tasks','Files','Automation','Related Records'].map(function(t){return '<button class="'+(state.leadTab===t?'active':'')+'" data-leadtab="'+t+'">'+t+'</button>';}).join('')+'</div>' +
  '<div id="lead-tab-body">'+renderLeadTab(l)+'</div></aside>';
}
function renderLeadTab(l){
  if(state.leadTab==='Overview')return '<div class="detail-body">'+detailCard('Contact Information',[['First Name',l.first_name],['Last Name',l.last_name],['Email',l.email],['Phone',l.phone],['Company',l.company],['Job Title',l.job_title],['Website',l.website],['Location',l.location]])+detailCard('Lead Details',[['Status',title(l.status)],['Source',title(l.source)],['Score',l.score],['Expected Value',money(l.value)],['Expected Close',dateFmt(l.expected_close_date)],['Industry',l.industry]])+'</div>';
  if(state.leadTab==='WhatsApp')return '<div class="detail-body">'+whatsappComposer(l)+'</div>';
  if(state.leadTab==='Notes')return '<div class="detail-body"><div class="detail-card"><div class="card-title"><h3>Lead Notes</h3><button class="primary small" id="add-lead-note">＋ Add note</button></div><div id="lead-notes-list"><div class="loading">Loading…</div></div></div></div>';
  if(state.leadTab==='Tasks')return '<div class="detail-body"><div class="detail-card"><div class="card-title"><h3>Lead Tasks</h3><button class="primary small" id="add-lead-task">＋ Add task</button></div><div id="lead-tasks-list"><div class="loading">Loading…</div></div></div></div>';
  if(state.leadTab==='Files')return '<div class="detail-body"><div class="detail-card"><div class="card-title"><h3>Media Assets</h3><button class="primary small" data-go-setting="media">Manage</button></div>'+ (state.assets.length?state.assets.map(function(a){return '<div class="asset-row"><span>📎</span><div><b>'+esc(a.name)+'</b><small>'+esc(a.url)+'</small></div></div>';}).join(''):'<div class="empty">No media assets.</div>')+'</div></div>';
  if(state.leadTab==='Automation')return '<div class="detail-body"><div class="detail-card"><div class="card-title"><h3>Automations</h3><button class="primary small" data-go="automation">Open</button></div><p>Run a configured automation against this lead from the Automation module.</p></div></div>';
  if(state.leadTab==='Emails')return '<div class="detail-body"><div class="detail-card"><h3>Email & SMS</h3><p>Use the Conversations inbox to manage email, SMS and web conversations linked to this customer.</p><button class="primary" data-go="conversations">Open inbox</button></div></div>';
  if(state.leadTab==='Related Records')return '<div class="detail-body"><div class="detail-card"><h3>Related Records</h3><p>Company, contact and opportunity IDs are shown here after conversion and linked records are created.</p></div></div>';
  return '<div class="detail-body">'+detailCard('Activity',[['Created',dateFmt(l.created_at)],['Last updated',dateFmt(l.updated_at)],['Current status',title(l.status)],['Source',title(l.source)]])+'</div>';
}
function detailCard(h,items){return '<div class="detail-card"><div class="card-title"><h3>'+esc(h)+'</h3></div><div class="detail-fields">'+items.map(function(x){return '<div><small>'+esc(x[0])+'</small><span>'+esc(x[1]==null||x[1]===''?'—':String(x[1]))+'</span></div>';}).join('')+'</div></div>';}

function whatsappComposer(l){
  var t=state.templates||[],a=state.assets||[];
  return '<div class="detail-card whatsapp-composer"><div class="card-title"><div><h3>WhatsApp Composer</h3><p>Prepare a click-to-chat message.</p></div><span class="wa-badge">WhatsApp</span></div>' +
  '<div class="composer-grid"><label>Message template<select id="wa-template"><option value="">Custom message</option>'+t.map(function(x){return '<option value="'+x.id+'">'+esc(x.name)+'</option>';}).join('')+'</select></label>' +
  '<label>Destination<select id="wa-mode"><option value="auto">Auto detect device</option><option value="web">WhatsApp Web</option><option value="desktop">WhatsApp Desktop</option><option value="mobile">WhatsApp mobile</option></select></label></div>' +
  '<label>Message<textarea id="wa-message" rows="6" placeholder="Hi '+esc(l.first_name||'there')+', I wanted to share an update with you."></textarea></label>' +
  '<label>Media asset<select id="wa-asset"><option value="">No media</option>'+a.map(function(x){return '<option value="'+x.id+'">'+esc(x.name)+' · '+esc(x.asset_type)+'</option>';}).join('')+'</select></label>' +
  '<div class="composer-actions"><span>'+esc(l.phone?'To: '+l.phone:'No phone number')+'</span><button class="primary whatsapp-send" id="send-wa" '+(l.phone?'':'disabled')+'>Send WhatsApp ↗</button></div>' +
  '<p class="composer-footnote">The CRM prepares a pre-filled WhatsApp chat. The final Send action occurs inside WhatsApp.</p></div>';
}
function openLeadPanel(l){
  var content=document.getElementById('og-content');
  var table=content.querySelector('.leads-layout');
  if(table)table.insertAdjacentHTML('beforeend',leadPanel(l));else content.insertAdjacentHTML('beforeend','<div class="leads-layout"><div></div>'+leadPanel(l)+'</div>');
  document.getElementById('close-detail').onclick=function(){state.selected=null;state.selectedType=null;renderLeads();};
  document.getElementById('edit-lead').onclick=function(){openLeadForm(l);};
  document.getElementById('convert-lead').onclick=convertLead;
  document.querySelectorAll('[data-leadtab]').forEach(function(b){b.onclick=async function(){state.leadTab=b.dataset.leadtab;state.selected=l;renderLeads();};});
  var send=document.getElementById('send-wa');
  if(send)send.onclick=sendWhatsApp.bind(null,l);
  if(state.leadTab==='Notes')loadLeadNotes(l.id);
  if(state.leadTab==='Tasks')loadLeadTasks(l.id);
}
async function loadLeadNotes(id){
  var box=document.getElementById('lead-notes-list'); if(!box)return;
  var r=await api('/notes?search='+encodeURIComponent(String(id)));var rows=(r.data||[]).filter(function(x){return String(x.related_id)===String(id);});
  box.innerHTML=rows.length?rows.map(function(x){return '<div class="note-row"><b>'+esc(dateFmt(x.created_at))+'</b><p>'+esc(x.body)+'</p></div>';}).join(''):'<div class="empty">No notes yet.</div>';
  var b=document.getElementById('add-lead-note');if(b)b.onclick=async function(){var body=prompt('Note');if(body){await api('/notes',{method:'POST',body:JSON.stringify({body:body,related_type:'lead',related_id:id,lead_id:id})});loadLeadNotes(id);}};
}
async function loadLeadTasks(id){
  var box=document.getElementById('lead-tasks-list');if(!box)return;
  var r=await api('/tasks?limit=100');var rows=(r.data||[]).filter(function(x){return String(x.related_id)===String(id)&&x.related_type==='lead';});
  box.innerHTML=rows.length?rows.map(function(x){return '<div class="note-row"><b>'+esc(x.title)+'</b><p>'+esc(title(x.status))+' · '+esc(dateFmt(x.due_at||x.due_date))+'</p></div>';}).join(''):'<div class="empty">No tasks linked to this lead.</div>';
  var b=document.getElementById('add-lead-task');if(b)b.onclick=async function(){var titleText=prompt('Task title');if(titleText){await api('/tasks',{method:'POST',body:JSON.stringify({title:titleText,related_type:'lead',related_id:id,status:'open',priority:'normal'})});loadLeadTasks(id);}};
}
async function sendWhatsApp(l){
  var body=document.getElementById('wa-message').value,template=Number(document.getElementById('wa-template').value||0),asset=Number(document.getElementById('wa-asset').value||0);
  if(!body.trim()&&template===0){alert('Write a message or choose a template.');return;}
  try{var r=await api('/leads/'+l.id+'/whatsapp/prepare',{method:'POST',body:JSON.stringify({body:body,template_id:template||undefined,media_asset_id:asset||undefined})});var mode=document.getElementById('wa-mode').value;var mobile=/Android|iPhone|iPad|iPod/i.test(navigator.userAgent);var url=mode==='desktop'?r.urls.desktop:mode==='web'?r.urls.web:mode==='mobile'?r.urls.mobile:(mobile?r.urls.mobile:r.urls.web);window.location.href=url;}catch(e){alert(e.message);}
}
async function convertLead(){
  if(!confirm('Convert this lead to a contact and opportunity?'))return;
  try{var r=await api('/leads/'+state.selected.id+'/convert',{method:'POST',body:'{}'});alert('Lead converted. Contact ID: '+r.contact_id+(r.opportunity_id?' · Opportunity ID: '+r.opportunity_id:''));state.selected=null;renderLeads();}catch(e){alert(e.message);}
}

async function renderResource(type){
  var cfg=resourceMap[type];if(!cfg)return;
  var q='?limit=100'+(state.search?'&search='+encodeURIComponent(state.search):'')+(state.status?'&status='+encodeURIComponent(state.status):'');
  var res=await api('/'+type+q);state.rows=res.data||[];
  var el=document.getElementById('og-content');
  var cols=cfg.fields.map(function(f){return f[0];}).slice(0,7);
  el.innerHTML='<div class="page-head"><div><h2>'+esc(cfg.label)+'</h2><p>Manage '+esc(cfg.label.toLowerCase())+' and keep your sales operations moving.</p></div><div class="page-actions"><button class="ghost" id="export-resource">⇩ Export</button><button class="primary" id="new-resource">＋ Add '+esc(cfg.singular)+'</button></div></div>' +
  '<div class="toolbar-card"><div class="table-search">⌕<input id="resource-search" placeholder="Search '+esc(cfg.label.toLowerCase())+'..." value="'+esc(state.search)+'"></div><select id="resource-status"><option value="">All statuses</option>'+['draft','open','in_progress','completed','cancelled','sent','paid','pending','won','lost'].map(function(s){return '<option value="'+s+'" '+(state.status===s?'selected':'')+'>'+title(s)+'</option>';}).join('')+'</select><button class="ghost" id="resource-reset">Reset</button></div>' +
  '<div class="panel table-panel"><table><thead><tr>'+cols.map(function(k){return '<th>'+esc(title(k))+'</th>';}).join('')+'<th>Actions</th></tr></thead><tbody>'+(state.rows.length?state.rows.map(function(r){return '<tr>'+cols.map(function(k){return '<td>'+esc(resourceValue(type,r,k))+'</td>';}).join('')+'<td><button class="link-btn res-open" data-id="'+r.id+'">Open</button> <button class="link-btn res-del" data-id="'+r.id+'">Delete</button></td></tr>';}).join(''):'<tr><td colspan="'+(cols.length+1)+'"><div class="empty">No records yet.</div></td></tr>')+'</tbody></table></div>';
  document.getElementById('new-resource').onclick=function(){openResourceForm(type);};
  document.getElementById('export-resource').onclick=function(){exportCSV(state.rows,'omnigocrm-'+type+'.csv');};
  document.getElementById('resource-search').onchange=function(){state.search=this.value;renderResource(type);};
  document.getElementById('resource-status').onchange=function(){state.status=this.value;renderResource(type);};
  document.getElementById('resource-reset').onclick=function(){state.search='';state.status='';renderResource(type);};
  document.querySelectorAll('.res-open').forEach(function(b){b.onclick=function(){openResource(type,Number(b.dataset.id));};});
  document.querySelectorAll('.res-del').forEach(function(b){b.onclick=async function(){if(confirm('Delete this record?')){await api('/'+type+'/'+b.dataset.id,{method:'DELETE'});renderResource(type);}};});
  if(state.selectedType===type&&state.selected)openResourcePanel(type,state.selected);
}
async function openResource(type,id){var r=await api('/'+type+'/'+id);state.selected=r.data;state.selectedType=type;renderResource(type);}
function openResourceForm(type,initial){
  state.modal={kind:'resource',type:type,mode:initial?'edit':'create',id:initial?initial.id:0,data:initial||{}};
  renderModal();
}
function resourcePanel(type,row){
  var cfg=resourceMap[type];var cols=cfg.fields.slice(0,6);
  return '<aside class="side-detail"><div class="detail-top"><button class="close" id="close-resource">×</button><span class="eyebrow">'+esc(cfg.singular.toUpperCase())+'</span><h2>'+esc(row.name||row.title||row.quote_number||row.order_number||row.invoice_number||cfg.singular+' #'+row.id)+'</h2><div class="detail-actions"><button class="ghost" id="edit-resource">✎ Edit</button><button class="danger" id="delete-resource">Delete</button></div></div><div class="detail-body">'+detailCard(cfg.label,cols.map(function(f){return [f[1],resourceValue(type,row,f[0])];}))+
  ((type==='quotes'||type==='orders')?lineItems(type,row.id):type==='invoices'?invoicePayments(row.id):'')+'</div></aside>';
}
function openResourcePanel(type,row){
  var content=document.getElementById('og-content');var wrap=content.querySelector('.resource-layout');
  if(wrap)wrap.insertAdjacentHTML('beforeend',resourcePanel(type,row));else content.insertAdjacentHTML('beforeend','<div class="resource-layout"><div></div>'+resourcePanel(type,row)+'</div>');
  document.getElementById('close-resource').onclick=function(){state.selected=null;state.selectedType=null;renderResource(type);};
  document.getElementById('edit-resource').onclick=function(){openResourceForm(type,row);};
  document.getElementById('delete-resource').onclick=async function(){if(confirm('Delete this record?')){await api('/'+type+'/'+row.id,{method:'DELETE'});state.selected=null;renderResource(type);}};
  if(type==='quotes'||type==='orders')loadLineItems(type,row.id);
  if(type==='invoices')loadInvoicePayments(row.id);
}
function lineItems(type,id){return '<div class="detail-card"><div class="card-title"><h3>Line items</h3><button class="primary small" id="add-line-item">＋ Add item</button></div><div id="line-items-list"><div class="loading">Loading…</div></div></div>';}
async function loadLineItems(type,id){
  var box=document.getElementById('line-items-list');if(!box)return;
  var r=await api('/'+type+'/'+id+'/items'),rows=r.data||[];
  box.innerHTML=(rows.length?rows.map(function(x){return '<div class="line-row"><span>'+esc(x.description)+'</span><span>'+x.quantity+' × '+money(x.unit_price)+'</span><b>'+money(x.total)+'</b></div>';}).join(''):'<div class="empty">No items.</div>');
  var b=document.getElementById('add-line-item');if(b)b.onclick=async function(){var d=prompt('Item description');if(!d)return;var q=prompt('Quantity','1');var p=prompt('Unit price','0');var tax=prompt('Tax rate %','0');await api('/'+type+'/'+id+'/items',{method:'POST',body:JSON.stringify({description:d,quantity:Number(q||1),unit_price:Number(p||0),tax_rate:Number(tax||0)})});loadLineItems(type,id);};
}
function invoicePayments(id){return '<div class="detail-card"><div class="card-title"><h3>Payments</h3><button class="primary small" id="add-payment">＋ Record payment</button></div><div id="invoice-payments"><div class="loading">Loading…</div></div></div>';}
async function loadInvoicePayments(id){
  var box=document.getElementById('invoice-payments');if(!box)return;var r=await api('/payments?limit=100');var rows=(r.data||[]).filter(function(x){return String(x.invoice_id)===String(id);});
  box.innerHTML=rows.length?rows.map(function(x){return '<div class="line-row"><span>'+esc(title(x.method))+' · '+esc(title(x.status))+'</span><b>'+money(x.amount)+'</b></div>';}).join(''):'<div class="empty">No payments.</div>';
  var b=document.getElementById('add-payment');if(b)b.onclick=async function(){var amount=prompt('Amount');if(amount){await api('/payments',{method:'POST',body:JSON.stringify({invoice_id:id,amount:Number(amount),currency:'INR',method:'upi',status:'paid',paid_at:new Date().toISOString().slice(0,19).replace('T',' ')})});loadInvoicePayments(id);}};
}

async function renderConversations(){
  var r=await api('/conversations');state.conversations=r.data||[];var el=document.getElementById('og-content');
  el.innerHTML='<div class="page-head"><div><h2>Omnichannel Inbox</h2><p>Centralize WhatsApp, SMS, email, calls and web conversations.</p></div><button class="primary" id="new-conversation">＋ New conversation</button></div><div class="inbox-layout"><div class="panel conversation-list">'+(state.conversations.length?state.conversations.map(function(c){return '<button class="conversation-row '+(state.thread&&state.thread.id===c.id?'active':'')+'" data-conv="'+c.id+'"><span class="channel-icon">'+esc((c.channel||'w').slice(0,1).toUpperCase())+'</span><span><b>'+esc(c.subject||c.external_contact||c.phone||'Conversation')+'</b><small>'+esc(c.channel)+' · '+esc(c.last_message||'No messages')+'</small></span><em>'+dateFmt(c.updated_at)+'</em></button>';}).join(''):'<div class="empty">No conversations yet.</div>')+'</div><div class="panel thread-panel" id="thread-panel"><div class="empty">Select a conversation.</div></div></div>';
  document.querySelectorAll('[data-conv]').forEach(function(b){b.onclick=function(){openConversation(Number(b.dataset.conv));};});
  document.getElementById('new-conversation').onclick=async function(){var ch=prompt('Channel: whatsapp, sms, email, call, web','whatsapp');var contact=prompt('Contact');if(contact){var x=await api('/conversations',{method:'POST',body:JSON.stringify({channel:ch||'whatsapp',external_contact:contact})});openConversation(x.data.id);}};
  if(state.thread)drawThread();
}
async function openConversation(id){state.thread=await api('/conversations/'+id+'/messages').then(function(m){var c=state.conversations.find(function(x){return String(x.id)===String(id);});return {id:id,meta:c,messages:m.data||[]};});renderConversations();}
function drawThread(){var box=document.getElementById('thread-panel');if(!box||!state.thread)return;box.innerHTML='<div class="thread-head"><div><h3>'+esc(state.thread.meta.subject||state.thread.meta.external_contact||'Conversation')+'</h3><span>'+esc(state.thread.meta.channel)+'</span></div></div><div class="messages">'+state.thread.messages.map(function(m){return '<div class="msg '+esc(m.direction||'outbound')+'"><div>'+esc(m.body||'')+'</div><small>'+dateFmt(m.created_at)+'</small></div>';}).join('')+'</div><div class="composer-bottom"><textarea id="thread-message" rows="3" placeholder="Write a message..."></textarea><button class="primary" id="send-thread">Send</button></div>';document.getElementById('send-thread').onclick=async function(){var body=document.getElementById('thread-message').value;if(!body)return;await api('/conversations/'+state.thread.id+'/messages',{method:'POST',body:JSON.stringify({body:body,direction:'outbound',message_type:'text'})});openConversation(state.thread.id);};}

async function renderCalendar(){
  var r=await api('/calendar'),rows=r.data||[],el=document.getElementById('og-content');
  el.innerHTML='<div class="page-head"><div><h2>Calendar</h2><p>Upcoming tasks and scheduled follow-ups for the next 60 days.</p></div><button class="primary" id="cal-add">＋ Add Task</button></div><div class="panel calendar-panel"><div class="calendar-grid">'+(rows.length?rows.map(function(x){return '<div class="calendar-card"><span class="cal-date">'+dateFmt(x.due_at||x.due_date)+'</span><h3>'+esc(x.title)+'</h3><p>'+esc(x.description||'')+'</p><small>'+esc(title(x.priority||'normal'))+' · '+esc(title(x.status||'open'))+'</small></div>';}).join(''):'<div class="empty">No scheduled tasks in the next 60 days.</div>')+'</div></div>';document.getElementById('cal-add').onclick=function(){openResourceForm('tasks');};
}

async function renderAutomation(){
  var r=await api('/automations?limit=100'),rows=r.data||[],el=document.getElementById('og-content');
  el.innerHTML='<div class="page-head"><div><h2>Automation</h2><p>Create trigger-based workflows and execute them through WP-Cron.</p></div><button class="primary" id="new-automation">＋ New Automation</button></div><div class="automation-grid">'+(rows.length?rows.map(function(a){return '<div class="panel automation-card"><div class="auto-top"><span class="badge">'+esc(a.active?'Active':'Draft')+'</span><span>'+esc(a.trigger_type)+'</span></div><h3>'+esc(a.name)+'</h3><p>'+esc(a.description||'No description')+'</p><div class="auto-actions"><button class="ghost auto-edit" data-id="'+a.id+'">Edit</button><button class="primary auto-run" data-id="'+a.id+'">Run now</button></div></div>';}).join(''):'<div class="panel"><div class="empty">No automations yet.</div></div>')+'</div>';
  document.getElementById('new-automation').onclick=function(){openResourceForm('automations')};
  document.querySelectorAll('.auto-edit').forEach(function(b){b.onclick=async function(){var x=await api('/automations/'+b.dataset.id);openResourceForm('automations',x.data);};});
  document.querySelectorAll('.auto-run').forEach(function(b){b.onclick=async function(){var x=await api('/automations/'+b.dataset.id+'/run',{method:'POST',body:'{}'});alert('Automation queued as job #'+x.job_id);};});
}
resourceMap.automations={label:'Automations',singular:'Automation',fields:[
  ['name','Name','text'],['trigger_type','Trigger type','text'],['active','Active','checkbox'],['description','Description','textarea'],['definition','Definition JSON','textarea']
]};
resourceMap.integrations={label:'Integrations',singular:'Integration',fields:[
  ['type','Type','text'],['name','Name','text'],['status','Status','select:disabled|Disabled,connected|Connected,error|Error'],['config','Config JSON','textarea'],['secrets_ref','Secrets reference','text']
]};

async function renderReports(){
  var d=await api('/reports/summary');state.reports=d;var el=document.getElementById('og-content');
  el.innerHTML='<div class="page-head"><div><h2>Reports</h2><p>Pipeline, revenue, lead source and conversion performance.</p></div><button class="ghost" id="report-export">⇩ Export data</button></div>' +
  '<div class="stat-grid">'+metric('Won opportunities',d.won,'Converted deals','green')+metric('Lost opportunities',d.lost,'Closed lost','red')+metric('Paid revenue',money(d.paid_revenue),'Recorded payments','blue')+metric('Outstanding',money(d.outstanding),'Unpaid invoices','orange')+'</div>' +
  '<div class="dashboard-grid"><div class="panel"><div class="panel-head"><h3>Leads by Source</h3></div><div class="bar-list">'+(d.leads_by_source||[]).map(function(x){var max=Math.max.apply(null,(d.leads_by_source||[]).map(function(y){return Number(y.count)}).concat([1]));return '<div><span>'+esc(x.source||'Unknown')+'</span><b style="width:'+Math.round(Number(x.count)/max*100)+'%">'+x.count+'</b></div>';}).join('')+'</div></div><div class="panel"><div class="panel-head"><h3>Pipeline by Stage</h3></div><div class="bar-list">'+(d.pipeline_by_stage||[]).map(function(x){return '<div><span>'+esc(title(x.stage))+'</span><b style="width:'+Math.min(100,Number(x.count)*15)+'%">'+esc(String(x.count)+' · '+money(x.value))+'</b></div>';}).join('')+'</div></div><div class="panel wide"><div class="panel-head"><h3>Monthly Revenue</h3></div><div class="revenue-list">'+(d.monthly_revenue||[]).map(function(x){return '<div><span>'+esc(x.month)+'</span><b>'+money(x.revenue)+'</b></div>';}).join('')+'</div></div></div>';
  document.getElementById('report-export').onclick=function(){exportCSV((d.monthly_revenue||[]),'omnigocrm-revenue.csv');};
}

async function renderSettings(){
  var el=document.getElementById('og-content');
  var s=await api('/settings');state.settings=s.data||{};
  var users=await api('/users').catch(function(){return {data:[]};});state.users=users.data||[];
  var ints=await api('/integrations?limit=100').catch(function(){return {data:[]};});state.integrations=ints.data||[];
  var plans=await api('/plans').catch(function(){return {data:[]};});state.plans=plans.data||[];
  var sub=await api('/subscription').catch(function(){return {data:null};});state.subscription=sub.data;
  var pipes=await api('/pipelines').catch(function(){return {data:[]};});state.pipelines=pipes.data||[];
  var tags=await api('/tags').catch(function(){return {data:[]};});state.tags=tags.data||[];
  el.innerHTML='<div class="page-head"><div><h2>Settings</h2><p>Workspace preferences, users, integrations, billing and CRM utilities.</p></div><button class="primary" id="save-settings">Save settings</button></div>' +
  '<div class="settings-tabs">'+['General','Users','Integrations','Templates','Media','Pipelines','Tags','Billing','Audit'].map(function(x){return '<button data-stab="'+x+'">'+x+'</button>';}).join('')+'</div><div class="settings-grid" id="settings-body">'+settingsGeneral()+'</div>';
  document.querySelectorAll('[data-stab]').forEach(function(b){b.onclick=function(){document.querySelectorAll('[data-stab]').forEach(function(x){x.classList.remove('active')});b.classList.add('active');document.getElementById('settings-body').innerHTML=settingsTab(b.dataset.stab);bindSettingsTab(b.dataset.stab);};});
  document.querySelector('[data-stab="General"]').classList.add('active');document.getElementById('save-settings').onclick=saveSettings;
}
function settingsGeneral(){var s=state.settings||{};return '<form id="settings-form"><div class="panel"><h3>General</h3><div class="form-grid">'+fieldControl(['business_name','Business name','text'],s.business_name)+fieldControl(['currency','Currency','text'],s.currency)+fieldControl(['timezone','Timezone','text'],s.timezone)+fieldControl(['lead_default_status','Default lead status','text'],s.lead_default_status)+fieldControl(['company_website','Website','url'],s.company_website)+fieldControl(['whatsapp_default_template','Default WhatsApp template','text'],s.whatsapp_default_template)+fieldControl(['notifications','Notifications','checkbox'],s.notifications)+'</div></div></form>';}
function settingsTab(t){
 if(t==='General')return settingsGeneral();
 if(t==='Users')return '<div class="panel"><div class="card-title"><h3>WordPress CRM Users</h3><button class="primary small" id="add-user">＋ Add user</button></div><table><thead><tr><th>Name</th><th>Email</th><th>Roles</th></tr></thead><tbody>'+state.users.map(function(u){return '<tr><td>'+esc(u.name)+'</td><td>'+esc(u.email)+'</td><td>'+esc((u.roles||[]).join(', '))+'</td></tr>';}).join('')+'</tbody></table></div>';
 if(t==='Integrations')return '<div class="panel"><div class="card-title"><h3>Integrations</h3><button class="primary small" id="add-integration">＋ Add</button></div><table><thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Config</th></tr></thead><tbody>'+state.integrations.map(function(x){return '<tr><td>'+esc(x.name)+'</td><td>'+esc(x.type)+'</td><td>'+esc(title(x.status))+'</td><td>'+esc(x.config||'{}')+'</td></tr>';}).join('')+'</tbody></table></div>';
 if(t==='Templates')return '<div class="panel"><div class="card-title"><h3>WhatsApp / SMS / Email Templates</h3><button class="primary small" id="add-template">＋ Add template</button></div><div id="templates-manage"><div class="loading">Loading…</div></div></div>';
 if(t==='Media')return '<div class="panel"><div class="card-title"><h3>Media Assets</h3><button class="primary small" id="add-media">＋ Add media</button></div><div id="media-manage"><div class="loading">Loading…</div></div></div>';
 if(t==='Pipelines')return '<div class="panel"><div class="card-title"><h3>Sales pipelines</h3><button class="primary small" id="add-pipeline">＋ Add pipeline</button></div>'+(state.pipelines.length?state.pipelines.map(function(p){return '<div class="pipeline-row"><div><b>'+esc(p.name)+'</b><small>'+esc(p.description||'')+'</small></div><span>'+(p.stages||[]).map(function(s){return '<label class="stage-chip">'+esc(s.name)+' · '+s.probability+'%</label>';}).join('')+'</span><button class="ghost add-stage" data-id="'+p.id+'">＋ Stage</button></div>';}).join(''):'<div class="empty">No pipelines.</div>')+'</div>';
 if(t==='Tags')return '<div class="panel"><div class="card-title"><h3>Tags</h3><button class="primary small" id="add-tag">＋ Add tag</button></div>'+(state.tags.length?state.tags.map(function(x){return '<div class="tag-row"><span class="tag-dot" style="background:'+esc(x.color||'#98a2b3')+'"></span><b>'+esc(x.name)+'</b><button class="link-btn tag-del" data-id="'+x.id+'">Delete</button></div>';}).join(''):'<div class="empty">No tags.</div>')+'</div>';
 if(t==='Billing')return '<div class="panel"><h3>Billing / Plan</h3><p>WordPress edition keeps the SaaS plan model locally so the same product can be deployed on shared hosting.</p>'+state.plans.map(function(p){return '<div class="plan-row"><div><b>'+esc(p.name)+'</b><small>'+esc(p.description||'')+'</small></div><span>'+money(p.monthly_price)+' / month</span><button class="ghost choose-plan" data-id="'+p.id+'">Use plan</button></div>';}).join('')+'</div>';
 if(t==='Audit')return '<div class="panel"><div class="card-title"><h3>Audit log</h3></div><div id="audit-list"><div class="loading">Loading…</div></div></div>';
 return '';
}
async function bindSettingsTab(t){
 if(t==='Users'){var au=document.getElementById('add-user');if(au)au.onclick=async function(){var name=prompt('Name');var email=prompt('Email');var password=prompt('Temporary password (min 8 characters)');var role=prompt('CRM role: owner, admin, manager, agent or viewer','agent');if(name&&email&&password){await api('/users',{method:'POST',body:JSON.stringify({name:name,email:email,password:password,role:role||'agent'})});renderSettings();}};}
 if(t==='Integrations')document.getElementById('add-integration').onclick=function(){openResourceForm('integrations');};
 if(t==='Templates')loadTemplateManage();
 if(t==='Media')loadMediaManage();
 if(t==='Pipelines'){var ap=document.getElementById('add-pipeline');if(ap)ap.onclick=async function(){var n=prompt('Pipeline name');if(n){await api('/pipelines',{method:'POST',body:JSON.stringify({name:n})});renderSettings();}};document.querySelectorAll('.add-stage').forEach(function(b){b.onclick=async function(){var n=prompt('Stage name');if(n){var p=prompt('Probability %','50');await api('/pipelines/'+b.dataset.id+'/stages',{method:'POST',body:JSON.stringify({name:n,probability:Number(p||0),position:99})});renderSettings();}};});}
 if(t==='Tags'){var at=document.getElementById('add-tag');if(at)at.onclick=async function(){var n=prompt('Tag name');if(n){await api('/tags',{method:'POST',body:JSON.stringify({name:n,color:'#2563eb'})});renderSettings();}};document.querySelectorAll('.tag-del').forEach(function(b){b.onclick=async function(){await api('/tags/'+b.dataset.id,{method:'DELETE'});renderSettings();};});}
 if(t==='Billing')document.querySelectorAll('.choose-plan').forEach(function(b){b.onclick=async function(){await api('/subscription',{method:'POST',body:JSON.stringify({plan_id:Number(b.dataset.id),status:'active'})});alert('Plan saved.');};});
 if(t==='Audit'){var r=await api('/audit-logs').catch(function(){return {data:[]};});document.getElementById('audit-list').innerHTML=(r.data||[]).map(function(x){return '<div class="audit-row"><b>'+esc(x.action)+'</b><span>'+esc(x.object_type)+' #'+esc(x.object_id)+'</span><small>'+dateFmt(x.created_at)+'</small></div>';}).join('')||'<div class="empty">No audit entries.</div>';};
}
async function loadTemplateManage(){var r=await api('/whatsapp/templates');var box=document.getElementById('templates-manage');box.innerHTML=(r.data||[]).map(function(x){return '<div class="asset-row"><span>✉</span><div><b>'+esc(x.name)+'</b><small>'+esc(x.channel)+' · '+esc(x.body)+'</small></div><button class="link-btn template-del" data-id="'+x.id+'">Disable</button></div>';}).join('')||'<div class="empty">No templates.</div>';document.getElementById('add-template').onclick=async function(){var name=prompt('Template name');var body=prompt('Template body');if(name&&body){await api('/whatsapp/templates',{method:'POST',body:JSON.stringify({name:name,body:body,channel:'whatsapp'})});loadTemplateManage();}};document.querySelectorAll('.template-del').forEach(function(b){b.onclick=async function(){await api('/whatsapp/templates/'+b.dataset.id,{method:'DELETE'});loadTemplateManage();};});}
async function loadMediaManage(){var r=await api('/whatsapp/assets');var box=document.getElementById('media-manage');box.innerHTML=(r.data||[]).map(function(x){return '<div class="asset-row"><span>📎</span><div><b>'+esc(x.name)+'</b><small>'+esc(x.asset_type)+' · '+esc(x.url)+'</small></div><button class="link-btn media-del" data-id="'+x.id+'">Disable</button></div>';}).join('')||'<div class="empty">No media assets.</div>';document.getElementById('add-media').onclick=async function(){var name=prompt('Asset name');var url=prompt('Public URL');if(name&&url){await api('/whatsapp/assets',{method:'POST',body:JSON.stringify({name:name,url:url,asset_type:'document'})});loadMediaManage();}};document.querySelectorAll('.media-del').forEach(function(b){b.onclick=async function(){await api('/whatsapp/assets/'+b.dataset.id,{method:'DELETE'});loadMediaManage();};});}
async function saveSettings(){var form=document.getElementById('settings-body').querySelector('form');if(!form){alert('Open General settings first.');return;}var data=formData(form,[['business_name','', 'text'],['currency','','text'],['timezone','','text'],['lead_default_status','','text'],['company_website','','url'],['whatsapp_default_template','','text'],['notifications','','checkbox']]);await api('/settings',{method:'POST',body:JSON.stringify(data)});state.notice='Settings saved.';renderSettings();}

function renderModal(){
  var host=document.createElement('div');host.className='modal-back';
  if(state.modal.kind==='lead'){
    var d=state.modal.data||{},html='<div class="modal wide"><button class="close" id="modal-close">×</button><span class="eyebrow">'+(state.modal.mode==='edit'?'EDIT LEAD':'NEW LEAD')+'</span><h2>'+(state.modal.mode==='edit'?'Edit Lead':'Add Lead')+'</h2><form id="modal-form"><div class="form-grid">'+[
      ['first_name','First name','text'],['last_name','Last name','text'],['company','Company','text'],['email','Email','email'],['phone','Phone','text'],['job_title','Job title','text'],['website','Website','url'],['location','Location','text'],['industry','Industry','text'],['source','Source','text'],['status','Status','select:new|New,contacted|Contacted,qualified|Qualified,proposal|Proposal,negotiation|Negotiation,converted|Converted,lost|Lost'],['score','Score','number'],['value','Expected value','number'],['expected_close_date','Expected close date','date'],['notes','Notes','textarea']
    ].map(function(f){return fieldControl(f,d[f[0]]);}).join('')+'</div><div class="modal-actions"><button class="ghost" type="button" id="modal-cancel">Cancel</button><button class="primary" type="submit">Save lead</button></div></form></div>';
    host.innerHTML=html;
  }else{
    var type=state.modal.type,cfg=resourceMap[type],d=state.modal.data||{};
    host.innerHTML='<div class="modal wide"><button class="close" id="modal-close">×</button><span class="eyebrow">'+esc(cfg.singular.toUpperCase())+'</span><h2>'+(state.modal.mode==='edit'?'Edit ':'Add ')+esc(cfg.singular)+'</h2><form id="modal-form"><div class="form-grid">'+cfg.fields.map(function(f){return fieldControl(f,d[f[0]]);}).join('')+'</div><div class="modal-actions"><button class="ghost" type="button" id="modal-cancel">Cancel</button><button class="primary" type="submit">Save '+esc(cfg.singular)+'</button></div></form></div>';
  }
  document.body.appendChild(host);
  document.getElementById('modal-close').onclick=closeModal;document.getElementById('modal-cancel').onclick=closeModal;
  document.getElementById('modal-form').onsubmit=async function(e){e.preventDefault();var form=this;try{
    if(state.modal.kind==='lead'){var fields=[['first_name','','text'],['last_name','','text'],['company','','text'],['email','','email'],['phone','','text'],['job_title','','text'],['website','','url'],['location','','text'],['industry','','text'],['source','','text'],['status','','text'],['score','','number'],['value','','number'],['expected_close_date','','date'],['notes','','textarea']];var data=formData(form,fields);if(state.modal.mode==='edit')await api('/leads/'+state.modal.id,{method:'PATCH',body:JSON.stringify(data)});else await api('/leads',{method:'POST',body:JSON.stringify(data)});closeModal();state.notice='Lead saved.';renderLeads();
    }else{var type=state.modal.type,data=formData(form,resourceMap[type].fields);if(state.modal.mode==='edit')await api('/'+type+'/'+state.modal.id,{method:'PATCH',body:JSON.stringify(data)});else await api('/'+type,{method:'POST',body:JSON.stringify(data)});closeModal();state.notice=resourceMap[type].singular+' saved.'; if(state.view===type)renderResource(type);else render();}
  }catch(err){alert(err.message);}};
}
function closeModal(){document.querySelectorAll('.modal-back').forEach(function(x){x.remove();});state.modal=null;}

function exportCSV(rows,name){
  if(!rows.length){alert('Nothing to export.');return;}
  var keys=Object.keys(rows[0]);var csv=[keys.join(',')].concat(rows.map(function(r){return keys.map(function(k){var v=String(r[k]==null?'':r[k]).replace(/"/g,'""');return '"'+v+'"';}).join(',');})).join('\n');
  var a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));a.download=name;a.click();URL.revokeObjectURL(a.href);
}
async function importCSV(file){
  if(!file)return;var text=await file.text();var lines=text.split(/\r?\n/).filter(Boolean);if(lines.length<2){alert('CSV has no rows.');return;}
  var headers=lines.shift().split(',').map(function(x){return x.trim().replace(/^"|"$/g,'');});var created=0;
  for(var i=0;i<lines.length;i++){var vals=lines[i].match(/(".*?"|[^",]+)(?=,|$)/g)||[];var row={};headers.forEach(function(h,j){var v=(vals[j]||'').replace(/^"|"$/g,'').replace(/""/g,'"');row[h]=v;});if(row.first_name){await api('/leads',{method:'POST',body:JSON.stringify(row)});created++;}}
  alert('Imported '+created+' leads.');renderLeads();
}

function openNotifications(){api('/notifications').then(function(r){var rows=r.data||[];alert(rows.length?'Notifications:\n\n'+rows.map(function(n){return (n.read_at?'✓ ':'• ')+n.title+'\n'+(n.body||'')}).join('\n\n'):'No notifications.');}).catch(function(e){alert(e.message);});}

function openLeadFormFallback(){openLeadForm();}

render();
})();