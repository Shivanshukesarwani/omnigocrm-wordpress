(() => {
const C=window.OmniGoCRMConfig;
const app=document.getElementById('omnigocrm-app');
const state={page:'dashboard',leads:[],lead:null,templates:[],media:[],dash:{}};
const api=async(path,opt={})=>{
  const r=await fetch(C.restUrl+path,{...opt,headers:{'Content-Type':'application/json','X-WP-Nonce':C.nonce,...(opt.headers||{})}});
  const d=await r.json(); if(!r.ok) throw new Error(d.message||'Request failed'); return d;
};
const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
function shell(){
app.innerHTML=`<div class="og-shell"><aside><div class="og-brand"><span>OG</span><b>OmniGoCRM</b></div>
<nav>${[['dashboard','Dashboard'],['leads','Leads'],['contacts','Contacts'],['companies','Companies'],['opportunities','Opportunities'],['tasks','Tasks'],['products','Products']].map(x=>`<button data-page="${x[0]}" class="${state.page===x[0]?'active':''}">${x[1]}</button>`).join('')}</nav>
<div class="og-foot">WordPress Edition<br><small>Premium Hosting Ready</small></div></aside>
<main><header><div><span class="eyebrow">CRM WORKSPACE</span><h1>${state.page[0].toUpperCase()+state.page.slice(1)}</h1></div><div class="top-actions"><span class="status-dot"></span> Connected</div></header><section id="og-content"></section></main></div>`;
document.querySelectorAll('[data-page]').forEach(b=>b.onclick=()=>{state.page=b.dataset.page;render();});
}
async function render(){
shell(); const el=document.getElementById('og-content');
try{
 if(state.page==='dashboard'){state.dash=await api('/dashboard');el.innerHTML=dashboard();}
 else if(state.page==='leads'){state.leads=await api('/leads');el.innerHTML=leads();}
 else {const rows=await api('/resource/'+state.page);el.innerHTML=table(state.page,rows);}
 bind();
}catch(e){el.innerHTML=`<div class="og-error">${esc(e.message)}</div>`;}
}
function dashboard(){
const d=state.dash; return `<div class="cards"><div><small>Total Leads</small><strong>${d.leads}</strong><span>CRM database</span></div><div><small>Contacts</small><strong>${d.contacts}</strong><span>People</span></div><div><small>Open Opportunities</small><strong>${d.opportunities}</strong><span>Pipeline records</span></div><div><small>Pipeline Value</small><strong>₹${Number(d.revenue).toLocaleString()}</strong><span>Opportunity amount</span></div><div><small>Tasks Due</small><strong>${d.tasks}</strong><span>Not completed</span></div></div><div class="panel-grid"><article class="panel"><div class="panel-head"><h2>OmniGoCRM CMS Edition</h2><span>WordPress</span></div><p>Run the CRM directly inside your WordPress dashboard using your existing hosting database. No Node.js, PostgreSQL, Redis or Docker is required.</p><div class="quick"><button data-page="leads">Open Leads</button><a href="https://wa.me/" target="_blank">WhatsApp Click-to-Chat</a></div></article><article class="panel"><h2>Architecture</h2><ul class="checks"><li>WordPress + PHP REST API</li><li>MySQL/MariaDB CRM tables</li><li>React-style JavaScript admin interface</li><li>WP authentication and permissions</li><li>WP-Cron compatible automation</li></ul></article></div>`;
}
function leads(){return `<div class="toolbar"><div><input id="lead-search" placeholder="Search leads..."></div><button id="new-lead">+ New Lead</button></div><div class="panel"><table><thead><tr><th>Name</th><th>Company</th><th>Phone</th><th>Source</th><th>Status</th><th>Value</th><th></th></tr></thead><tbody>${state.leads.map(l=>`<tr><td><b>${esc(l.first_name+' '+l.last_name)}</b><small>${esc(l.email)}</small></td><td>${esc(l.company)}</td><td>${esc(l.phone)}</td><td>${esc(l.source)}</td><td><span class="badge">${esc(l.status)}</span></td><td>₹${Number(l.value).toLocaleString()}</td><td><button class="link lead-open" data-id="${l.id}">Open</button></td></tr>`).join('')}</tbody></table></div>${state.lead?leadModal():''}`;}
function leadModal(){const l=state.lead;return `<div class="modal-back"><div class="modal"><button class="close" id="close">×</button><span class="eyebrow">LEAD</span><h2>${esc(l.first_name+' '+l.last_name)}</h2><div class="lead-meta">${esc(l.company)} · ${esc(l.phone)} · ${esc(l.email)}</div><div class="wa-box"><div><h3>WhatsApp Composer</h3><p>Prepare a Click-to-Chat message for this lead.</p></div><select id="template"><option value="">Custom message</option>${state.templates.map(t=>`<option value="${t.id}">${esc(t.name)}</option>`).join('')}</select><textarea id="message" placeholder="Write your WhatsApp message..."></textarea><select id="media"><option value="0">No media link</option>${state.media.map(m=>`<option value="${m.id}">${esc(m.name)}</option>`).join('')}</select><button id="send-wa" class="wa">Open WhatsApp</button><small>WhatsApp will open with the message prepared. You press Send in WhatsApp.</small></div></div></div>`;}
function table(type,rows){return `<div class="panel"><div class="panel-head"><h2>${esc(type)}</h2><span>${rows.length} records</span></div><table><thead><tr>${Object.keys(rows[0]||{name:''}).slice(0,7).map(k=>`<th>${esc(k.replaceAll('_',' '))}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${Object.keys(rows[0]||r).slice(0,7).map(k=>`<td>${esc(r[k])}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;}
function bind(){
document.querySelectorAll('.lead-open').forEach(b=>b.onclick=async()=>{state.lead=await api('/leads/'+b.dataset.id);state.templates=await api('/templates');state.media=await api('/media');render();});
document.getElementById('close')?.addEventListener('click',()=>{state.lead=null;render();});
document.getElementById('send-wa')?.addEventListener('click',async()=>{try{const data=await api('/leads/'+state.lead.id+'/whatsapp',{method:'POST',body:JSON.stringify({template_id:Number(document.getElementById('template').value||0),media_id:Number(document.getElementById('media').value||0),message:document.getElementById('message').value})});window.open(data.url,'_blank','noopener');}catch(e){alert(e.message);}});
document.getElementById('new-lead')?.addEventListener('click',async()=>{const first=prompt('First name');if(!first)return;const phone=prompt('Phone');await api('/leads',{method:'POST',body:JSON.stringify({first_name:first,phone:phone||''})});render();});
}
render();
})();