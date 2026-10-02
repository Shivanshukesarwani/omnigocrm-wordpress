import * as React from '@wordpress/element';

const cfg = window.OmniGoCRMConfig || {};
const { useState, useEffect, useMemo } = React;
const API = String(cfg.restUrl || '').replace(/\/$/, '');
const nonce = cfg.nonce || '';

async function api(path, options = {}) {
  const headers = { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json', ...(options.headers || {}) };
  const res = await fetch(API + path, { credentials: 'same-origin', ...options, headers });
  const text = await res.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch { data = { message: text }; }
  if (!res.ok) throw new Error(data.message || data.code || 'Request failed');
  return data;
}

const nav = [
  ['dashboard','⌂','Dashboard'],['leads','◉','Leads'],['contacts','◌','Contacts'],['companies','▣','Companies'],
  ['opportunities','◆','Opportunities'],['quotes','▤','Quotes'],['orders','▥','Orders'],['invoices','▦','Invoices'],
  ['products','◇','Products'],['calls','☎','Calls'],['tasks','✓','Tasks'],['conversations','◍','Inbox'],['campaigns','✦','Campaigns'],
  ['automations','⚙','Automation'],['calendar','◷','Calendar'],['notifications','●','Notifications'],['billing','₹','Billing'],['integrations','⌘','Integrations'],['reports','▥','Reports'],['team','♙','Team'],['audit','≡','Audit Log'],['settings','☷','Settings']
];

const resources = {
  leads:{title:'Leads',endpoint:'/leads',fields:['first_name','last_name','company','email','phone','status','source','score','value']},
  contacts:{title:'Contacts',endpoint:'/contacts',fields:['first_name','last_name','company','email','phone','job_title']},
  companies:{title:'Companies',endpoint:'/companies',fields:['name','email','phone','website','industry','address']},
  opportunities:{title:'Opportunities',endpoint:'/opportunities',fields:['name','company','amount','currency','stage','probability','close_date']},
  quotes:{title:'Quotes',endpoint:'/quotes',fields:['quote_number','company_id','contact_id','status','currency','total','valid_until']},
  orders:{title:'Orders',endpoint:'/orders',fields:['order_number','quote_id','company_id','status','currency','total']},
  invoices:{title:'Invoices',endpoint:'/invoices',fields:['invoice_number','order_id','company_id','status','currency','total','due_date']},
  products:{title:'Products',endpoint:'/products',fields:['name','sku','price','currency','tax_rate','active']},
  calls:{title:'Calls',endpoint:'/calls',fields:['phone','direction','status','duration_seconds','started_at','ended_at','agent_id']},
  tasks:{title:'Tasks',endpoint:'/tasks',fields:['title','status','priority','due_date','assigned_to']},
  campaigns:{title:'Campaigns',endpoint:'/campaigns',fields:['name','channel','status','audience_type','scheduled_at']},
  automations:{title:'Automation',endpoint:'/automations',fields:['name','trigger_type','active','description']},
};

const labels = s => String(s || '').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
const money = n => Number(n || 0).toLocaleString(undefined,{maximumFractionDigits:2});
const initials = (a,b) => ((a||'').charAt(0)+(b||'').charAt(0)).toUpperCase() || 'OG';

function Button({children,onClick,kind='ghost',type='button',disabled=false}){return <button type={type} className={kind} onClick={onClick} disabled={disabled}>{children}</button>}

function Shell({page,setPage,children}) {
  const [search,setSearch]=useState('');
  return <div className="og-shell">
    <aside className="og-side">
      <div className="og-brand"><span>OG</span><div><b>OmniGoCRM</b><small>WordPress SaaS CRM</small></div></div>
      <div className="og-workspace"><div className="workspace-avatar">S</div><div><b>Workspace</b><small>Default workspace</small></div><span>⌄</span></div>
      <nav>{nav.map(([id,icon,name])=><button key={id} className={page===id?'active':''} onClick={()=>setPage(id)}><i>{icon}</i>{name}</button>)}</nav>
      <div className="og-side-bottom">React interface<br/><small>v{cfg.version || '0.5.5'}</small></div>
    </aside>
    <main className="og-main">
      <header className="og-top"><div><div className="eyebrow">CRM WORKSPACE</div><h1>{nav.find(x=>x[0]===page)?.[2] || 'OmniGoCRM'}</h1></div>
        <div className="top-tools"><div className="global-search">⌕ <input value={search} onChange={e=>setSearch(e.target.value)} placeholder="Search CRM"/></div><button className="icon-btn" title="Notifications" onClick={()=>setPage('notifications')}>🔔</button><span className="status-chip"><i/>Live</span></div>
      </header>
      {children}
    </main>
  </div>
}

function PageHead({title,desc,onAdd,addLabel='Add'}) {
  return <div className="page-head"><div><h2>{title}</h2><p>{desc}</p></div><div className="page-actions">{onAdd&&<Button kind="primary" onClick={onAdd}>＋ {addLabel}</Button>}</div></div>
}

function Dashboard({go}) {
  const [data,setData]=useState(null);
  useEffect(()=>{api('/dashboard').then(setData).catch(()=>setData({}))},[]);
  const d=data||{};
  const metrics=[
    ['Total Leads',d.leads ?? 0,'All active prospects','blue'],['Contacts',d.contacts ?? 0,'People in CRM','purple'],
    ['Opportunities',d.opportunities ?? 0,'Open opportunities','green'],['Pipeline Value',money(d.pipeline_value),'Current pipeline','orange'],['Tasks',d.tasks ?? 0,'Open tasks','red']
  ];
  return <><PageHead title="Sales overview" desc="Your CRM activity, pipeline and revenue snapshot."/>
    <div className="stat-grid">{metrics.map(m=><div className="metric" key={m[0]}><small>{m[0]}</small><strong>{m[1]}</strong><span>{m[2]}</span></div>)}</div>
    <div className="dashboard-grid">
      <section className="panel"><div className="panel-head"><h3>CRM operating system</h3><span>Connected to WordPress REST</span></div>
        <div className="cap-grid">{['Lead management','Contacts & companies','Sales pipeline','Quotes & orders','Invoices & payments','Omnichannel inbox','Campaigns','Automation','Reports'].map(x=><div key={x}>✓ <span>{x}</span></div>)}</div>
      </section>
      <section className="panel"><h3>Quick actions</h3><div className="quick-grid">
        {[['leads','Create lead'],['contacts','Add contact'],['opportunities','New opportunity'],['tasks','Create task']].map(x=><button key={x[0]} onClick={()=>go(x[0])}>{x[1]} <b>→</b></button>)}
      </div></section>
      <section className="panel wide"><h3>Getting started</h3><div className="snapshot"><div><small>Backend</small><b>WordPress REST API</b></div><div><small>Frontend</small><b>React + Vite</b></div><div><small>Authentication</small><b>WP session + REST nonce</b></div><div><small>Deployment</small><b>Static assets — no Node server</b></div></div></section>
    </div>
  </>
}


function whatsappTargetLabel(target) {
  return ({web:'WhatsApp Web',desktop:'WhatsApp Desktop',mobile_personal:'Mobile · WhatsApp Personal',mobile_business:'Mobile · WhatsApp Business'})[target] || 'WhatsApp Web';
}

function launchWhatsApp(urls, target, phone, message) {
  const ua=navigator.userAgent||'';
  const encoded=encodeURIComponent(message||'');
  if(target==='web') { window.open(urls.web,'_blank','noopener'); return; }
  if(target==='desktop') { window.location.href=urls.desktop; return; }
  if(/Android/i.test(ua)) { window.location.href=target==='mobile_business'?urls.mobile_business:urls.mobile_personal; return; }
  if(/iPhone|iPad|iPod/i.test(ua)) {
    const scheme=target==='mobile_business'?'whatsapp-business':'whatsapp';
    window.location.href=scheme+'://send?phone='+String(phone||'')+'&text='+encoded;
    return;
  }
  window.open(urls.web,'_blank','noopener');
}

function WhatsAppComposer({record,entityType='lead',compact=false}) {
  const [templates,setTemplates]=useState([]),[media,setMedia]=useState([]),[settings,setSettings]=useState({whatsapp_default_target:'web',whatsapp_mobile_target:'personal'}),[body,setBody]=useState(''),[templateId,setTemplateId]=useState(''),[mediaId,setMediaId]=useState(''),[target,setTarget]=useState('web'),[busy,setBusy]=useState(false),[notice,setNotice]=useState('');
  useEffect(()=>{
    Promise.all([
      api('/whatsapp/templates'),
      api('/whatsapp/assets'),
      api('/settings')
    ]).then(([t,m,st])=>{
      const ts=t.data||t, ms=m.data||m, ss=st.data||st;
      setTemplates(ts); setMedia(ms); setSettings(ss);
      setTarget(ss.whatsapp_default_target||'web');
    }).catch(e=>setNotice(e.message));
  },[]);
  const chooseTemplate=id=>{
    setTemplateId(id);
    const t=templates.find(x=>String(x.id)===String(id));
    if(t)setBody(t.body||'');
    if(t?.media_asset_id)setMediaId(String(t.media_asset_id));
  };
  const send=async()=>{
    if(!record?.id)return;
    setBusy(true);setNotice('');
    try{
      const r=await api('/whatsapp/prepare',{method:'POST',body:JSON.stringify({
        entity_type:entityType,entity_id:record.id,body,template_id:Number(templateId||0),media_asset_id:Number(mediaId||0)
      })});
      setBody(r.message||body);
      launchWhatsApp(r.urls,target,r.phone,r.message||body);
      setNotice('WhatsApp opened with the message prepared. Press Send in WhatsApp.');
    }catch(e){setNotice(e.message)}finally{setBusy(false)}
  };
  const hasPhone=!!record?.phone;
  return <section className={compact?'panel whatsapp-composer compact':'panel whatsapp-composer'}>
    <div className="panel-head"><div><h3>WhatsApp</h3><span>{hasPhone?'Prepare a message and choose where to open it.':'Add a phone number to enable WhatsApp.'}</span></div></div>
    {hasPhone&&<div className="form-grid">
      <label>Open with<select value={target} onChange={e=>setTarget(e.target.value)}>
        <option value="web">WhatsApp Web</option><option value="desktop">WhatsApp Desktop</option>
        <option value="mobile_personal">Mobile · WhatsApp Personal</option><option value="mobile_business">Mobile · WhatsApp Business</option>
      </select></label>
      <label>Template<select value={templateId} onChange={e=>chooseTemplate(e.target.value)}><option value="">Custom message</option>{templates.map(t=><option value={t.id} key={t.id}>{t.name}</option>)}</select></label>
      <label>Attach link<select value={mediaId} onChange={e=>setMediaId(e.target.value)}><option value="">No file</option>{media.map(x=><option value={x.id} key={x.id}>{x.name}</option>)}</select></label>
    </div>}
    {hasPhone&&<textarea className="wa-compose" value={body} onChange={e=>setBody(e.target.value)} placeholder="Write your WhatsApp message…"/>}
    {notice&&<div className="og-notice">{notice}</div>}
    {hasPhone&&<div className="modal-actions"><Button kind="primary" onClick={send} disabled={busy}>{busy?'Preparing…':'Open WhatsApp'}</Button><small className="muted">File selection adds a public WordPress media link to the message.</small></div>}
  </section>
}

function LeadDetail({leadId, onBack}) {
  const [lead,setLead]=useState(null), [tab,setTab]=useState('overview'), [notes,setNotes]=useState([]), [tasks,setTasks]=useState([]), [templates,setTemplates]=useState([]), [message,setMessage]=useState(''), [busy,setBusy]=useState(false), [notice,setNotice]=useState('');
  const load=async()=>{try{const [l,n,t,tm]=await Promise.all([api('/leads/'+leadId),api('/notes'),api('/tasks'),api('/whatsapp/templates')]);setLead(l.data||l);setNotes((n.data||n).filter(x=>String(x.lead_id||'')===String(leadId)||String(x.related_id||'')===String(leadId)));setTasks((t.data||t).filter(x=>String(x.related_id||'')===String(leadId)));setTemplates(tm.data||tm)}catch(e){setNotice(e.message)}};
  useEffect(()=>{load()},[leadId]);
  if(!lead)return <><PageHead title="Lead" desc="Loading lead record…"/><div className="panel empty">{notice||'Loading…'}</div></>;
  const sendWhatsApp=async()=>{if(!message.trim())return;setBusy(true);try{const r=await api('/leads/'+leadId+'/whatsapp/prepare',{method:'POST',body:JSON.stringify({body:message,template_id:0})});setNotice('WhatsApp message prepared. Open WhatsApp to send it.');if(r.urls?.web)window.open(r.urls.web,'_blank')}catch(e){setNotice(e.message)}finally{setBusy(false)}};
  const convert=async()=>{setBusy(true);try{const r=await api('/leads/'+leadId+'/convert',{method:'POST',body:'{}'});setNotice('Lead converted successfully. Contact #'+r.contact_id+(r.opportunity_id?' · Opportunity #'+r.opportunity_id:''));load()}catch(e){setNotice(e.message)}finally{setBusy(false)}};
  const addNote=async()=>{const body=window.prompt('Note for this lead:');if(!body)return;await api('/notes',{method:'POST',body:JSON.stringify({body,lead_id:leadId,related_type:'lead',related_id:leadId,created_by:cfg.userId||0})});load()};
  const addTask=async()=>{const title=window.prompt('Task title:');if(!title)return;await api('/tasks',{method:'POST',body:JSON.stringify({title,status:'open',priority:'normal',related_type:'lead',related_id:leadId,assigned_to:cfg.userId||0})});load()};
  return <><PageHead title={[lead.first_name,lead.last_name].filter(Boolean).join(' ')||'Lead'} desc={lead.company||lead.email||'Lead record'} />
    <div className="lead-toolbar"><Button onClick={onBack}>← Back to leads</Button><div><Button onClick={async()=>{if(!lead.phone)return;try{await api('/calls',{method:'POST',body:JSON.stringify({lead_id:leadId,phone:lead.phone,direction:'outbound',status:'initiated',agent_id:cfg.userId||0,started_at:new Date().toISOString().slice(0,19).replace('T',' ')})});window.location.href='tel:'+String(lead.phone).replace(/[^\\d+]/g,'');}catch(e){setNotice(e.message)}}}>☎ Call</Button><Button onClick={addNote}>＋ Note</Button><Button onClick={addTask}>＋ Task</Button><Button kind="primary" onClick={convert} disabled={busy}>Convert lead</Button></div></div>
    {notice&&<div className="og-notice">{notice}</div>}
    <div className="lead-summary panel"><div className="big-avatar">{initials(lead.first_name,lead.last_name)}</div><div><h2>{lead.first_name} {lead.last_name}</h2><p>{lead.job_title||'Prospect'} · {lead.company||'No company'}</p></div><div className="lead-facts"><span><small>Status</small><b>{lead.status||'new'}</b></span><span><small>Score</small><b>{lead.score||0}</b></span><span><small>Value</small><b>{money(lead.value)}</b></span></div></div>
    <div className="detail-tabs">{['overview','activity','whatsapp','notes','tasks','related'].map(x=><button className={tab===x?'active':''} onClick={()=>setTab(x)} key={x}>{labels(x)}</button>)}</div>
    {tab==='overview'&&<section className="panel detail-grid"><div><h3>Contact information</h3>{[['Email',lead.email],['Phone',lead.phone],['Website',lead.website],['Location',lead.location],['Industry',lead.industry],['Source',lead.source]].map(([k,v])=><div className="detail-line" key={k}><small>{k}</small><span>{v||'—'}</span></div>)}</div><div><h3>Lead notes</h3><p>{lead.notes||'No lead notes yet.'}</p></div></section>}
    {tab==='activity'&&<section className="panel"><h3>Recent activity</h3><div className="timeline"><div>Lead created <small>{lead.created_at||''}</small></div><div>Status: <b>{lead.status||'new'}</b><small>{lead.updated_at||''}</small></div>{notes.slice(0,5).map(n=><div key={n.id}>Note added <small>{n.created_at||''}</small><p>{n.body}</p></div>)}</div></section>}
    {tab==='whatsapp'&&<WhatsAppComposer record={lead} entityType="lead"/>}
    {tab==='notes'&&<section className="panel"><div className="panel-head"><h3>Notes</h3><Button onClick={addNote}>＋ Add note</Button></div>{notes.map(n=><div className="note-row" key={n.id}><b>{n.created_at||'Note'}</b><span>{n.body}</span></div>)}{!notes.length&&<div className="empty">No notes for this lead.</div>}</section>}
    {tab==='tasks'&&<section className="panel"><div className="panel-head"><h3>Tasks</h3><Button onClick={addTask}>＋ Add task</Button></div>{tasks.map(t=><div className="note-row" key={t.id}><b>{t.title}</b><span>{t.status} · {t.priority} · {t.due_date||'No due date'}</span></div>)}{!tasks.length&&<div className="empty">No tasks linked to this lead.</div>}</section>}
    {tab==='related'&&<section className="panel"><h3>Related records</h3><div className="snapshot"><div><small>Company</small><b>{lead.company||'—'}</b></div><div><small>Contact</small><b>Created on conversion</b></div><div><small>Opportunity</small><b>{lead.value?'Potential opportunity':'Not yet created'}</b></div></div></section>}
  </>;
}

function ResourcePage({type,onSelect}) {
  const meta=resources[type], [rows,setRows]=useState([]), [loading,setLoading]=useState(true), [error,setError]=useState(''), [q,setQ]=useState(''), [selected,setSelected]=useState(null), [modal,setModal]=useState(false), [editing,setEditing]=useState(false), [saving,setSaving]=useState(false);
  const load=()=>{setLoading(true);setError('');api(meta.endpoint).then(r=>setRows(Array.isArray(r)?r:(r.data||r.items||[]))).catch(e=>setError(e.message)).finally(()=>setLoading(false))};
  useEffect(load,[]);
  const filtered=useMemo(()=>rows.filter(r=>JSON.stringify(r).toLowerCase().includes(q.toLowerCase())),[rows,q]);
  const create=async form=>{setSaving(true);setError('');try{await api(meta.endpoint,{method:'POST',body:JSON.stringify(form)});setModal(false);load()}catch(e){setError(e.message)}finally{setSaving(false)}};
  const update=async form=>{if(!selected?.id)return;setSaving(true);setError('');try{const r=await api(meta.endpoint+'/'+selected.id,{method:'PATCH',body:JSON.stringify(form)});setSelected(r.data||r);setEditing(false);load()}catch(e){setError(e.message)}finally{setSaving(false)}};
  const remove=async()=>{if(!selected?.id||!window.confirm('Delete this record? This cannot be undone.'))return;setSaving(true);setError('');try{await api(meta.endpoint+'/'+selected.id,{method:'DELETE'});setSelected(null);setEditing(false);load()}catch(e){setError(e.message)}finally{setSaving(false)}};
  return <><PageHead title={meta.title} desc={'Manage '+meta.title.toLowerCase()+' from one workspace.'} onAdd={()=>setModal(true)} addLabel={'Add '+meta.title.replace(/s$/,'')}/>
    <div className="toolbar-card"><div className="table-search">⌕<input value={q} onChange={e=>setQ(e.target.value)} placeholder={'Search '+meta.title.toLowerCase()}/></div><span className="result-count">{filtered.length} records</span></div>
    {error&&<div className="og-error">{error}</div>}
    <section className="panel table-panel">{loading?<div className="empty">Loading…</div>:!filtered.length?<div className="empty">No records yet. Create the first one.</div>:
      <table><thead><tr>{meta.fields.slice(0,7).map(f=><th key={f}>{labels(f)}</th>)}</tr></thead><tbody>{filtered.map((r,i)=><tr key={r.id||i} onClick={()=>{setSelected(r);setEditing(false);if(onSelect)onSelect(r.id) }}>{meta.fields.slice(0,7).map(f=><td key={f}>{f==='price'||f==='amount'||f==='total'||f==='value'?money(r[f]):f==='active'?<span className="badge">{r[f]?'Active':'Inactive'}</span>:String(r[f]??'—')}</td>)}</tr>)}</tbody></table>}
    </section>
    {selected&&<div className="side-detail"><div className="detail-top"><button className="close" onClick={()=>{setSelected(null);setEditing(false)}} disabled={saving}>×</button><div className="detail-person"><div className="big-avatar">{initials(selected.first_name,selected.last_name||selected.name)}</div><div><h2>{selected.name||[selected.first_name,selected.last_name].filter(Boolean).join(' ')}</h2><p>{selected.email||selected.company||selected.status||'CRM record'}</p></div></div><div className="detail-actions">{type==='automations'&&<Button kind="primary" onClick={async()=>{try{setSaving(true);await api('/automations/'+selected.id+'/run',{method:'POST',body:'{}'});window.alert('Automation queued successfully.')}catch(e){setError(e.message)}finally{setSaving(false)}}} disabled={saving}>Run</Button>}<Button onClick={()=>setEditing(true)} disabled={saving}>Edit</Button><Button kind="danger" onClick={remove} disabled={saving}>Delete</Button></div></div>
      <div className="detail-body">{['leads','contacts','companies'].includes(type)&&selected.phone&&<WhatsAppComposer record={selected} entityType={type==='leads'?'lead':type==='contacts'?'contact':'company'} compact/>}{editing?<RecordModal embedded title={'Edit '+meta.title.replace(/s$/,'')} fields={meta.fields} initial={selected} onClose={()=>setEditing(false)} onSave={update} saving={saving}/>:<div className="detail-card"><h3>Record details</h3>{Object.entries(selected).filter(([k])=>!['id','created_at','updated_at'].includes(k)).slice(0,14).map(([k,v])=><div className="detail-line" key={k}><small>{labels(k)}</small><span>{typeof v==='object'?JSON.stringify(v):String(v??'—')}</span></div>)}</div>}</div></div>}
    {modal&&<RecordModal title={'Create '+meta.title.replace(/s$/,'')} fields={meta.fields} onClose={()=>setModal(false)} onSave={create} saving={saving}/>}
  </>
}

function RecordModal({title,fields,onClose,onSave,saving=false,initial={},embedded=false}) {
  const [form,setForm]=useState(()=>Object.fromEntries(fields.map(f=>[f,initial[f]??''])));
  const body=<><h2>{title}</h2><div className="form-grid">
    {fields.map(f=><label key={f}>{labels(f)}<input value={form[f]??''} onChange={e=>setForm({...form,[f]:e.target.value})} disabled={saving} /></label>)}
  </div><div className="modal-actions"><Button onClick={onClose} disabled={saving}>Cancel</Button><Button kind="primary" onClick={()=>onSave(form)} disabled={saving}>{saving?'Saving…':'Save'}</Button></div></>;
  return embedded?<div className="embedded-form">{body}</div>:<div className="modal-back"><div className="modal wide"><button className="close" onClick={onClose} disabled={saving}>×</button>{body}</div></div>;
}

function Inbox(){
  const [convs,setConvs]=useState([]),[active,setActive]=useState(null),[messages,setMessages]=useState([]),[text,setText]=useState('');
  const load=()=>api('/conversations').then(r=>setConvs(Array.isArray(r)?r:(r.data||[]))).catch(()=>setConvs([]));
  useEffect(load,[]);
  useEffect(()=>{if(active)api('/conversations/'+active+'/messages').then(r=>setMessages(Array.isArray(r)?r:(r.data||[]))).catch(()=>setMessages([]))},[active]);
  const send=async()=>{if(!active||!text.trim())return;try{await api('/conversations/'+active+'/messages',{method:'POST',body:JSON.stringify({body:text,direction:'outbound'})});setText('');const r=await api('/conversations/'+active+'/messages');setMessages(r.data||r)}catch(e){window.alert(e.message)}};
  return <><PageHead title="Omnichannel inbox" desc="Conversations, messages and follow-ups in one place."/><div className="inbox-layout"><section className="panel conversation-list">{convs.map(c=><button className={'conversation-row '+(active===c.id?'active':'')} key={c.id} onClick={()=>setActive(c.id)}><div className="channel-icon">{String(c.channel||'W')[0]}</div><div><b>{c.subject||c.phone||('Conversation #'+c.id)}</b><small>{c.last_message||c.status||'No messages'}</small></div><em>{c.updated_at||''}</em></button>)}{!convs.length&&<div className="empty">No conversations yet.</div>}</section>
  <section className="panel thread-panel">{active?<><div className="thread-head"><h3>Conversation #{active}</h3><span>Messages</span></div><div className="messages">{messages.map((m,i)=><div className={'msg '+(m.direction==='outbound'?'outbound':'')} key={m.id||i}>{m.body||m.message}<small>{m.created_at||''}</small></div>)}</div><div className="composer-bottom"><textarea value={text} onChange={e=>setText(e.target.value)} placeholder="Write a message…"/><Button kind="primary" onClick={send}>Send</Button></div></>:<div className="empty">Select a conversation.</div>}</section></div></>
}

function Calendar(){
  const [rows,setRows]=useState([]);
  useEffect(()=>api('/calendar').then(r=>setRows(r.data||r)).catch(()=>setRows([])),[]);
  return <><PageHead title="Calendar" desc="Tasks, calls and scheduled CRM activity."/><section className="panel"><div className="calendar-list">{rows.map((x,i)=><div className="calendar-item" key={x.id||i}><div className="calendar-date">{String(x.date||x.due_date||x.start||'—').slice(0,10)}</div><div><b>{x.title||x.subject||x.type||'Activity'}</b><small>{x.status||x.description||''}</small></div></div>)}{!rows.length&&<div className="empty">No scheduled activity found.</div>}</div></section></>
}

function Notifications(){
  const [rows,setRows]=useState([]);
  const load=()=>api('/notifications').then(r=>setRows(r.data||r)).catch(()=>setRows([]));
  useEffect(load,[]);
  const read=async id=>{try{await api('/notifications/'+id+'/read',{method:'POST'});load()}catch(e){window.alert(e.message)}};
  return <><PageHead title="Notifications" desc="CRM alerts and workflow activity."/><section className="panel">{rows.map(x=><div className={'notification '+(x.read_at?'read':'')} key={x.id}><div><b>{x.title}</b><p>{x.body||''}</p><small>{x.created_at||''}</small></div>{!x.read_at&&<Button onClick={()=>read(x.id)}>Mark read</Button>}</div>)}{!rows.length&&<div className="empty">No notifications.</div>}</section></>
}

function Billing(){
  const [plans,setPlans]=useState([]),[sub,setSub]=useState(null);
  useEffect(()=>{Promise.all([api('/plans'),api('/subscription')]).then(([p,s])=>{setPlans(p.data||p);setSub(s.data||s)}).catch(()=>{})},[]);
  const choose=async id=>{try{const r=await api('/subscription',{method:'POST',body:JSON.stringify({plan_id:id})});setSub(r.data||r)}catch(e){window.alert(e.message)}};
  return <><PageHead title="Billing" desc="Plans and workspace subscription."/><div className="billing-grid">{plans.map(p=><div className="panel plan-card" key={p.id}><h3>{p.name}</h3><strong>{p.currency||'INR'} {money(p.monthly_price)}</strong><p>{p.description||'CRM workspace plan'}</p><Button kind={String(sub?.plan_id)===String(p.id)?'primary':'ghost'} onClick={()=>choose(p.id)}>{String(sub?.plan_id)===String(p.id)?'Current plan':'Select plan'}</Button></div>)}{!plans.length&&<div className="panel empty">No plans configured.</div>}</div></>
}

function Integrations(){
  const [rows,setRows]=useState([]),[providers,setProviders]=useState([]),[modal,setModal]=useState(null),[form,setForm]=useState({}),[saving,setSaving]=useState(false),[notice,setNotice]=useState('');
  const load=()=>Promise.all([api('/integrations'),api('/integrations/providers')]).then(([r,p])=>{setRows(r.data||r);setProviders(p.data||p)}).catch(e=>setNotice(e.message));
  useEffect(load,[]);
  const save=async()=>{
    setSaving(true);setNotice('');
    try{
      const provider=modal;
      const created=await api('/integrations',{method:'POST',body:JSON.stringify({name:form.name||provider.name,type:provider.code,status:'configured',config:{client_id:form.client_id||'',scope:form.scope||provider.scopes?.join(' ')||'',auth_url:provider.auth_url||'',token_url:provider.token_url||'',data_center:form.data_center||''}})});
      const id=(created.data||created).id;
      if(form.client_secret||form.api_key)await api('/integrations/'+id+'/credentials',{method:'POST',body:JSON.stringify({client_id:form.client_id||'',client_secret:form.client_secret||'',api_key:form.api_key||'',data_center:form.data_center||''})});
      setModal(null);setForm({});setNotice('Integration saved.');load();
    }catch(e){setNotice(e.message)}finally{setSaving(false)}
  };
  const connect=async row=>{
    try{const r=await api('/integrations/'+row.id+'/oauth/start',{method:'POST',body:'{}'});window.location.href=r.url}catch(e){setNotice(e.message)}
  };
  return <><PageHead title="Integrations" desc="Connect Google, Zoho, Microsoft, Slack, Zoom and custom webhooks." onAdd={()=>setModal(providers[0]||null)} addLabel="Add integration"/>
    {notice&&<div className="og-notice">{notice}</div>}
    <div className="integration-grid">{providers.map(p=><div className="panel integration-card" key={p.code}><div className="integration-icon">{p.name.charAt(0)}</div><h3>{p.name}</h3><small>{p.category}</small><p>{p.description}</p><Button onClick={()=>{setModal(p);setForm({name:p.name,scope:p.scopes?.join(' ')||'',data_center:''})}}>Configure</Button></div>)}</div>
    <section className="panel"><div className="panel-head"><h3>Configured integrations</h3><span>{rows.length} connected/configured</span></div>{rows.map(x=><div className="integration-row" key={x.id}><div><b>{x.name}</b><small>{x.type} · {x.status}</small></div><div>{String(x.status)==='connected'?<span className="badge">Connected</span>:<Button kind="primary" onClick={()=>connect(x)}>Connect</Button>}</div></div>)}{!rows.length&&<div className="empty">No integrations configured.</div>}</section>
    {modal&&<div className="modal-back"><div className="modal wide"><button className="close" onClick={()=>setModal(null)}>×</button><h2>Configure {modal.name}</h2><p>{modal.description}</p>
      {modal.auth_type==='oauth2'&&<><label className="stack">OAuth Client ID<input value={form.client_id||''} onChange={e=>setForm({...form,client_id:e.target.value})} placeholder="From provider developer console"/></label><label className="stack">OAuth Client Secret<input type="password" value={form.client_secret||''} onChange={e=>setForm({...form,client_secret:e.target.value})}/></label><label className="stack">Scopes<input value={form.scope||''} onChange={e=>setForm({...form,scope:e.target.value})}/></label>{modal.code==='zoho_mail'&&<label className="stack">Zoho data center<input value={form.data_center||''} onChange={e=>setForm({...form,data_center:e.target.value})} placeholder="accounts.zoho.in"/></label>}<div className="og-notice">OAuth callback: {window.location.origin}/wp-json/omnigocrm/v1/integrations/oauth/callback</div></>}
      {modal.auth_type==='webhook'&&<label className="stack">Webhook URL<input value={form.webhook_url||''} onChange={e=>setForm({...form,webhook_url:e.target.value})}/></label>}
      <div className="modal-actions"><Button onClick={()=>setModal(null)}>Cancel</Button><Button kind="primary" onClick={save} disabled={saving}>{saving?'Saving…':'Save integration'}</Button></div>
    </div></div>}
  </>
}

function Audit(){
  const [rows,setRows]=useState([]);
  useEffect(()=>api('/audit-logs').then(r=>setRows(r.data||r)).catch(()=>setRows([])),[]);
  return <><PageHead title="Audit Log" desc="Track changes and actions performed in the CRM."/><section className="panel table-panel"><table><thead><tr><th>Action</th><th>Object</th><th>ID</th><th>User</th><th>Date</th></tr></thead><tbody>{rows.map(x=><tr key={x.id}><td>{x.action}</td><td>{x.object_type}</td><td>{x.object_id}</td><td>{x.user_id}</td><td>{x.created_at}</td></tr>)}</tbody></table>{!rows.length&&<div className="empty">No audit events.</div>}</section></>
}



function Team(){
  const [rows,setRows]=useState([]),[loading,setLoading]=useState(true),[error,setError]=useState(''),[modal,setModal]=useState(false),[saving,setSaving]=useState(false);
  const load=()=>{setLoading(true);setError('');api('/users').then(r=>setRows(r.data||r)).catch(e=>setError(e.message)).finally(()=>setLoading(false))};
  useEffect(load,[]);
  const create=async e=>{e.preventDefault();setSaving(true);setError('');const f=new FormData(e.currentTarget);try{await api('/users',{method:'POST',body:JSON.stringify({name:f.get('name'),email:f.get('email'),password:f.get('password'),role:f.get('role')})});setModal(false);load()}catch(err){setError(err.message)}finally{setSaving(false)}};
  return <><PageHead title="Team" desc="Manage the WordPress users available to your CRM workspace." onAdd={()=>setModal(true)} addLabel="Add user"/>
    {error&&<div className="og-error">{error}</div>}
    <section className="panel table-panel">{loading?<div className="empty">Loading…</div>:!rows.length?<div className="empty">No users found.</div>:
      <table><thead><tr><th>Name</th><th>Email</th><th>WordPress roles</th></tr></thead><tbody>{rows.map(u=><tr key={u.id}><td><b>{u.name}</b></td><td>{u.email}</td><td>{(u.roles||[]).join(', ')||'—'}</td></tr>)}</tbody></table>}
    </section>
    {modal&&<div className="modal-back"><form className="modal" onSubmit={create}><button className="close" type="button" onClick={()=>setModal(false)} disabled={saving}>×</button><h2>Add CRM user</h2>
      <label className="stack">Name<input name="name" required disabled={saving}/></label>
      <label className="stack">Email<input name="email" type="email" required disabled={saving}/></label>
      <label className="stack">Password<input name="password" type="password" placeholder="Leave blank to generate" disabled={saving}/></label>
      <label className="stack">CRM role<select name="role" defaultValue="agent" disabled={saving}><option value="viewer">Viewer</option><option value="agent">Agent</option><option value="manager">Manager</option><option value="admin">Admin</option><option value="owner">Owner</option></select></label>
      <div className="modal-actions"><Button type="button" onClick={()=>setModal(false)} disabled={saving}>Cancel</Button><Button kind="primary" type="submit" disabled={saving}>{saving?'Saving…':'Create user'}</Button></div>
    </form></div>}
  </>
}

function Reports(){
  const [data,setData]=useState(null),[error,setError]=useState('');
  useEffect(()=>{api('/reports/summary').then(r=>setData(r.data||r)).catch(e=>setError(e.message))},[]);
  const d=data||{}, m=d.summary||{};
  const moneyMetric=(label,value,sub)=> <div className="metric"><small>{label}</small><strong>{money(value)}</strong><span>{sub||''}</span></div>;
  const pct=(v)=>String(v??0)+'%';
  return <><PageHead title="Reports & analytics" desc="A complete sales, funnel, revenue, pipeline and activity view."/>
    {error&&<div className="og-error">{error}</div>}
    <div className="stat-grid">
      <div className="metric"><small>Lead conversion</small><strong>{pct(m.conversion_rate)}</strong><span>{m.converted_leads||0} converted of {m.total_leads||0}</span></div>
      <div className="metric"><small>Win rate</small><strong>{pct(m.win_rate)}</strong><span>{m.won||0} won · {m.lost||0} lost</span></div>
      {moneyMetric('Open pipeline',m.pipeline_value,'Current open opportunities')}
      {moneyMetric('Weighted forecast',m.weighted_pipeline,'Probability-adjusted pipeline')}
      {moneyMetric('Won revenue',m.won_value,'Value of won opportunities')}
      {moneyMetric('Paid revenue',m.paid_revenue,'Recorded paid payments')}
      {moneyMetric('Outstanding',m.outstanding,'Unpaid invoice balance')}
      {moneyMetric('Overdue',m.overdue,'Past-due invoice balance')}
      <div className="metric"><small>Average won deal</small><strong>{money(m.average_won_deal)}</strong><span>Average value of won opportunities</span></div>
      <div className="metric"><small>Sales cycle</small><strong>{m.sales_cycle_days||0} days</strong><span>Average created → updated time for won deals</span></div>
    </div>
    <div className="dashboard-grid">
      <section className="panel wide"><div className="panel-head"><h3>Lead sources</h3><span>Volume and conversion</span></div><div className="bar-list">{(d.leads_by_source||[]).map(x=><div key={x.source||'unknown'}><span>{x.source||'Unknown'} <small>{x.converted||0} converted</small></span><b>{x.count} · {pct(x.conversion_rate)}</b></div>)}{!d.leads_by_source?.length&&<div className="empty">No lead source data yet.</div>}</div></section>
      <section className="panel"><div className="panel-head"><h3>Pipeline stages</h3><span>Value + weighted value</span></div><div className="bar-list">{(d.pipeline_by_stage||[]).map(x=><div key={x.stage}><span>{x.stage}</span><b>{money(x.value)} · {money(x.weighted_value)}</b></div>)}{!d.pipeline_by_stage?.length&&<div className="empty">No opportunity data yet.</div>}</div></section>
      <section className="panel"><div className="panel-head"><h3>Activity mix</h3><span>CRM records</span></div><div className="bar-list">{(d.activity||[]).map(x=><div key={x.type}><span>{labels(x.type)}</span><b>{x.count}</b></div>)}</div></section>
      <section className="panel wide"><div className="panel-head"><h3>12-month lead trend</h3><span>Leads and conversions</span></div><div className="bar-list">{(d.monthly_leads||[]).map(x=><div key={x.month}><span>{x.month}</span><b>{x.leads} leads · {x.converted} converted</b></div>)}{!d.monthly_leads?.length&&<div className="empty">No monthly lead data.</div>}</div></section>
      <section className="panel wide"><div className="panel-head"><h3>12-month paid revenue</h3><span>Recorded payments</span></div><div className="bar-list">{(d.monthly_revenue||[]).map(x=><div key={x.month}><span>{x.month}</span><b>{money(x.revenue)}</b></div>)}{!d.monthly_revenue?.length&&<div className="empty">No paid revenue data.</div>}</div></section>
      <section className="panel wide"><div className="panel-head"><h3>Top open opportunities</h3><span>Highest current value</span></div><div className="bar-list">{(d.top_opportunities||[]).map(x=><div key={x.id}><span>{x.name} <small>{x.company||'—'} · {x.stage}</small></span><b>{money(x.amount)} · {x.probability||0}%</b></div>)}{!d.top_opportunities?.length&&<div className="empty">No open opportunities.</div>}</div></section>
    </div>
  </>;
}

function Settings(){
  const [tab,setTab]=useState('workspace'),[templates,setTemplates]=useState([]),[settings,setSettings]=useState(null),[saving,setSaving]=useState(false),[notice,setNotice]=useState('');
  useEffect(()=>{api('/settings').then(r=>setSettings(r.data||r)).catch(e=>setNotice(e.message));},[]);
  useEffect(()=>{if(tab==='templates')api('/whatsapp/templates').then(r=>setTemplates(r.data||r)).catch(()=>setTemplates([]))},[tab]);
  const saveSettings=async()=>{if(!settings)return;setSaving(true);try{const r=await api('/settings',{method:'POST',body:JSON.stringify(settings)});setSettings(r.data||r);setNotice('Settings saved.')}catch(e){setNotice(e.message)}finally{setSaving(false)}};
  return <><PageHead title="Settings" desc="Configure workspace, WhatsApp, media and external integrations."/>
    <div className="settings-tabs">{['workspace','whatsapp','templates','media','integrations','pipelines','tags'].map(x=><button className={tab===x?'active':''} onClick={()=>setTab(x)} key={x}>{labels(x)}</button>)}</div><section className="panel">
    {notice&&<div className="og-notice">{notice}</div>}
    {tab==='workspace'&&settings&&<><div className="form-grid">
      {['business_name','currency','timezone','lead_default_status','company_website'].map(k=><label key={k}>{labels(k)}<input value={settings[k]??''} onChange={e=>setSettings({...settings,[k]:e.target.value})}/></label>)}
    </div><div className="modal-actions"><Button kind="primary" onClick={saveSettings} disabled={saving}>{saving?'Saving…':'Save settings'}</Button></div></>}
    {tab==='whatsapp'&&settings&&<><div className="settings-section"><h3>WhatsApp opening preference</h3><p>Choose where CRM messages should open by default. WhatsApp still requires you to press Send.</p><div className="form-grid">
      <label>Default destination<select value={settings.whatsapp_default_target||'web'} onChange={e=>setSettings({...settings,whatsapp_default_target:e.target.value})}><option value="web">WhatsApp Web</option><option value="desktop">WhatsApp Desktop</option><option value="mobile_personal">Mobile · WhatsApp Personal</option><option value="mobile_business">Mobile · WhatsApp Business</option></select></label>
      <label>Mobile default<select value={settings.whatsapp_mobile_target||'personal'} onChange={e=>setSettings({...settings,whatsapp_mobile_target:e.target.value})}><option value="personal">WhatsApp Personal</option><option value="business">WhatsApp Business</option></select></label>
    </div><p className="muted">On Android, Personal/Business targets use app-specific intent routing when available. On iPhone, WhatsApp's supported universal/custom URL behavior is used.</p></div>
    <div className="modal-actions"><Button kind="primary" onClick={saveSettings} disabled={saving}>{saving?'Saving…':'Save WhatsApp settings'}</Button></div></>}
    {tab==='templates'&&<div>{templates.map(t=><div className="note-row" key={t.id}><b>{t.name}</b><span>{t.body}</span></div>)}{!templates.length&&<div className="empty">No WhatsApp templates found.</div>}</div>}
    {tab==='media'&&<Media/>}
    {tab==='integrations'&&<Integrations/>}
    {tab==='pipelines'&&<PipelineSettings/>}{tab==='tags'&&<TagSettings/>}
  </section></>
}

function Media(){
  const [rows,setRows]=useState([]),[busy,setBusy]=useState(false),[notice,setNotice]=useState('');
  const load=()=>api('/whatsapp/assets').then(r=>setRows(r.data||r)).catch(e=>setNotice(e.message));
  useEffect(load,[]);
  const upload=async e=>{
    const file=e.target.files?.[0]; if(!file)return;
    setBusy(true);setNotice('');
    try{
      const wpRoot=(cfg.wpRoot||((window.location.origin||'')+'/wp-json/')).replace(/\/$/,'');
      const headers={'X-WP-Nonce':nonce,'Content-Disposition':'attachment; filename="'+file.name.replace(/"/g,'')+'"','Content-Type':file.type||'application/octet-stream'};
      const res=await fetch(wpRoot+'/wp/v2/media',{method:'POST',credentials:'same-origin',headers,body:file});
      const media=await res.json();
      if(!res.ok)throw new Error(media.message||'WordPress media upload failed');
      await api('/whatsapp/assets',{method:'POST',body:JSON.stringify({name:media.title?.rendered||file.name,description:'Uploaded from OmniGoCRM',asset_type:media.media_type||'document',url:media.source_url,thumbnail_url:media.media_details?.sizes?.thumbnail?.source_url||'',mime_type:media.mime_type||file.type||''})});
      setNotice('File uploaded and added to the WhatsApp media library.');
      load();
    }catch(err){setNotice(err.message)}finally{setBusy(false);e.target.value='';}
  };
  return <div className="media-manager">
    <div className="panel-head"><div><h3>WhatsApp media library</h3><span>Upload files to WordPress and reuse their public links in CRM WhatsApp messages.</span></div></div>
    <div className="upload-box"><input type="file" onChange={upload} disabled={busy}/><span>{busy?'Uploading…':'Choose a file to upload'}</span><small>Files are stored in the WordPress Media Library; the CRM sends the file URL in the prepared WhatsApp message.</small></div>
    {notice&&<div className="og-notice">{notice}</div>}
    <div className="media-grid">{rows.map(x=><div className="media-card" key={x.id}><div><b>{x.name}</b><small>{x.mime_type||x.asset_type}</small></div><a href={x.url} target="_blank" rel="noreferrer">Open</a></div>)}{!rows.length&&<div className="empty">No WhatsApp media uploaded yet.</div>}</div>
  </div>
}

function PipelineSettings(){const [rows,setRows]=useState([]);useEffect(()=>api('/pipelines').then(r=>setRows(r.data||r)).catch(()=>{}),[]);return <div>{rows.map(x=><div className="pipeline-row" key={x.id}><div><b>{x.name}</b><small>{x.description||'Sales pipeline'}</small></div></div>)}{!rows.length&&<div className="empty">No pipelines found.</div>}</div>}
function TagSettings(){const [rows,setRows]=useState([]);useEffect(()=>api('/tags').then(r=>setRows(r.data||r)).catch(()=>{}),[]);return <div>{rows.map(x=><div className="tag-row" key={x.id}><b>{x.name}</b></div>)}{!rows.length&&<div className="empty">No tags found.</div>}</div>}

export default function App(){
  const [page,setPage]=useState('dashboard');
  const [leadId,setLeadId]=useState(null);
  const content=leadId?<LeadDetail leadId={leadId} onBack={()=>setLeadId(null)}/>:page==='dashboard'?<Dashboard go={setPage}/>:page==='conversations'?<Inbox/>:page==='reports'?<Reports/>:page==='team'?<Team/>:page==='calendar'?<Calendar/>:page==='notifications'?<Notifications/>:page==='billing'?<Billing/>:page==='integrations'?<Integrations/>:page==='audit'?<Audit/>:page==='settings'?<Settings/>:resources[page]?<ResourcePage type={page} onSelect={page==='leads'?setLeadId:undefined}/>:<div className="panel empty">This workspace module is being connected to the React API.</div>;
  return <Shell page={page} setPage={setPage}>{content}</Shell>;
}
