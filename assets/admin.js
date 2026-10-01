(() => {
const C=window.OmniGoCRMConfig;
const app=document.getElementById('omnigocrm-app');
const state={page:'dashboard',rows:[],leads:[],lead:null,templates:[],media:[],dash:{}};
const labels={contacts:'Contacts',companies:'Companies',opportunities:'Opportunities',tasks:'Tasks',products:'Products'};
const api=async(path,opt={})=>{
  const r=await fetch(C.restUrl+path,{...opt,headers:{'Content-Type':'application/json','X-WP-Nonce':C.nonce,...(opt.headers||{})}});
  const d=await r.json(); if(!r.ok) throw new Error(d.message||'Request failed'); return d;
};
const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
const money=n=>'₹'+Number(n||0).toLocaleString('en-IN',{maximumFractionDigits:2});
function shell(){
app.innerHTML=`<div class="og-shell"><aside><div class="og-brand"><span>OG</span><b>OmniGoCRM</b></div>
<nav>${[['dashboard','Dashboard'],['leads','Leads'],...Object.entries(labels).map(([k,v])=>[k,v])].map(x=>`<button data-page="${x[0]}" class="${state.page===x[0]?'active':''}">${x[1]}</button>`).join('')}</nav>
<div class="og-foot">WordPress Edition<br><small>CRM workspace</small></div></aside>
<main><header><div><span class="eyebrow">CRM WORKSPACE</span><h1>${state.page==='dashboard'?'Dashboard':labels[state.page]||'Leads'}</h1></div><div class="top-actions"><span class="status-dot"></span> Connected</div></header><section id="og-content"></section></main></div>`;
document.querySelectorAll('[data-page]').forEach(b=>b.onclick=()=>{state.page=b.dataset.page;state.lead=null;render();});
}
async function render(){
shell(); const el=document.getElementById('og-content');
try{
 if(state.page==='dashboard'){state.dash=await api('/dashboard');el.innerHTML=dashboard();}
 else if(state.page==='leads'){state.leads=await api('/leads?per_page=100');el.innerHTML=leads();}
 else {state.rows=await api('/resource/'+state.page+'?per_page=100');el.innerHTML=resourceTable(state.page,state.rows);}
 bind();
}catch(e){el.innerHTML=`<div class="og-error">${esc(e.message)}</div>`;}
}
function dashboard(){
const d=state.dash;
return `<div class="cards">
<div><small>Total Leads</small><strong>${d.leads}</strong><span>CRM database</span></div>
<div><small>Contacts</small><strong>${d.contacts}</strong><span>People</span></div>
<div><small>Companies</small><strong>${d.companies}</strong><span>Accounts</span></div>
<div><small>Opportunities</small><strong>${d.opportunities}</strong><span>${money(d.revenue)} pipeline</span></div>
<div><small>Open Tasks</small><strong>${d.tasks}</strong><span>Not completed</span></div>
</div>
<div class="panel-grid"><article class="panel"><div class="panel-head"><h2>OmniGoCRM CMS Edition</h2><span>v0.2</span></div>
<p>A WordPress-native CRM designed for shared hosting. Manage leads, contacts, companies, opportunities, tasks and products from one workspace.</p>
<div class="quick"><button data-page="leads">Open Leads</button><button data-page="contacts">Contacts</button></div></article>
<article class="panel"><h2>Core capabilities</h2><ul class="checks"><li>WordPress authentication</li><li>MySQL/MariaDB CRM tables</li><li>REST API with CRUD operations</li><li>Search-ready resource endpoints</li><li>WhatsApp Click-to-Chat</li></ul></article></div>`;
}
function leads(){
return `<div class="toolbar"><input id="lead-search" placeholder="Search leads by name, company, email or phone..."><button id="new-lead">+ New Lead</button></div>
<div class="panel"><table><thead><tr><th>Name</th><th>Company</th><th>Phone</th><th>Source</th><th>Status</th><th>Value</th><th></th></tr></thead>
<tbody>${state.leads.map(l=>`<tr><td><b>${esc(l.first_name+' '+l.last_name)}</b><small>${esc(l.email)}</small></td><td>${esc(l.company)}</td><td>${esc(l.phone)}</td><td>${esc(l.source)}</td><td><span class="badge">${esc(l.status)}</span></td><td>${money(l.value)}</td><td><button class="link lead-open" data-id="${l.id}">Open</button></td></tr>`).join('')}</tbody></table></div>${state.lead?leadModal():''}`;
}
function leadModal(){
const l=state.lead;
return `<div class="modal-back"><div class="modal wide"><button class="close" id="close">×</button><span class="eyebrow">LEAD #${l.id}</span><h2>${esc(l.first_name+' '+l.last_name)}</h2>
<div class="form-grid"><label>First name<input id="lf" value="${esc(l.first_name)}"></label><label>Last name<input id="ll" value="${esc(l.last_name)}"></label><label>Company<input id="lc" value="${esc(l.company)}"></label><label>Email<input id="le" value="${esc(l.email)}"></label><label>Phone<input id="lp" value="${esc(l.phone)}"></label><label>Source<input id="ls" value="${esc(l.source)}"></label><label>Status<select id="lst"><option ${l.status==='new'?'selected':''}>new</option><option ${l.status==='contacted'?'selected':''}>contacted</option><option ${l.status==='qualified'?'selected':''}>qualified</option><option ${l.status==='won'?'selected':''}>won</option><option ${l.status==='lost'?'selected':''}>lost</option></select></label><label>Value<input id="lv" type="number" step="0.01" value="${Number(l.value||0)}"></label></div>
<div class="modal-actions"><button id="save-lead" class="primary">Save changes</button><button id="delete-lead" class="danger">Delete</button></div>
<div class="wa-box"><div><h3>WhatsApp Composer</h3><p>Prepare a Click-to-Chat message for this lead.</p></div>
${state.templates.length? `<select id="template"><option value="">Custom message</option>${state.templates.map(t=>`<option value="${t.id}">${esc(t.name)}</option>`).join('')}</select>`:''}
<textarea id="message" placeholder="Write your WhatsApp message..."></textarea>
${state.media.length? `<select id="media"><option value="0">No media link</option>${state.media.map(m=>`<option value="${m.id}">${esc(m.name)}</option>`).join('')}</select>`:''}
<button id="send-wa" class="wa">Open WhatsApp</button><small>WhatsApp opens with the message prepared. You press Send in WhatsApp.</small></div></div></div>`;
}
function resourceTable(type,rows){
const cols=rows.length?Object.keys(rows[0]).filter(k=>!['created_at','updated_at','description','address','related_type','related_id'].includes(k)).slice(0,8):[];
return `<div class="toolbar"><input id="resource-search" placeholder="Search ${esc(labels[type].toLowerCase())}..."><button id="new-resource">+ New ${esc(labels[type].slice(0,-1)||labels[type])}</button></div>
<div class="panel"><div class="panel-head"><h2>${esc(labels[type])}</h2><span>${rows.length} records</span></div>
${rows.length?`<table><thead><tr>${cols.map(k=>`<th>${esc(k.replaceAll('_',' '))}</th>`).join('')}<th></th></tr></thead><tbody>${rows.map(r=>`<tr>${cols.map(k=>`<td>${esc(k==='amount'||k==='price'?money(r[k]):r[k])}</td>`).join('')}<td><button class="link resource-delete" data-id="${r.id}">Delete</button></td></tr>`).join('')}</tbody></table>`:'<div class="empty">No records yet. Use “New” to create the first record.</div>'}</div>`;
}
function promptResource(type){
const fields={contacts:['first_name','last_name','company','email','phone'],companies:['name','email','phone','website','address'],opportunities:['name','company','stage','amount','close_date'],tasks:['title','description','status','due_date'],products:['name','sku','price','description']};
const fs=fields[type]; const data={};
for(const f of fs){const v=prompt('Enter '+f.replaceAll('_',' '));if(v===null)return;data[f]=v;}
return data;
}
function bind(){
document.querySelectorAll('.lead-open').forEach(b=>b.onclick=async()=>{state.lead=await api('/leads/'+b.dataset.id);state.templates=await api('/templates');state.media=await api('/media');render();});
document.querySelectorAll('[data-page]').forEach(b=>b.onclick=()=>{state.page=b.dataset.page;render();});
document.getElementById('close')?.addEventListener('click',()=>{state.lead=null;render();});
document.getElementById('new-lead')?.addEventListener('click',async()=>{const first=prompt('First name');if(!first)return;await api('/leads',{method:'POST',body:JSON.stringify({first_name:first,last_name:prompt('Last name')||'',company:prompt('Company')||'',phone:prompt('Phone')||'',email:prompt('Email')||''})});render();});
document.getElementById('save-lead')?.addEventListener('click',async()=>{await api('/leads/'+state.lead.id,{method:'POST',body:JSON.stringify({first_name:document.getElementById('lf').value,last_name:document.getElementById('ll').value,company:document.getElementById('lc').value,email:document.getElementById('le').value,phone:document.getElementById('lp').value,source:document.getElementById('ls').value,status:document.getElementById('lst').value,value:Number(document.getElementById('lv').value||0)})});state.lead=null;render();});
document.getElementById('delete-lead')?.addEventListener('click',async()=>{if(confirm('Delete this lead?')){await api('/leads/'+state.lead.id,{method:'DELETE'});state.lead=null;render();}});
document.getElementById('send-wa')?.addEventListener('click',async()=>{try{const data=await api('/leads/'+state.lead.id+'/whatsapp',{method:'POST',body:JSON.stringify({template_id:Number(document.getElementById('template')?.value||0),media_id:Number(document.getElementById('media')?.value||0),message:document.getElementById('message').value})});window.open(data.url,'_blank','noopener');}catch(e){alert(e.message);}});
document.getElementById('resource-search')?.addEventListener('input',async e=>{const q=e.target.value.trim();if(q.length>1||q===''){state.rows=await api('/resource/'+state.page+'?per_page=100&search='+encodeURIComponent(q));document.getElementById('og-content').innerHTML=resourceTable(state.page,state.rows);bind();}});
document.getElementById('lead-search')?.addEventListener('input',async e=>{const q=e.target.value.trim();if(q.length>1||q===''){state.leads=await api('/leads?per_page=100&search='+encodeURIComponent(q));document.getElementById('og-content').innerHTML=leads();bind();}});
document.getElementById('new-resource')?.addEventListener('click',async()=>{const data=promptResource(state.page);if(data){await api('/resource/'+state.page,{method:'POST',body:JSON.stringify(data)});render();}});
document.querySelectorAll('.resource-delete').forEach(b=>b.onclick=async()=>{if(confirm('Delete this record?')){await api('/resource/'+state.page+'/'+b.dataset.id,{method:'DELETE'});render();}});
}
render();
})();