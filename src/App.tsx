import { useEffect, useState, type ReactNode } from 'react';
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import {
  Alert, Box, Button, Card, CardContent, Chip, CircularProgress, Divider, Grid,
  IconButton, List, ListItemButton, ListItemIcon, ListItemText, MenuItem,
  Select, Stack, TextField, Typography
} from '@mui/material';
import {
  Dashboard, People, Contacts, Handshake, TaskAlt, Folder, Settings,
  Logout, Menu, Add, Login, Business
} from '@mui/icons-material';
import { supabase } from './lib/supabase';

type Workspace = { id:string; name:string; slug:string; owner_id:string };
const nav = [
  ['/', 'Dashboard', Dashboard], ['/leads', 'Leads', People],
  ['/contacts', 'Contacts', Contacts], ['/deals', 'Deals', Handshake],
  ['/tasks', 'Tasks', TaskAlt], ['/files', 'Files', Folder],
  ['/settings', 'Settings', Settings]
] as const;

function Shell({children, workspace, setWorkspace}:{children:ReactNode;workspace:Workspace;setWorkspace:(w:Workspace)=>void}) {
  const loc=useLocation(); const navg=useNavigate(); const [open,setOpen]=useState(true);
  const [workspaces,setWorkspaces]=useState<Workspace[]>([workspace]);
  useEffect(()=>{supabase.from('workspaces').select('id,name,slug,owner_id').then(({data})=>{if(data?.length)setWorkspaces(data as Workspace[])})},[]);
  return <Box className="app-shell">
    <Box className="sidebar" sx={{display:{xs:open?'block':'none',md:'block'}}}>
      <Box className="brand">OmnigoCRM</Box>
      <Box sx={{px:1,mb:2}}>
        <Select fullWidth size="small" value={workspace.id} onChange={e=>{const w=workspaces.find(x=>x.id===e.target.value);if(w)setWorkspace(w)}} sx={{color:'#fff','.MuiOutlinedInput-notchedOutline':{borderColor:'#374151'},'& .MuiSvgIcon-root':{color:'#fff'}}}>
          {workspaces.map(w=><MenuItem value={w.id} key={w.id}>{w.name}</MenuItem>)}
        </Select>
      </Box>
      <List>{nav.map(([p,l,I])=><ListItemButton key={p} className={loc.pathname===p?'nav-item active':'nav-item'} onClick={()=>navg(p)}>
        <ListItemIcon sx={{color:'inherit',minWidth:34}}><I/></ListItemIcon><ListItemText primary={l}/>
      </ListItemButton>)}</List>
      <Box sx={{mt:4,p:1}}><Button fullWidth color="inherit" startIcon={<Logout/>} onClick={()=>supabase.auth.signOut()}>Sign out</Button></Box>
    </Box>
    <Box className="main"><Box className="topbar"><IconButton onClick={()=>setOpen(v=>!v)}><Menu/></IconButton><Stack direction="row" spacing={1} alignItems="center"><Business fontSize="small"/><Typography variant="body2" color="text.secondary">{workspace.name}</Typography><Chip label="Supabase" color="success" size="small"/></Stack></Box><Box className="content">{children}</Box></Box>
  </Box>;
}

