// Registration email-OTP UI. The server is the only authority: nothing is marked verified until the API says so.
(()=>{const f=document.getElementById('reg');if(!f)return;const el=n=>f.elements[n],API=f.dataset.base+'api/',tok=el('csrf').value,sub=document.getElementById('regbtn'),b=f.querySelector('.vf');
let verified=f.dataset.ve||'',exp=0,res=0,tm=null;
const q=s=>b.querySelector(s),msg=document.getElementById('email-msg'),otp=q('.otp'),code=q('.otp input'),send=q('[data-act=send]'),ver=q('[data-act=verify]'),rs=q('[data-act=resend]'),ok=q('.vok'),texp=q('.t-exp');
const key=()=>el('email').value.trim().toLowerCase(),mmss=s=>Math.floor(s/60)+':'+String(s%60).padStart(2,'0'),gate=()=>{sub.disabled=!(verified&&verified===key())};
const say=(t,bad)=>{msg.textContent=t||'';msg.className='msg'+(t?(bad?' err':' good'):'')},busy=(x,on)=>{x.disabled=on;x.classList.toggle('loading',on);x.setAttribute('aria-busy',on)},stop=()=>{clearInterval(tm);tm=null};
async function call(ep,extra){const d=new FormData();d.append('csrf',tok);d.append('channel','email');d.append('email',el('email').value);for(const k in extra)d.append(k,extra[k]);
 try{const r=await fetch(API+ep,{method:'POST',body:d,credentials:'same-origin',headers:{Accept:'application/json'}});return await r.json().catch(()=>({ok:false,error:'Unexpected server response. Please try again.'}))}
 catch(e){return{ok:false,error:'Network problem. Check your connection and try again.'}}}
const tick=()=>{const n=Math.round(Date.now()/1000),e=exp-n,r=res-n;if(e>0)texp.textContent='Code expires in '+mmss(e)+'.';else{texp.textContent='Code expired. Request a new one.';ver.disabled=true}
 rs.disabled=r>0;rs.textContent=r>0?'Resend code ('+r+'s)':'Resend code';if(e<=0&&r<=0)stop()};
const start=(e,r)=>{const n=Math.round(Date.now()/1000);exp=n+e;res=n+r;ver.disabled=false;otp.hidden=false;stop();tick();tm=setInterval(tick,1000)};
const done=()=>{verified=key();ok.hidden=false;otp.hidden=true;send.hidden=true;stop();say('');gate()},reset=()=>{verified='';ok.hidden=true;send.hidden=false;otp.hidden=true;stop();gate()};
async function request(btn){busy(btn,true);say('Sending code…');const r=await call('send_otp.php');busy(btn,false);
 if(r.ok){say(r.message+(r.dev_code?' Your code: '+r.dev_code:''));start(r.expires_in,r.resend_in);code.value='';code.focus()}
 else{say((r.error||'Could not send the code.')+(r.debug?' ['+r.debug+']':''),true);if(r.retry_in&&r.retry_in<=60)start(r.expires_in||0,r.retry_in)}}
send.onclick=()=>request(send);rs.onclick=()=>request(rs);
ver.onclick=async()=>{busy(ver,true);const r=await call('verify_otp.php',{code:code.value.trim()});busy(ver,false);
 if(r.ok&&r.verified){done();say(r.message)}else{say(r.error||'Verification failed.',true);if(r.attempts_left===0||/expired|No active/.test(r.error||''))ver.disabled=true;else code.focus()}};
code.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();ver.click()}});code.addEventListener('input',()=>{code.value=code.value.replace(/\D/g,'')});
q('[data-act=change]').onclick=()=>{reset();say('');el('email').focus()};
el('email').addEventListener('input',()=>{if(verified&&verified!==key()){reset();say('Email changed. Request a new code to verify it.',true)}else if(!verified&&!otp.hidden){otp.hidden=true;stop();say('Email changed. Request a new code.',true)}gate()});
f.addEventListener('submit',e=>{if(sub.disabled){e.preventDefault();return}sub.classList.add('loading');sub.setAttribute('aria-busy','true')});
if(verified&&verified===key())done();gate();})();
