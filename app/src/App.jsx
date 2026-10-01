import React, { useEffect, useMemo, useState } from 'react';

const cfg = window.OmniGoCRMConfig || {};
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
  ['products','◇','Products'],['tasks','✓','Tasks'],['conversations','◍','Inbox'],['campaigns','✦','Campaigns'],
  ['automations','⚙','Automation'],['calendar','◷','Calendar'],['notifications','●','Notifications'],['billing','₹','Billing'],['integrations','⌘','Integrations'],['audit','≡','Audit Log'],['settings','☷','Settings']
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
  tasks:{title:'Tasks',endpoint:'/tasks',fields:['title','status','priority','due_date','assigned_to']},
  campaigns:{title:'Campaigns',endpoint:'/campaigns',fields:['name','channel','status','audience_type','scheduled_at']},
  automations:{title:'Automation',endpoint:'/automations',fields:['name','trigger_type','active','description']},
};

const labels = s => String(s || '').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
const money = n => Number(n || 0).toLocaleString(undefined,{maximumFractionDigits:2});
const initials = (a,b) => ((a||'').charAt(0)+(b||'').charAt(0)).toUpperCase() || 'OG';

function Button({children,onClick,kind='ghost',type='button'}){return <button type={type} className={kind} onClick={onClick}>{children}</button>}