function Auth(){
  const [mode,setMode]=useState<'signin'|'signup'>('signin'); const [email,setEmail]=useState(''); const [password,setPassword]=useState(''); const [name,setName]=useState(''); const [loading,setLoading]=useState(false); const [error,setError]=useState(''); const [message,setMessage]=useState('');
  const submit=async()=>{setLoading(true);setError('');setMessage('');
    if(mode==='signin'){const {error}=await supabase.auth.signInWithPassword({email,password});if(error)setError(error.message)}
    else {const {data,error}=await supabase.auth.signUp({email,password,options:{data:{full_name:name}}});if(error)setError(error.message);else if(!data.session)setMessage('Account created. Check your email if confirmation is enabled.')}
    setLoading(false);
  };
  return <Box sx={{minHeight:'100vh',display:'grid',placeItems:'center',p:2,background:'linear-gradient(135deg,#eff6ff,#f8fafc)'}}><Card sx={{width:'100%',maxWidth:440,boxShadow:'0 20px 60px rgba(15,23,42,.12)'}}><CardContent sx={{p:4}}>
    <Typography variant="h4" fontWeight={800} gutterBottom>OmnigoCRM</Typography><Typography color="text.secondary" mb={3}>{mode==='signin'?'Sign in to your workspace':'Create your CRM account'}</Typography>
    <Stack spacing={2}>{mode==='signup'&&<TextField label="Full name" value={name} onChange={e=>setName(e.target.value)} fullWidth/>}<TextField label="Email" type="email" value={email} onChange={e=>setEmail(e.target.value)} fullWidth/><TextField label="Password" type="password" value={password} onChange={e=>setPassword(e.target.value)} fullWidth/>{error&&<Alert severity="error">{error}</Alert>}{message&&<Alert severity="success">{message}</Alert>}<Button variant="contained" size="large" onClick={submit} disabled={loading} startIcon={loading?<CircularProgress size={18}/>:<Login/>}>{mode==='signin'?'Sign in':'Create account'}</Button><Button onClick={()=>setMode(mode==='signin'?'signup':'signin')}>{mode==='signin'?'Create a new account':'I already have an account'}</Button></Stack>
  </CardContent></Card></Box>;
}

async function loadWorkspace(userId:string):Promise<Workspace|null>{
  const {data,error}=await supabase.from('workspaces').select('id,name,slug,owner_id').limit(20);
  if(error) throw error;
  if(data?.length) {
    const w=data[0] as Workspace;
    if (w.owner_id === userId) {
      await supabase.from('workspace_members').upsert({workspace_id:w.id,user_id:userId,role:'owner'},{onConflict:'workspace_id,user_id'});
    }
    return w;
  }
  const name='My Workspace'; const slug='my-workspace-'+userId.slice(0,8);
  const {data:created,error:createError}=await supabase.from('workspaces').insert({name,slug,owner_id:userId}).select('id,name,slug,owner_id').single();
  if(createError) throw createError;
  await supabase.from('workspace_members').insert({workspace_id:created.id,user_id:userId,role:'owner'});
  return created as Workspace;
}

function DashboardPage({workspaceId}:{workspaceId:string}){
  const [stats,setStats]=useState({leads:0,contacts:0,deals:0,tasks:0}); const [error,setError]=useState('');
  useEffect(()=>{let alive=true;(async()=>{const names=['leads','contacts','deals','tasks'] as const;const entries=await Promise.all(names.map(async n=>{const {count,error}=await supabase.from(n).select('*',{count:'exact',head:true}).eq('workspace_id',workspaceId);if(error)throw error;return [n,count??0] as const}));if(alive)setStats(Object.fromEntries(entries) as typeof stats)})().catch(e=>setError(e.message));return()=>{alive=false}},[workspaceId]);
  return <><Typography variant="h4" fontWeight={800} gutterBottom>Dashboard</Typography><Typography color="text.secondary" mb={3}>Your CRM at a glance.</Typography>{error&&<Alert severity="error" sx={{mb:2}}>{error}</Alert>}<Grid container spacing={2}>{Object.entries(stats).map(([k,v])=><Grid size={{xs:12,sm:6,md:3}} key={k}><Card><CardContent><Typography color="text.secondary" textTransform="capitalize">{k}</Typography><Typography variant="h3" fontWeight={800}>{v}</Typography></CardContent></Card></Grid>)}</Grid><Card sx={{mt:3}}><CardContent><Typography variant="h6" fontWeight={700}>Connected architecture</Typography><Typography color="text.secondary" sx={{mt:1}}>Standalone React frontend → Supabase Auth → PostgreSQL + Storage. Workspace isolation is enforced by Supabase RLS.</Typography></CardContent></Card></>;
}

