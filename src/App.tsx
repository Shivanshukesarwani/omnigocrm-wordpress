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
    await supabase.from('workspace_members').upsert({workspace_id:w.id,user_id:userId,role:w.owner_id===userId?'owner':'member'},{onConflict:'workspace_id,user_id'});
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

function Resource({title,table,workspaceId}:{title:string;table:string;workspaceId:string}){
  const [rows,setRows]=useState<Record<string,unknown>[]>([]); const [loading,setLoading]=useState(true);
  useEffect(()=>{setLoading(true);supabase.from(table as any).select('*').eq('workspace_id',workspaceId).limit(50).then(({data})=>{setRows((data??[]) as Record<string,unknown>[]);setLoading(false)})},[table,workspaceId]);
  return <><Stack direction="row" justifyContent="space-between" alignItems="center" mb={3}><Box><Typography variant="h4" fontWeight={800}>{title}</Typography><Typography color="text.secondary">Live workspace data from Supabase</Typography></Box><Button variant="contained" startIcon={<Add/>}>Add {title.slice(0,-1)}</Button></Stack><Card><CardContent>{loading?<CircularProgress size={24}/>:rows.length===0?<Typography color="text.secondary">No records yet.</Typography>:rows.map((r,i)=><Box key={String(r.id??i)} sx={{py:1.5}}><Typography fontWeight={600}>{String(r.name??r.first_name??r.title??r.original_name??r.id)}</Typography><Divider sx={{mt:1}}/></Box>)}</CardContent></Card></>;
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
    <Route path="/leads" element={<Resource title="Leads" table="leads" workspaceId={workspace.id}/>}/>
    <Route path="/contacts" element={<Resource title="Contacts" table="contacts" workspaceId={workspace.id}/>}/>
    <Route path="/deals" element={<Resource title="Deals" table="deals" workspaceId={workspace.id}/>}/>
    <Route path="/tasks" element={<Resource title="Tasks" table="tasks" workspaceId={workspace.id}/>}/>
    <Route path="/files" element={<Resource title="Files" table="files" workspaceId={workspace.id}/>}/>
    <Route path="/settings" element={<Card><CardContent><Typography variant="h4" fontWeight={800}>Settings</Typography><Typography color="text.secondary" sx={{mt:1}}>Workspace, profile, team and integrations settings will live here.</Typography></CardContent></Card>}/>
    <Route path="*" element={<Navigate to="/"/>}/>
  </Routes></Shell>;
}
export default App;