function Shell({page,setPage,children}) {
  const [search,setSearch]=useState('');
  return <div className="og-shell">
    <aside className="og-side">
      <div className="og-brand"><span>OG</span><div><b>OmniGoCRM</b><small>WordPress SaaS CRM</small></div></div>
      <div className="og-workspace"><div className="workspace-avatar">S</div><div><b>Workspace</b><small>Default workspace</small></div><span>⌄</span></div>
      <nav>{nav.map(([id,icon,name])=><button key={id} className={page===id?'active':''} onClick={()=>setPage(id)}><i>{icon}</i>{name}</button>)}</nav>
      <div className="og-side-bottom">React interface<br/><small>v{cfg.version || '0.5.0'}</small></div>
    </aside>
    <main className="og-main">
      <header className="og-top"><div><div className="eyebrow">CRM WORKSPACE</div><h1>{nav.find(x=>x[0]===page)?.[2] || 'OmniGoCRM'}</h1></div>
        <div className="top-tools"><div className="global-search">⌕ <input value={search} onChange={e=>setSearch(e.target.value)} placeholder="Search CRM"/></div><button className="icon-btn" title="Notifications">🔔</button><span className="status-chip"><i/>Live</span></div>
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

function LeadDetail({leadId, onBack}) {
  const [lead,setLead]=useState(null), [tab,setTab]=useState('overview'), [notes,setNotes]=useState([]), [tasks,setTasks]=useState([]), [templates,setTemplates]=useState([]), [message,setMessage]=useState(''), [busy,setBusy]=useState(false), [notice,setNotice]=useState('');
  const load=async()=>{try{const [l,n,t,tm]=await Promise.all([api('/leads/'+leadId),api('/notes'),api('/tasks'),api('/whatsapp/templates')]);setLead(l.data||l);setNotes((n.data||n).filter(x=>String(x.lead_id||'')===String(leadId)||String(x.related_id||'')===String(leadId)));setTasks((t.data||t).filter(x=>String(x.related_id||'')===String(leadId)));setTemplates(tm.data||tm)}catch(e){setNotice(e.message)}};
  useEffect(()=>{load()},[leadId]);
  if(!lead)return <><PageHead title="Lead" desc="Loading lead record…"/><div className="panel empty">{notice||'Loading…'}</div></>;
  const sendWhatsApp=async()=>{if(!message.trim())return;setBusy(true);try{const r=await api('/leads/'+leadId+'/whatsapp/prepare',{method:'POST',body:JSON.stringify({body:message,template_id:0})});setNotice('WhatsApp message prepared. Open WhatsApp to send it.');if(r.whatsapp_url)window.open(r.whatsapp_url,'_blank')}catch(e){setNotice(e.message)}finally{setBusy(false)}};
  const convert=async()=>{setBusy(true);try{const r=await api('/leads/'+leadId+'/convert',{method:'POST',body:'{}'});setNotice('Lead converted successfully. Contact #'+r.contact_id+(r.opportunity_id?' · Opportunity #'+r.opportunity_id:''));load()}catch(e){setNotice(e.message)}finally{setBusy(false)}};
  const addNote=async()=>{const body=window.prompt('Note for this lead:');if(!body)return;await api('/notes',{method:'POST',body:JSON.stringify({body,lead_id:leadId,related_type:'lead',related_id:leadId,created_by:cfg.userId||0})});load()};
  const addTask=async()=>{const title=window.prompt('Task title:');if(!title)return;await api('/tasks',{method:'POST',body:JSON.stringify({title,status:'open',priority:'normal',related_type:'lead',related_id:leadId,assigned_to:cfg.userId||0})});load()};
  return <><PageHead title={[lead.first_name,lead.last_name].filter(Boolean).join(' ')||'Lead'} desc={lead.company||lead.email||'Lead record'} />
    <div className="lead-toolbar"><Button onClick={onBack}>← Back to leads</Button><div><Button onClick={addNote}>＋ Note</Button><Button onClick={addTask}>＋ Task</Button><Button kind="primary" onClick={convert} disabled={busy}>Convert lead</Button></div></div>
    {notice&&<div className="og-notice">{notice}</div>}
    <div className="lead-summary panel"><div className="big-avatar">{initials(lead.first_name,lead.last_name)}</div><div><h2>{lead.first_name} {lead.last_name}</h2><p>{lead.job_title||'Prospect'} · {lead.company||'No company'}</p></div><div className="lead-facts"><span><small>Status</small><b>{lead.status||'new'}</b></span><span><small>Score</small><b>{lead.score||0}</b></span><span><small>Value</small><b>{money(lead.value)}</b></span></div></div>
    <div className="detail-tabs">{['overview','activity','whatsapp','notes','tasks','related'].map(x=><button className={tab===x?'active':''} onClick={()=>setTab(x)} key={x}>{labels(x)}</button>)}</div>
    {tab==='overview'&&<section className="panel detail-grid"><div><h3>Contact information</h3>{[['Email',lead.email],['Phone',lead.phone],['Website',lead.website],['Location',lead.location],['Industry',lead.industry],['Source',lead.source]].map(([k,v])=><div className="detail-line" key={k}><small>{k}</small><span>{v||'—'}</span></div>)}</div><div><h3>Lead notes</h3><p>{lead.notes||'No lead notes yet.'}</p></div></section>}
    {tab==='activity'&&<section className="panel"><h3>Recent activity</h3><div className="timeline"><div>Lead created <small>{lead.created_at||''}</small></div><div>Status: <b>{lead.status||'new'}</b><small>{lead.updated_at||''}</small></div>{notes.slice(0,5).map(n=><div key={n.id}>Note added <small>{n.created_at||''}</small><p>{n.body}</p></div>)}</div></section>}
    {tab==='whatsapp'&&<section className="panel"><h3>WhatsApp</h3><p>Prepare a message using the CRM WhatsApp workflow.</p><div className="form-grid"><label>Template<select onChange={e=>{const t=templates.find(x=>String(x.id)===e.target.value);if(t)setMessage(t.body)}}><option value="">Custom message</option>{templates.map(t=><option value={t.id} key={t.id}>{t.name}</option>)}</select></label></div><textarea className="wa-compose" value={message} onChange={e=>setMessage(e.target.value)} placeholder="Write your WhatsApp message…"/><div className="modal-actions"><Button kind="primary" onClick={sendWhatsApp} disabled={busy}>Prepare WhatsApp</Button></div></section>}
    {tab==='notes'&&<section className="panel"><div className="panel-head"><h3>Notes</h3><Button onClick={addNote}>＋ Add note</Button></div>{notes.map(n=><div className="note-row" key={n.id}><b>{n.created_at||'Note'}</b><span>{n.body}</span></div>)}{!notes.length&&<div className="empty">No notes for this lead.</div>}</section>}
    {tab==='tasks'&&<section className="panel"><div className="panel-head"><h3>Tasks</h3><Button onClick={addTask}>＋ Add task</Button></div>{tasks.map(t=><div className="note-row" key={t.id}><b>{t.title}</b><span>{t.status} · {t.priority} · {t.due_date||'No due date'}</span></div>)}{!tasks.length&&<div className="empty">No tasks linked to this lead.</div>}</section>}
    {tab==='related'&&<section className="panel"><h3>Related records</h3><div className="snapshot"><div><small>Company</small><b>{lead.company||'—'}</b></div><div><small>Contact</small><b>Created on conversion</b></div><div><small>Opportunity</small><b>{lead.value?'Potential opportunity':'Not yet created'}</b></div></div></section>}
  </>;
}

function ResourcePage({type,onSelect}) {
  const meta=resources[type], [rows,setRows]=useState([]), [loading,setLoading]=useState(true), [error,setError]=useState(''), [q,setQ]=useState(''), [selected,setSelected]=useState(null), [modal,setModal]=useState(false);
  const load=()=>{setLoading(true);api(meta.endpoint).then(r=>setRows(Array.isArray(r)?r:(r.data||r.items||[]))).catch(e=>setError(e.message)).finally(()=>setLoading(false))};
  useEffect(load,[]);
  const filtered=useMemo(()=>rows.filter(r=>JSON.stringify(r).toLowerCase().includes(q.toLowerCase())),[rows,q]);
  const create=async form=>{await api(meta.endpoint,{method:'POST',body:JSON.stringify(form)});setModal(false);load()};
  return <><PageHead title={meta.title} desc={'Manage '+meta.title.toLowerCase()+' from one workspace.'} onAdd={()=>setModal(true)} addLabel={'Add '+meta.title.replace(/s$/,'')}/>
    <div className="toolbar-card"><div className="table-search">⌕<input value={q} onChange={e=>setQ(e.target.value)} placeholder={'Search '+meta.title.toLowerCase()}/></div><span className="result-count">{filtered.length} records</span></div>
    {error&&<div className="og-error">{error}</div>}
    <section className="panel table-panel">{loading?<div className="empty">Loading…</div>:!filtered.length?<div className="empty">No records yet. Create the first one.</div>:
      <table><thead><tr>{meta.fields.slice(0,7).map(f=><th key={f}>{labels(f)}</th>)}</tr></thead><tbody>{filtered.map((r,i)=><tr key={r.id||i} onClick={()=>{setSelected(r);if(onSelect)onSelect(r.id) }}>{meta.fields.slice(0,7).map(f=><td key={f}>{f==='price'||f==='amount'||f==='total'||f==='value'?money(r[f]):f==='active'?<span className="badge">{r[f]?'Active':'Inactive'}</span>:String(r[f]??'—')}</td>)}</tr>)}</tbody></table>}
    </section>
    {selected&&<div className="side-detail"><div className="detail-top"><button className="close" onClick={()=>setSelected(null)}>×</button><div className="detail-person"><div className="big-avatar">{initials(selected.first_name,selected.last_name||selected.name)}</div><div><h2>{selected.name||[selected.first_name,selected.last_name].filter(Boolean).join(' ')}</h2><p>{selected.email||selected.company||selected.status||'CRM record'}</p></div></div></div><div className="detail-body"><div className="detail-card"><h3>Record details</h3>{Object.entries(selected).filter(([k])=>!['id','created_at','updated_at'].includes(k)).slice(0,14).map(([k,v])=><div className="detail-line" key={k}><small>{labels(k)}</small><span>{typeof v==='object'?JSON.stringify(v):String(v??'—')}</span></div>)}</div></div></div>}
    {modal&&<RecordModal title={'Create '+meta.title.replace(/s$/,'')} fields={meta.fields} onClose={()=>setModal(false)} onSave={create}/>}
  </>
}

function RecordModal({title,fields,onClose,onSave}) {
  const [form,setForm]=useState({});
  return <div className="modal-back"><div className="modal wide"><button className="close" onClick={onClose}>×</button><h2>{title}</h2><div className="form-grid">
    {fields.map(f=><label key={f}>{labels(f)}<input value={form[f]??''} onChange={e=>setForm({...form,[f]:e.target.value})} /></label>)}
  </div><div className="modal-actions"><Button onClick={onClose}>Cancel</Button><Button kind="primary" onClick={()=>onSave(form)}>Save</Button></div></div></div>
}

function Inbox(){
  const [convs,setConvs]=useState([]),[active,setActive]=useState(null),[messages,setMessages]=useState([]),[text,setText]=useState('');
  const load=()=>api('/conversations').then(r=>setConvs(Array.isArray(r)?r:(r.data||[]))).catch(()=>setConvs([]));
  useEffect(load,[]);
  useEffect(()=>{if(active)api('/conversations/'+active+'/messages').then(r=>setMessages(Array.isArray(r)?r:(r.data||[]))).catch(()=>setMessages([]))},[active]);
  const send=async()=>{if(!active||!text.trim())return;await api('/conversations/'+active+'/messages',{method:'POST',body:JSON.stringify({body:text,direction:'outbound'})});setText('');api('/conversations/'+active+'/messages').then(r=>setMessages(r.data||r));};
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
  const read=async id=>{await api('/notifications/'+id+'/read',{method:'POST'});load()};
  return <><PageHead title="Notifications" desc="CRM alerts and workflow activity."/><section className="panel">{rows.map(x=><div className={'notification '+(x.read_at?'read':'')} key={x.id}><div><b>{x.title}</b><p>{x.body||''}</p><small>{x.created_at||''}</small></div>{!x.read_at&&<Button onClick={()=>read(x.id)}>Mark read</Button>}</div>)}{!rows.length&&<div className="empty">No notifications.</div>}</section></>
}

function Billing(){
  const [plans,setPlans]=useState([]),[sub,setSub]=useState(null);
  useEffect(()=>{Promise.all([api('/plans'),api('/subscription')]).then(([p,s])=>{setPlans(p.data||p);setSub(s.data||s)}).catch(()=>{})},[]);
  const choose=async id=>{await api('/subscription',{method:'POST',body:JSON.stringify({plan_id:id})});setSub({plan_id:id})};
  return <><PageHead title="Billing" desc="Plans and workspace subscription."/><div className="billing-grid">{plans.map(p=><div className="panel plan-card" key={p.id}><h3>{p.name}</h3><strong>{p.currency||'INR'} {money(p.price)}</strong><p>{p.description||'CRM workspace plan'}</p><Button kind={String(sub?.plan_id)===String(p.id)?'primary':'ghost'} onClick={()=>choose(p.id)}>{String(sub?.plan_id)===String(p.id)?'Current plan':'Select plan'}</Button></div>)}{!plans.length&&<div className="panel empty">No plans configured.</div>}</div></>
}

function Integrations(){
  const [rows,setRows]=useState([]),[modal,setModal]=useState(false);
  const load=()=>api('/integrations').then(r=>setRows(r.data||r)).catch(()=>setRows([])); useEffect(load,[]);
  const save=async e=>{e.preventDefault();const f=new FormData(e.currentTarget);await api('/integrations',{method:'POST',body:JSON.stringify({name:f.get('name'),type:f.get('type'),status:'configured',config:{}})});setModal(false);load()};
  return <><PageHead title="Integrations" desc="Connect channels and external services to the CRM." onAdd={()=>setModal(true)} addLabel="Add integration"/><section className="panel">{rows.map(x=><div className="integration-row" key={x.id}><div><b>{x.name}</b><small>{x.type} · {x.status}</small></div></div>)}{!rows.length&&<div className="empty">No integrations configured.</div>}</section>{modal&&<div className="modal-back"><form className="modal" onSubmit={save}><button className="close" type="button" onClick={()=>setModal(false)}>×</button><h2>Add integration</h2><label className="stack">Name<input name="name" required/></label><label className="stack">Type<input name="type" placeholder="whatsapp, email, sms..." required/></label><div className="modal-actions"><Button type="button" onClick={()=>setModal(false)}>Cancel</Button><Button kind="primary" type="submit">Save</Button></div></form></div>}</>
}

function Audit(){
  const [rows,setRows]=useState([]);
  useEffect(()=>api('/audit-logs').then(r=>setRows(r.data||r)).catch(()=>setRows([])),[]);
  return <><PageHead title="Audit Log" desc="Track changes and actions performed in the CRM."/><section className="panel table-panel"><table><thead><tr><th>Action</th><th>Object</th><th>ID</th><th>User</th><th>Date</th></tr></thead><tbody>{rows.map(x=><tr key={x.id}><td>{x.action}</td><td>{x.object_type}</td><td>{x.object_id}</td><td>{x.user_id}</td><td>{x.created_at}</td></tr>)}</tbody></table>{!rows.length&&<div className="empty">No audit events.</div>}</section></>
}

function Reports(){
  const [data,setData]=useState({});
  useEffect(()=>api('/reports/summary').then(setData).catch(()=>{}),[]);
  return <><PageHead title="Reports" desc="Sales and activity reporting for your CRM."/><div className="dashboard-grid">
    <section className="panel"><h3>Sales snapshot</h3><div className="bar-list">{Object.entries(data||{}).slice(0,8).map(([k,v])=><div key={k}><span>{labels(k)}</span><b>{typeof v==='number'?money(v):String(v)}</b></div>)}</div>{!Object.keys(data||{}).length&&<div className="empty">Report data will appear as records are created.</div>}</section>
    <section className="panel"><h3>Operational coverage</h3><div className="checks">{['Leads','Contacts','Pipeline','Quotes','Orders','Invoices','Payments','Inbox','Automation'].map(x=><li key={x}>Connected: {x}</li>)}</div></section>
  </div></>
}

function Settings(){
  const [tab,setTab]=useState('workspace'),[templates,setTemplates]=useState([]);
  useEffect(()=>{if(tab==='templates')api('/whatsapp/templates').then(r=>setTemplates(r.data||r)).catch(()=>setTemplates([]))},[tab]);
  return <><PageHead title="Settings" desc="Configure your CRM workspace and communication assets."/><div className="settings-tabs">{['workspace','templates','media','pipelines','tags'].map(x=><button className={tab===x?'active':''} onClick={()=>setTab(x)} key={x}>{labels(x)}</button>)}</div><section className="panel">
    {tab==='workspace'&&<div className="form-grid"><label>Workspace name<input defaultValue="OmniGoCRM"/></label><label>Currency<input defaultValue="INR"/></label><label>Timezone<input defaultValue="Asia/Kolkata"/></label><label>Default lead status<input defaultValue="new"/></label></div>}
    {tab==='templates'&&<div>{templates.map(t=><div className="note-row" key={t.id}><b>{t.name}</b><span>{t.body}</span></div>)}{!templates.length&&<div className="empty">No WhatsApp templates found.</div>}</div>}
    {tab==='media'&&<Media/>}{tab==='pipelines'&&<PipelineSettings/>}{tab==='tags'&&<TagSettings/>}
  </section></>
}

function Media(){const [rows,setRows]=useState([]);useEffect(()=>api('/whatsapp/assets').then(r=>setRows(r.data||r)).catch(()=>{}),[]);return <div>{rows.map(x=><div className="asset-row" key={x.id}><div><b>{x.name}</b><small>{x.url||x.media_url}</small></div></div>)}{!rows.length&&<div className="empty">No media assets found.</div>}</div>}
function PipelineSettings(){const [rows,setRows]=useState([]);useEffect(()=>api('/pipelines').then(r=>setRows(r.data||r)).catch(()=>{}),[]);return <div>{rows.map(x=><div className="pipeline-row" key={x.id}><div><b>{x.name}</b><small>{x.description||'Sales pipeline'}</small></div></div>)}{!rows.length&&<div className="empty">No pipelines found.</div>}</div>}
function TagSettings(){const [rows,setRows]=useState([]);useEffect(()=>api('/tags').then(r=>setRows(r.data||r)).catch(()=>{}),[]);return <div>{rows.map(x=><div className="tag-row" key={x.id}><b>{x.name}</b></div>)}{!rows.length&&<div className="empty">No tags found.</div>}</div>}

export default function App(){
  const [page,setPage]=useState('dashboard');
  const [leadId,setLeadId]=useState(null);
  const content=leadId?<LeadDetail leadId={leadId} onBack={()=>setLeadId(null)}/>:page==='dashboard'?<Dashboard go={setPage}/>:page==='conversations'?<Inbox/>:page==='reports'?<Reports/>:page==='calendar'?<Calendar/>:page==='notifications'?<Notifications/>:page==='billing'?<Billing/>:page==='integrations'?<Integrations/>:page==='audit'?<Audit/>:page==='settings'?<Settings/>:resources[page]?<ResourcePage type={page} onSelect={page==='leads'?setLeadId:undefined}/>:<div className="panel empty">This workspace module is being connected to the React API.</div>;
  return <Shell page={page} setPage={setPage}>{content}</Shell>;
}