function LeadsPage({workspaceId}:{workspaceId:string}) {
  const [rows,setRows]=useState<Record<string,unknown>[]>([]);
  const [loading,setLoading]=useState(true); const [error,setError]=useState('');
  const [open,setOpen]=useState(false); const [editing,setEditing]=useState<Record<string,unknown>|null>(null);
  const [search,setSearch]=useState(''); const [status,setStatus]=useState('all');
  const [form,setForm]=useState({first_name:'',last_name:'',company_name:'',email:'',mobile:'',whatsapp:'',source:'',status:'new',notes:''});
  const load=async()=>{setLoading(true);const {data,error}=await supabase.from('leads').select('*').eq('workspace_id',workspaceId).order('created_at',{ascending:false});if(error)setError(error.message);else setRows((data??[]) as Record<string,unknown>[]);setLoading(false)};
  useEffect(()=>{load()},[workspaceId]);
  const filtered=rows.filter(r=>{const q=search.toLowerCase();const text=[r.first_name,r.last_name,r.company_name,r.email,r.mobile].join(' ').toLowerCase();return (!q||text.includes(q))&&(status==='all'||r.status===status)});
  const save=async()=>{setError('');const payload={...form,workspace_id:workspaceId,created_by:(await supabase.auth.getUser()).data.user?.id};const result=editing?await supabase.from('leads').update(payload).eq('id',editing.id as string):await supabase.from('leads').insert(payload);if(result.error)setError(result.error.message);else{setOpen(false);setEditing(null);setForm({first_name:'',last_name:'',company_name:'',email:'',mobile:'',whatsapp:'',source:'',status:'new',notes:''});await load()}};
  const remove=async(id:string)=>{if(!confirm('Delete this lead?'))return;const {error}=await supabase.from('leads').delete().eq('id',id);if(error)setError(error.message);else load()};
  const edit=(r:Record<string,unknown>)=>{setEditing(r);setForm({first_name:String(r.first_name??''),last_name:String(r.last_name??''),company_name:String(r.company_name??''),email:String(r.email??''),mobile:String(r.mobile??''),whatsapp:String(r.whatsapp??''),source:String(r.source??''),status:String(r.status??'new'),notes:String(r.notes??'')});setOpen(true)};
  return <><Stack direction={{xs:'column',sm:'row'}} justifyContent="space-between" alignItems={{sm:'center'}} mb={3} gap={2}><Box><Typography variant="h4" fontWeight={800}>Leads</Typography><Typography color="text.secondary">{filtered.length} leads in this workspace</Typography></Box><Button variant="contained" startIcon={<Add/>} onClick={()=>{setEditing(null);setOpen(true)}}>Add Lead</Button></Stack>
  {error&&<Alert severity="error" sx={{mb:2}}>{error}</Alert>}
  <Stack direction={{xs:'column',sm:'row'}} spacing={2} mb={2}><TextField size="small" label="Search leads" value={search} onChange={e=>setSearch(e.target.value)} sx={{minWidth:260}}/><Select size="small" value={status} onChange={e=>setStatus(e.target.value)}><MenuItem value="all">All statuses</MenuItem><MenuItem value="new">New</MenuItem><MenuItem value="contacted">Contacted</MenuItem><MenuItem value="qualified">Qualified</MenuItem><MenuItem value="converted">Converted</MenuItem><MenuItem value="lost">Lost</MenuItem></Select></Stack>
  <Card><CardContent>{loading?<CircularProgress size={24}/>:filtered.length===0?<Typography color="text.secondary">No leads found.</Typography>:filtered.map(r=><Box key={String(r.id)} sx={{py:2}}><Stack direction={{xs:'column',sm:'row'}} justifyContent="space-between" gap={2}><Box><Typography fontWeight={700}>{[r.first_name,r.last_name].filter(Boolean).join(' ')||'Unnamed lead'}</Typography><Typography variant="body2" color="text.secondary">{[r.company_name,r.email,r.mobile].filter(Boolean).join(' · ')}</Typography>{r.notes&&<Typography variant="body2" sx={{mt:0.5}}>{String(r.notes)}</Typography>}</Box><Stack direction="row" alignItems="center" spacing={1}><Chip size="small" label={String(r.status)} /><Button size="small" onClick={()=>edit(r)}>Edit</Button><Button size="small" color="error" onClick={()=>remove(String(r.id))}>Delete</Button>{r.mobile&&<Button size="small" href={`https://wa.me/${String(r.mobile).replace(/\\D/g,'')}`} target="_blank">WhatsApp</Button>}</Stack></Stack><Divider sx={{mt:2}}/></Box>)}</CardContent></Card>
  {open&&<Box sx={{position:'fixed',inset:0,zIndex:1300,background:'rgba(15,23,42,.45)',display:'grid',placeItems:'center',p:2}}><Card sx={{width:'100%',maxWidth:650,maxHeight:'90vh',overflow:'auto'}}><CardContent><Stack direction="row" justifyContent="space-between" mb={2}><Typography variant="h5" fontWeight={800}>{editing?'Edit Lead':'New Lead'}</Typography><Button onClick={()=>setOpen(false)}>Close</Button></Stack><Grid container spacing={2}>{[['first_name','First name'],['last_name','Last name'],['company_name','Company'],['email','Email'],['mobile','Mobile'],['whatsapp','WhatsApp'],['source','Source']].map(([k,l])=><Grid size={{xs:12,sm:6}} key={k}><TextField fullWidth label={l} value={(form as any)[k]} onChange={e=>setForm({...form,[k]:e.target.value})}/></Grid>)}<Grid size={{xs:12,sm:6}}><Select fullWidth value={form.status} onChange={e=>setForm({...form,status:e.target.value})}>{['new','contacted','qualified','converted','lost'].map(s=><MenuItem key={s} value={s}>{s}</MenuItem>)}</Select></Grid><Grid size={{xs:12}}><TextField fullWidth multiline minRows={3} label="Notes" value={form.notes} onChange={e=>setForm({...form,notes:e.target.value})}/></Grid></Grid><Stack direction="row" justifyContent="flex-end" spacing={1} mt={3}><Button onClick={()=>setOpen(false)}>Cancel</Button><Button variant="contained" onClick={save}>Save Lead</Button></Stack></CardContent></Card></Box>}</>;
}


function ContactsPage({workspaceId}:{workspaceId:string}) {
 const [rows,setRows]=useState<Record<string,unknown>[]>([]),[open,setOpen]=useState(false),[editing,setEditing]=useState<Record<string,unknown>|null>(null),[error,setError]=useState('');
 const [form,setForm]=useState({first_name:'',last_name:'',company_name:'',email:'',mobile:'',designation:''});
 const load=async()=>{const {data,error}=await supabase.from('contacts').select('*').eq('workspace_id',workspaceId).order('created_at',{ascending:false});if(error)setError(error.message);else setRows((data??[]) as Record<string,unknown>[])};useEffect(()=>{load()},[workspaceId]);
 const save=async()=>{const p={...form,workspace_id:workspaceId,owner_id:(await supabase.auth.getUser()).data.user?.id};const q=editing?supabase.from('contacts').update(p).eq('id',editing.id as string):supabase.from('contacts').insert(p);const {error}=await q;if(error)setError(error.message);else{setOpen(false);setEditing(null);load()}};
 const edit=(r:Record<string,unknown>)=>{setEditing(r);setForm({first_name:String(r.first_name??''),last_name:String(r.last_name??''),company_name:String(r.company_name??''),email:String(r.email??''),mobile:String(r.mobile??''),designation:String(r.designation??'')});setOpen(true)};
 return <><Stack direction="row" justifyContent="space-between" mb={3}><Box><Typography variant="h4" fontWeight={800}>Contacts</Typography><Typography color="text.secondary">{rows.length} contacts</Typography></Box><Button variant="contained" startIcon={<Add/>} onClick={()=>{setEditing(null);setForm({first_name:'',last_name:'',company_name:'',email:'',mobile:'',designation:''});setOpen(true)}}>Add Contact</Button></Stack>{error&&<Alert severity="error">{error}</Alert>}<Card><CardContent>{rows.length===0?<Typography color="text.secondary">No contacts yet.</Typography>:rows.map(r=><Box key={String(r.id)} sx={{py:1.5}}><Stack direction={{xs:'column',sm:'row'}} justifyContent="space-between"><Box><Typography fontWeight={700}>{[r.first_name,r.last_name].filter(Boolean).join(' ')||'Unnamed contact'}</Typography><Typography variant="body2" color="text.secondary">{[r.company_name,r.designation,r.email,r.mobile].filter(Boolean).join(' · ')}</Typography></Box><Stack direction="row"><Button size="small" onClick={()=>edit(r)}>Edit</Button><Button size="small" color="error" onClick={async()=>{if(confirm('Delete this contact?')){await supabase.from('contacts').delete().eq('id',String(r.id));load()}}}>Delete</Button></Stack></Stack><Divider sx={{mt:1}}/></Box>)}</CardContent></Card>
 {open&&<Box sx={{position:'fixed',inset:0,zIndex:1300,background:'rgba(15,23,42,.45)',display:'grid',placeItems:'center',p:2}}><Card sx={{width:'100%',maxWidth:650}}><CardContent><Typography variant="h5" fontWeight={800} mb={2}>{editing?'Edit Contact':'New Contact'}</Typography><Grid container spacing={2}>{[['first_name','First name'],['last_name','Last name'],['company_name','Company'],['email','Email'],['mobile','Mobile'],['designation','Designation']].map(([k,l])=><Grid size={{xs:12,sm:6}} key={k}><TextField fullWidth label={l} value={(form as any)[k]} onChange={e=>setForm({...form,[k]:e.target.value})}/></Grid>)}</Grid><Stack direction="row" justifyContent="flex-end" spacing={1} mt={3}><Button onClick={()=>setOpen(false)}>Cancel</Button><Button variant="contained" onClick={save}>Save</Button></Stack></CardContent></Card></Box>}</>;
}
function DealsPage({workspaceId}:{workspaceId:string}) {
 const stages=['new','qualified','proposal','negotiation','won','lost'];const [rows,setRows]=useState<Record<string,unknown>[]>([]),[open,setOpen]=useState(false),[editing,setEditing]=useState<Record<string,unknown>|null>(null),[error,setError]=useState('');
 const [form,setForm]=useState({name:'',value:'0',stage:'new',probability:'0',expected_close_date:''});
 const load=async()=>{const {data,error}=await supabase.from('deals').select('*').eq('workspace_id',workspaceId).order('created_at',{ascending:false});if(error)setError(error.message);else setRows((data??[]) as Record<string,unknown>[])};useEffect(()=>{load()},[workspaceId]);
 const save=async()=>{const p={...form,value:Number(form.value)||0,probability:Number(form.probability)||0,workspace_id:workspaceId,owner_id:(await supabase.auth.getUser()).data.user?.id,expected_close_date:form.expected_close_date||null};const q=editing?supabase.from('deals').update(p).eq('id',editing.id as string):supabase.from('deals').insert(p);const {error}=await q;if(error)setError(error.message);else{setOpen(false);setEditing(null);load()}};
 return <><Stack direction="row" justifyContent="space-between" mb={3}><Box><Typography variant="h4" fontWeight={800}>Deals</Typography><Typography color="text.secondary">Pipeline</Typography></Box><Button variant="contained" startIcon={<Add/>} onClick={()=>{setEditing(null);setForm({name:'',value:'0',stage:'new',probability:'0',expected_close_date:''});setOpen(true)}}>Add Deal</Button></Stack>{error&&<Alert severity="error">{error}</Alert>}<Grid container spacing={2}>{stages.map(s=><Grid size={{xs:12,sm:6,md:4}} key={s}><Card><CardContent><Stack direction="row" justifyContent="space-between"><Typography fontWeight={800} textTransform="capitalize">{s}</Typography><Chip label={rows.filter(r=>r.stage===s).length} size="small"/></Stack>{rows.filter(r=>r.stage===s).map(r=><Box key={String(r.id)} sx={{mt:1,p:1.5,border:1,borderColor:'divider',borderRadius:2}}><Typography fontWeight={700}>{String(r.name)}</Typography><Typography variant="body2">₹{Number(r.value||0).toLocaleString('en-IN')} · {String(r.probability)}%</Typography><Button size="small" onClick={()=>{setEditing(r);setForm({name:String(r.name),value:String(r.value),stage:String(r.stage),probability:String(r.probability),expected_close_date:String(r.expected_close_date??'')});setOpen(true)}}>Edit</Button><Button size="small" color="error" onClick={async()=>{if(confirm('Delete this deal?')){await supabase.from('deals').delete().eq('id',String(r.id));load()}}}>Delete</Button></Box>)}</CardContent></Card></Grid>)}</Grid>
 {open&&<Box sx={{position:'fixed',inset:0,zIndex:1300,background:'rgba(15,23,42,.45)',display:'grid',placeItems:'center',p:2}}><Card sx={{width:'100%',maxWidth:600}}><CardContent><Typography variant="h5" fontWeight={800} mb={2}>{editing?'Edit Deal':'New Deal'}</Typography><Stack spacing={2}><TextField label="Deal name" value={form.name} onChange={e=>setForm({...form,name:e.target.value})}/><TextField label="Value" type="number" value={form.value} onChange={e=>setForm({...form,value:e.target.value})}/><TextField label="Probability %" type="number" value={form.probability} onChange={e=>setForm({...form,probability:e.target.value})}/><Select value={form.stage} onChange={e=>setForm({...form,stage:e.target.value})}>{stages.map(s=><MenuItem key={s} value={s}>{s}</MenuItem>)}</Select><TextField label="Expected close date" type="date" InputLabelProps={{shrink:true}} value={form.expected_close_date} onChange={e=>setForm({...form,expected_close_date:e.target.value})}/><Stack direction="row" justifyContent="flex-end"><Button onClick={()=>setOpen(false)}>Cancel</Button><Button variant="contained" onClick={save}>Save Deal</Button></Stack></Stack></CardContent></Card></Box>}</>;
}

function App(){
  const [session,setSession]=useState<any>(undefined); const [workspace,setWorkspace]=useState<Workspace|null>(null); const [error,setError]=useState('');
  useEffect(()=>{supabase.auth.getSession().then(({data})=>setSession(data.session));const {data}=supabase.auth.onAuthStateChange((_e,s)=>setSession(s));return()=>data.subscription.unsubscribe()},[]);
  useEffect(()=>{if(session?.user){setWorkspace(null);setError('');loadWorkspace(session.user.id).then(setWorkspace).catch(e=>setError(e.message))}},[session?.user?.id]);
  if(session===undefined)return <Box sx={{minHeight:'100vh',display:'grid',placeItems:'center'}}><CircularProgress/></Box>;
  if(!session)return <Auth/>;
  if(error)return <Box sx={{p:4}}><Alert severity="error">{error}</Alert></Box>;
  if(!workspace)return <Box sx={{minHeight:'100vh',display:'grid',placeItems:'center'}}><CircularProgress/></Box>;
  return <Shell workspace={workspace} setWorkspace={setWorkspace}><Routes>
    <Route path="/" element={<DashboardPage workspaceId={workspace.id}/>}/>
    <Route path="/leads" element={<LeadsPage workspaceId={workspace.id}/>}/>
    <Route path="/contacts" element={<ContactsPage workspaceId={workspace.id}/>}/>
    <Route path="/deals" element={<DealsPage workspaceId={workspace.id}/>}/>
    <Route path="/tasks" element={<Resource title="Tasks" table="tasks" workspaceId={workspace.id}/>}/>
    <Route path="/files" element={<Resource title="Files" table="files" workspaceId={workspace.id}/>}/>
    <Route path="/settings" element={<Card><CardContent><Typography variant="h4" fontWeight={800}>Settings</Typography><Typography color="text.secondary" sx={{mt:1}}>Workspace, profile, team and integrations settings will live here.</Typography></CardContent></Card>}/>
    <Route path="*" element={<Navigate to="/"/>}/>
  </Routes></Shell>;
}
export default App;