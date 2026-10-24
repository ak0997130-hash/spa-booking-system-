"""End-to-end tests for email-OTP registration. Needs MySQL, PHP and tests/mock_providers.py (see OTP_SETUP.md)."""
import urllib.request,urllib.parse,http.cookiejar,re,json,time,subprocess,threading,sys,os
PORT=int(os.environ.get('PORT',8080));B='http://127.0.0.1:%d/servicehub/'%PORT;OUT='/tmp/otp_outbox.jsonl'
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*a,**k):return None
def sess():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedir)
def go(o,path,data=None,base=None,hdr=None):
    d=urllib.parse.urlencode(data).encode() if isinstance(data,dict) else data
    try:r=o.open(urllib.request.Request((base or B)+path,d,hdr or {}),timeout=20);return r.status,r.read().decode(),None
    except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.headers.get('Location')
def csrf(o,page='register.php'):return re.search(r'name="csrf" value="(\w+)"',go(o,page)[1]).group(1)
def api(o,ep,data,tok=None):
    s,h,_=go(o,'api/'+ep,dict(data,csrf=tok) if tok else data)
    try:return s,json.loads(h)
    except Exception:return s,{'raw':h[:200]}
def decp(v,ctx):
    code='require "includes/env.php";require "includes/crypto.php";echo dec($argv[1],$argv[2]);'
    return subprocess.run(['php','-r',code,v,ctx],cwd='/mnt/user-data/outputs/servicehub',capture_output=True,text=True,env=dict(os.environ)).stdout
def sql(q):return subprocess.run(['mysql','-uroot','-N','-B','servicehub','-e',q],capture_output=True,text=True).stdout.strip()
def outbox():return [json.loads(l) for l in open(OUT)] if os.path.exists(OUT) else []
def code_for(to,n=1,wait=4):
    t=time.time()
    while time.time()-t<wait:
        m=[x for x in outbox() if x['to']==to]
        if len(m)>=n:return m[n-1]['code']
        time.sleep(.1)
def ok(n,c,i=''):print(('PASS ' if c else 'FAIL ')+n,'' if c else '  <- '+str(i)[:300]);return c
N=[0]
def uniq():N[0]+=1;return '%d%d'%(int(time.time())%100000,N[0])
def fresh():o=sess();return o,csrf(o)
def send(o,t,em):return api(o,'send_otp.php',{'channel':'email','email':em},t)
def verify(o,t,em,code):return api(o,'verify_otp.php',{'channel':'email','email':em,'code':code},t)
def pn():return '98%08d'%(int(uniq())*37%100000000)
def verified(o,t,em):send(o,t,em);return verify(o,t,em,code_for(em))[1].get('verified')
def reg(o,t,em,ph,name='Asha Rao',pw='Secret123',cf=None,cc='+91'):return go(o,'register.php',{'csrf':t,'name':name,'email':em,'cc':cc,'phone':ph,'password':pw,'confirm':cf or pw})
import email,glob
from email.header import decode_header,make_header
MO='/tmp/mail_out'
def emls(to):
    r=[]
    for f in sorted(glob.glob(MO+'/*.eml'),key=os.path.getmtime):
        m=email.message_from_bytes(open(f,'rb').read())
        if to in str(m['To'] or ''):m.fname=f;r.append(m)
    return r
def eml_for(to,n=1,wait=4):
    t=time.time()
    while time.time()-t<wait:
        r=emls(to)
        if len(r)>=n:return r[n-1]
        time.sleep(.1)
def eml_code(m):
    for p in m.walk():
        if p.get_content_type()=='text/plain':
            x=re.search(r'code is[^0-9]{0,40}(\d{6})',p.get_payload(decode=True).decode());return x and x.group(1)
def mverified(o,t,em):send(o,t,em);m=eml_for(em);return bool(m) and verify(o,t,em,eml_code(m))[1].get('verified')
S=sys.argv[1] if len(sys.argv)>1 else 'main'
if S=='main':
    o,t=fresh();s,h,_=go(o,'register.php')
    ok('A1 form: name, email OTP buttons, plain mobile field, passwords, Register',all(x in h for x in ['name="name"','name="email"','name="cc"','name="phone"','name="password"','name="confirm"','Send Email OTP','Verify Email','Register Account']))
    ok('A2 no phone OTP controls anywhere',not any(x in h for x in ['Send Phone OTP','Verify Phone','phone-otp','phone-msg']) and 'SMS' not in h)
    ok('A3 script served',go(o,'assets/js/register.js')[0]==200 and 'phone' not in go(o,'assets/js/register.js')[1].lower())
    em='b%s@example.com'%uniq();s,r=send(o,t,em)
    ok('B1 email OTP send ok, expiry 300s, cooldown 60s',s==200 and r.get('expires_in')==300 and r.get('resend_in')==60,(s,r))
    ce=code_for(em);ok('B2 delivered over SMTP with a 6-digit code',bool(ce and re.fullmatch(r'\d{6}',ce)))
    ok('B3 response does not expose the code',ce not in json.dumps(r))
    row=sql("SELECT otp_hash,status,TIMESTAMPDIFF(SECOND,NOW(),expires_at) FROM otp_challenges WHERE destination='%s'"%em).split('\t')
    ok('B4 stored as 64-hex hash, pending, ~5 min expiry',len(row[0])==64 and row[0]!=ce and row[1]=='pending' and 290<=int(row[2])<=300,row)
    ok('B5 code appears nowhere in the DB',sql("SELECT COUNT(*) FROM otp_challenges WHERE CONCAT_WS('|',id,destination,otp_hash,bind_key)LIKE '%%%s%%'"%ce)=='0')
    s,r=send(o,t,em);ok('B6 immediate resend blocked by cooldown',s==429 and 0<r.get('retry_in',0)<=60,(s,r))
    bad='000000' if ce!='000000' else '111111';s,r=verify(o,t,em,bad);ok('B7 wrong code rejected, 4 attempts left',s==422 and r.get('attempts_left')==4,(s,r))
    ok('B8 malformed code rejected',verify(o,t,em,'12ab')[0]==422)
    s,r=verify(o,t,'other@example.com',ce);ok('B9 code is bound to its address',s==422 and not r.get('verified'))
    s,r=verify(o,t,em,ce);ok('B10 correct code verifies',s==200 and r.get('verified') is True,(s,r))
    s,r=verify(o,t,em,ce);ok('B11 reused code rejected',not r.get('verified'),(s,r))
    ok('B12 phone channel no longer exists',api(o,'send_otp.php',{'channel':'phone','cc':'+91','phone':'9876543210'},t)[0]==400 and api(o,'verify_otp.php',{'channel':'phone','code':'123456'},t)[0]==400)
    o2,t2=fresh();em2='c%s@example.com'%uniq();send(o2,t2,em2);c2=code_for(em2);sql("UPDATE otp_challenges SET expires_at=NOW()-INTERVAL 1 SECOND WHERE destination='%s'"%em2);s,r=verify(o2,t2,em2,c2)
    ok('C1 expired code rejected',s==410 and 'expired' in r.get('error',''),(s,r))
    o3,t3=fresh();em3='d%s@example.com'%uniq();send(o3,t3,em3);c3=code_for(em3);bad='000000' if c3!='000000' else '111111';rs=[verify(o3,t3,em3,bad) for _ in range(5)]
    ok('D1 5th wrong attempt locks',rs[-1][1].get('attempts_left')==0 and 'Too many' in rs[-1][1].get('error',''),rs[-1])
    ok('D2 correct code no longer works after lock',not verify(o3,t3,em3,c3)[1].get('verified'));ok('D3 status is locked',sql("SELECT status FROM otp_challenges WHERE destination='%s'"%em3)=='locked')
    o4,t4=fresh();em4='e%s@example.com'%uniq();send(o4,t4,em4);c4a=code_for(em4);sql("UPDATE otp_challenges SET resend_available_at=NOW()-INTERVAL 1 SECOND WHERE destination='%s'"%em4)
    s,r=send(o4,t4,em4);c4b=code_for(em4,2);ok('E1 resend allowed after cooldown',s==200)
    if c4a!=c4b:ok('E2 old code dead after resend',not verify(o4,t4,em4,c4a)[1].get('verified'))
    ok('E3 new code works',verify(o4,t4,em4,c4b)[1].get('verified') is True)
    # registration
    base=sql("SELECT COUNT(*) FROM users");o7,t7=fresh();em7='g%s@example.com'%uniq();ph7=pn()
    s,h,_=reg(o7,t7,em7,ph7);ok('G1 registration without email verification refused',s==200 and 'Verify your email' in h and sql("SELECT COUNT(*) FROM users")==base)
    verified(o7,t7,em7)
    s,h,_=reg(o7,t7,em7,ph7,pw='weak',cf='weak');ok('G2 weak password: field error, inputs kept (incl. mobile)',s==200 and 'Use 8+ characters' in h and 'value="%s"'%em7 in h and 'value="Asha Rao"' in h and 'value="%s"'%ph7 in h)
    ok('G3 mismatch flagged','Passwords do not match' in reg(o7,t7,em7,ph7,cf='Different1')[1]);ok('G4 invalid email flagged','Enter a valid email' in reg(o7,t7,'not-an-email',ph7)[1])
    ok('G5 invalid / missing mobile flagged','valid mobile number' in reg(o7,t7,em7,'12345')[1] and 'valid mobile number' in reg(o7,t7,em7,'')[1])
    s,h,l=reg(o7,t7,em7.upper(),ph7);ok('G6 registers with ONLY email verified (mobile needs no code); redirect to login',s==302 and 'login.php' in (l or ''),(s,l,h[-150:]))
    row=sql("SELECT is_verified,email_verified_at IS NOT NULL,phone,LEFT(password,7),email FROM users WHERE email='%s'"%em7).split('\t')
    ok('G7 account verified, mobile stored ENCRYPTED (E.164 once decrypted), email lowercased, password hashed',row[0]=='1' and row[1]=='1' and row[2].startswith('enc1:') and '+91'+ph7 not in row[2] and decp(row[2],'users.phone')=='+91'+ph7 and (row[3].startswith('$argon2') or row[3].startswith('$2y$')) and row[4]==em7,row)
    ok('G8 challenge consumed',sql("SELECT COUNT(*) FROM otp_challenges WHERE destination='%s' AND status='consumed'"%em7)=='1')
    reg(o7,t7,em7,ph7);ok('G9 replay cannot create a second account',sql("SELECT COUNT(*) FROM users WHERE email='%s'"%em7)=='1')
    tl=csrf(o7,'login.php');s,h,l=go(o7,'login.php',{'csrf':tl,'email':em7,'password':'Secret123'});ok('G10 new user logs in and reaches dashboard',s==302 and 'dashboard' in l and go(o7,'user/dashboard.php')[0]==200)
    o8,t8=fresh();em8='h%s@example.com'%uniq();verified(o8,t8,em8);res=[];th=[threading.Thread(target=lambda:res.append(reg(o8,t8,em8,ph7)[0])) for _ in range(2)];[x.start() for x in th];[x.join() for x in th]
    ok('H1 simultaneous double submit creates exactly one account (same mobile as another customer is allowed)',sql("SELECT COUNT(*) FROM users WHERE email='%s'"%em8)=='1' and 500 not in res,res)
    o9,t9=fresh();ea='i%s@example.com'%uniq();eb='j%s@example.com'%uniq();verified(o9,t9,ea)
    reg(o9,t9,eb,pn());ok('I1 changed (unverified) email refused',sql("SELECT COUNT(*) FROM users WHERE email='%s'"%eb)=='0')
    sql("UPDATE otp_challenges SET resend_available_at=NOW()-INTERVAL 1 SECOND WHERE bind_key IN(SELECT bind_key FROM (SELECT bind_key FROM otp_challenges WHERE destination='%s') t)"%ea);send(o9,t9,eb)
    reg(o9,t9,ea,pn());ok('I2 requesting a code for a new address cancels the old verification',sql("SELECT COUNT(*) FROM users WHERE email='%s'"%ea)=='0')
    o10,t10=fresh();reg(o10,t10,ea,pn());ok('J1 another session cannot reuse someone else\'s verification',sql("SELECT COUNT(*) FROM users WHERE email='%s'"%ea)=='0')
    o11,t11=fresh();n0=len(outbox());s,r=send(o11,t11,em7);o12,t12=fresh();s2,r2=send(o12,t12,'new%s@example.com'%uniq());time.sleep(.6)
    ok('K1 existing email: same response as a new one (no enumeration)',s==s2==200 and set(r)==set(r2) and r['message']==r2['message'])
    ok('K2 nothing is sent to the existing account holder',not [x for x in outbox()[n0:] if x['to']==em7]);ok('K3 a taken address can never be verified',not verify(o11,t11,em7,'123456')[1].get('verified'))
    o14=sess();go(o14,'register.php');s,r=api(o14,'send_otp.php',{'channel':'email','email':'x@example.com'});ok('L1 API without CSRF -> 419 JSON',s==419 and r.get('ok') is False)
    em10='k%s@example.com'%uniq();go(o14,'register.php',{'name':'x','email':em10,'phone':'9876543210','password':'Secret123','confirm':'Secret123'});ok('L2 registration without CSRF creates nothing',sql("SELECT COUNT(*) FROM users WHERE email='%s'"%em10)=='0')
    ok('L3 GET to API -> 405',go(o14,'api/send_otp.php')[0]==405)
    em='m%s@example.com'%uniq();rs=[]
    for i in range(6):oo,tt=fresh();rs.append(send(oo,tt,em)[0])
    ok('M1 email destination limit: 5 requests/hour then 429',rs==[200]*5+[429],rs)
    lo,lt=fresh();ok('N1 legacy (pre-OTP) customer can still log in',go(lo,'login.php',{'csrf':csrf(lo,'login.php'),'email':'old1@x.com','password':'oldpass1'})[0]==302)
    s,h,_=go(lo,'user/dashboard.php');ok('N2 legacy dashboard + booking intact',s==200 and 'Home Cleaning' in h)
    sql("INSERT INTO users(name,email,phone,password,is_verified) SELECT 'Unv','unv@x.com',NULL,password,0 FROM users WHERE email='old1@x.com'");uo=sess()
    s,h,l=go(uo,'login.php',{'csrf':csrf(uo,'login.php'),'email':'unv@x.com','password':'oldpass1'});ok('N3 unverified account cannot log in','email verification' in h and s==200)
    sql("UPDATE users SET is_verified=0 WHERE email='old1@x.com'");ok('N4 unverified session cannot reach dashboard',go(lo,'user/dashboard.php')[0]==302);sql("UPDATE users SET is_verified=1 WHERE email='old1@x.com'")
    ao=sess();s,h,l=go(ao,'admin/login.php',{'csrf':csrf(ao,'admin/login.php'),'email':'admin@servicehub.com','password':'password'});ok('N5 admin login still works (development mode)',s==302 and 'admin/index' in l)
    ok('N6 admin pages render',all(go(ao,'admin/%s.php'%p)[0]==200 for p in ['index','bookings','services','users','settings','verification']));ok('N7 home + login render',go(sess(),'')[0]==200 and go(sess(),'login.php')[0]==200)
    log=open('/tmp/php_main.log').read();codes=[x['code'] for x in outbox() if x['code']]
    ok('O1 server log contains no OTP codes',not any(re.search(r'(?<!\d)%s(?!\d)'%c,log) for c in codes[:40]));ok('O2 log never contains the secret',os.environ.get('APP_SECRET','x'*8) not in log)
elif S=='smtpdown':   # port 8081: SMTP unreachable, tight IP limit
    o,t=fresh();s,r=send(o,t,'p%s@example.com'%uniq());ok('P1 unreachable SMTP -> generic 502 (+ localhost-only technical detail)',s==502 and r.get('error','').startswith('We could not send') and 'debug' in r,(s,r))
    s,h,_=go(o,'api/send_otp.php',{'channel':'email','email':'q%s@example.com'%uniq(),'csrf':csrf(o)},hdr={'Host':'spa.example.com'});j=json.loads(h);ok('P2 off-localhost callers get no technical detail',s==502 and 'debug' not in j and 'hint' not in j,(s,j))
    sql("DELETE FROM rate_limits");rs=[]
    for i in range(5):oo,tt=fresh();rs.append(send(oo,tt,'z%s@example.com'%uniq())[0])
    ok('P3 per-IP limit (3/hour on this server) -> 429',rs[:3]==[502,502,502] and 429 in rs[3:],rs)
elif S=='unconfigured':
    o,t=fresh();s,r=send(o,t,'u%s@example.com'%uniq());ok('Q1 email not configured -> honest 503 (+ localhost-only hint)',s==503 and 'not set up' in r.get('error','') and 'Verification' in r.get('hint',''),(s,r))
    s,h,_=go(o,'register.php');ok('Q2 register page tells the local owner what to do',s==200 and 'Setup needed' in h and 'admin/verification.php' in h)
    s,h,_=go(sess(),'register.php',hdr={'Host':'spa.example.com'});ok('Q3 ...and shows that notice to nobody else','Setup needed' not in h)
elif S=='nosecret':
    o,t=fresh();s,r=send(o,t,'u@example.com');lf='/mnt/user-data/outputs/servicehub/includes/local.settings.php';raw=open(lf).read() if os.path.exists(lf) else ''
    ok('R1 missing APP_SECRET is generated automatically',s==503 and 'APP_SECRET' not in r.get('error','') and raw.startswith('<?php exit; ?>') and bool(re.search(r'"APP_SECRET": "[0-9a-f]{64}"',raw)),(s,r,raw[:60]))
    go(o,'register.php',{'csrf':t,'name':'Zed Zed','email':'zed@example.com','cc':'+91','phone':'9876543212','password':'Secret123','confirm':'Secret123'});ok('R2 registration still refuses without verification',sql("SELECT COUNT(*) FROM users WHERE email='zed@example.com'")=='0')
    s,h,_=go(sess(),'includes/local.settings.php');ok('R3 settings file shows nothing if requested over the web',h.strip()=='' and 'APP_SECRET' not in h)
elif S=='devmode':
    o,t=fresh();em='dv%s@example.com'%uniq();ph=pn();n0=len(outbox());s,r=send(o,t,em)
    ok('T1 test mode shows the code on screen, sends nothing',s==200 and re.fullmatch(r'\d{6}',r.get('dev_code','')) and len(outbox())==n0 and 'Test mode' in r['message'],(s,r))
    ok('T2 cooldown still applies',send(o,t,em)[0]==429);bad='000000' if r['dev_code']!='000000' else '111111';ok('T3 wrong code still rejected',not verify(o,t,em,bad)[1].get('verified'))
    ok('T4 correct code verifies',verify(o,t,em,r['dev_code'])[1].get('verified') is True)
    s,h,l=reg(o,t,em,ph);ok('T5 full registration works with no email provider at all',s==302 and sql("SELECT is_verified FROM users WHERE email='%s'"%em)=='1',(s,h[-150:]))
    o2,t2=fresh();s,rt=send(o2,t2,em);ok('T6 already-registered address gets no code, even in test mode',s==200 and 'dev_code' not in rt)
    ok('T7 no "setup needed" notice while test mode is on','Setup needed' not in go(sess(),'register.php')[1])
elif S=='adminverif':
    lf='/mnt/user-data/outputs/servicehub/includes/local.settings.php';a=sess();go(a,'admin/login.php',{'csrf':csrf(a,'admin/login.php'),'email':'admin@servicehub.com','password':'password'})
    s,h,_=go(a,'admin/verification.php');ok('V1 Verification page renders (email only)',s==200 and 'Send a test email' in h and 'SMS' not in h and 'Twilio' not in h)
    ok('V2 guests cannot use it',go(sess(),'admin/verification.php')[0]==302)
    o,t=fresh();ok('V3 before setup, sending is honestly refused',send(o,t,'pre%s@example.com'%uniq())[0]==503)
    F={'action':'save','MAIL_HOST':'127.0.0.1','MAIL_PORT':'2525','MAIL_ENCRYPTION':'none','MAIL_USER':'u','MAIL_PASS':'secretpass123','MAIL_FROM':'spa@example.test','MAIL_FROM_NAME':'Spa'}
    tv=csrf(a,'admin/verification.php');s,h,l=go(a,'admin/verification.php',dict(F,csrf=tv));raw=open(lf).read()
    ok('V4 settings saved to a protected file',s==302 and raw.startswith('<?php exit; ?>') and '"MAIL_HOST": "127.0.0.1"' in raw)
    s,h,_=go(a,'admin/verification.php');ok('V5 saved password is never shown back','secretpass123' not in h and 'saved - leave blank' in h)
    tv=csrf(a,'admin/verification.php');go(a,'admin/verification.php',dict(F,csrf=tv,MAIL_PASS=''));ok('V6 blank password keeps the saved one','secretpass123' in open(lf).read())
    ok('V7 file not readable over the web',go(sess(),'includes/local.settings.php')[1].strip()=='')
    tv=csrf(a,'admin/verification.php');s,h,_=go(a,'admin/verification.php',{'csrf':tv,'action':'test_email','to':'owner@example.com'});time.sleep(.5)
    ok('V8 "Send test email" works and the email arrives','Test email sent' in h and [x for x in outbox() if x['to']=='owner@example.com'],re.sub('<[^>]+>',' ',h)[-250:])
    tv=csrf(a,'admin/verification.php');go(a,'admin/verification.php',dict(F,csrf=tv,MAIL_PORT='1'));tv=csrf(a,'admin/verification.php');s,h,_=go(a,'admin/verification.php',{'csrf':tv,'action':'test_email','to':'owner@example.com'})
    ok('V9 wrong SMTP settings give a readable reason','Could not send' in h and ('connect' in h.lower() or 'SMTP' in h))
    tv=csrf(a,'admin/verification.php');go(a,'admin/verification.php',dict(F,csrf=tv,MAIL_PORT='99999'));ok('V10 invalid port rejected with a message','valid email port' in go(a,'admin/verification.php')[1])
    tv=csrf(a,'admin/verification.php');go(a,'admin/verification.php',dict(F,csrf=tv))
    o,t=fresh();em='av%s@example.com'%uniq();ph=pn();ok('V11 after admin setup, the customer email code is delivered and verifies',verified(o,t,em))
    s,h,l=reg(o,t,em,ph);ok('V12 ...and registration completes',s==302 and sql("SELECT is_verified FROM users WHERE email='%s'"%em)=='1')
    tv=csrf(a,'admin/verification.php');go(a,'admin/verification.php',dict(F,csrf=tv,dev='on'));ok('V13 test-mode switch persists','name="dev" checked' in go(a,'admin/verification.php')[1])
    s,h,_=go(a,'admin/verification.php');ok('V14 Sending method choice (SMTP / PHP mail()) is offered, SMTP is the default','name="MAIL_DRIVER"' in h and 'PHP mail() function' in h and 'value="smtp" selected' in h)
    tv=csrf(a,'admin/verification.php');go(a,'admin/verification.php',dict(F,csrf=tv,MAIL_DRIVER='mail',MAIL_FROM='bad'));ok('V15 PHP mail() needs a valid From address','valid &quot;from&quot; email' in go(a,'admin/verification.php')[1])
    tv=csrf(a,'admin/verification.php');go(a,'admin/verification.php',dict(F,csrf=tv,MAIL_DRIVER='mail',MAIL_HOST='',MAIL_FROM='no-reply@example.test'));n0=len(outbox())
    tv=csrf(a,'admin/verification.php');s,h,_=go(a,'admin/verification.php',{'csrf':tv,'action':'test_email','to':'mailowner@example.com'});time.sleep(.5)
    ok('V16 admin picks PHP mail() with no SMTP host: test email goes through mail()','handed to the mail system' in h and eml_for('mailowner@example.com') is not None and len(outbox())==n0,re.sub('<[^>]+>',' ',h)[-250:])
    o,t=fresh();em='am%s@example.com'%uniq();ok('V17 customers then get their code through mail() with those settings',mverified(o,t,em))
elif S=='unit':
    def dev(env,remote,host):
        code='$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REMOTE_ADDR"]="%s";$_SERVER["HTTP_HOST"]="%s";$_SERVER["SCRIPT_FILENAME"]=getcwd()."/index.php";$_SERVER["SCRIPT_NAME"]="/servicehub/index.php";ob_start();require "includes/config.php";require "includes/otp.php";ob_end_clean();echo is_dev_mode()?"ON":"off","|",is_local()?"local":"remote","|",ini_get("display_errors");'%(remote,host)
        return subprocess.run(['php','-d','display_errors=1','-r',code],cwd='/mnt/user-data/outputs/servicehub',capture_output=True,text=True,env=dict(os.environ,**env)).stdout.strip().split('\n')[-1][-30:]
    ok('W1 test mode ON only when enabled + localhost + not production',dev({'OTP_DEV_MODE':'1'},'127.0.0.1','localhost').startswith('ON|local'))
    ok('W2 off by default',dev({},'127.0.0.1','localhost').startswith('off|local'))
    ok('W3 off for any non-local visitor even if enabled',dev({'OTP_DEV_MODE':'1'},'8.8.8.8','localhost').startswith('off|remote') and dev({'OTP_DEV_MODE':'1'},'127.0.0.1','spa.example.com').startswith('off|remote'))
    r=dev({'OTP_DEV_MODE':'1','APP_ENV':'production'},'127.0.0.1','localhost');ok('W4 production: test mode off, localhost-only tools off, PHP errors hidden',r.startswith('off|remote|0'),r)
elif S=='phpmail':   # port 8086: MAIL_DRIVER=mail with a stand-in sendmail; MAIL_FROM_NAME contains an injection attempt
    o,t=fresh();em='pm%s@example.com'%uniq();s,r=send(o,t,em);m=eml_for(em)
    ok('PM1 OTP is sent with PHP mail() (no SMTP involved) and arrives with a 6-digit code',s==200 and m is not None and re.fullmatch(r'\d{6}',eml_code(m) or ''),(s,r))
    if m:
        hdr=lambda k:str(make_header(decode_header(m[k]))) if m[k] else ''
        ok('PM2 proper MIME message: From, Subject, multipart/alternative (text + HTML), Message-ID, MIME-Version',m.get_content_type()=='multipart/alternative' and 'no-reply@example.test' in hdr('From') and 'verification code' in hdr('Subject') and 'example.test' in (m['Message-ID'] or '') and m['MIME-Version']=='1.0' and [p.get_content_type() for p in m.get_payload()]==['text/plain','text/html'],dict(m.items()))
        ok('PM3 header injection through the from-name is neutralised (no Bcc header)',m['Bcc'] is None and 'evil@example.com' not in str(m['To'])+str(m['Cc']),dict(m.items()))
        ok('PM4 envelope sender (-f) is passed to sendmail',('-fno-reply@example.test' in open(m.fname+'.args').read()))
        ok('PM5 the HTML part also carries the code',any(eml_code(m) in p.get_payload(decode=True).decode() for p in m.get_payload() if p.get_content_type()=='text/html'))
    ok('PM6 nothing was sent over SMTP for this address',not [x for x in outbox() if x['to']==em])
    ok('PM7 response does not expose the code',(eml_code(m) or 'x') not in json.dumps(r))
    s,r2=send(o,t,em);ok('PM8 cooldown still applies',s==429)
    ok('PM9 wrong code rejected, right code verifies',not verify(o,t,em,'000000' if eml_code(m)!='000000' else '111111')[1].get('verified') and verify(o,t,em,eml_code(m))[1].get('verified') is True)
    n0=len(glob.glob(MO+'/*.eml'));s,r=send(o,t,'victim@example.com\r\nBcc: evil@example.com');ok('PM10 email field cannot carry injected headers',s==422 and len(glob.glob(MO+'/*.eml'))==n0,(s,r))
    o3,t3=fresh();em3='pr%s@example.com'%uniq();ph=pn();ok('PM11 whole sign-up works with mail(): code by email, verify',mverified(o3,t3,em3))
    s,h,l=reg(o3,t3,em3,ph);ok('PM12 ...registration completes, account verified',s==302 and sql("SELECT is_verified FROM users WHERE email='%s'"%em3)=='1',(s,h[-120:]))
    o4,t4=fresh();n0=len(emls(em3));send(o4,t4,em3);time.sleep(.6);ok('PM13 already-registered address: same reply, nothing mailed (no enumeration)',len(emls(em3))==n0)
    log=open('/tmp/php_8086.log').read();ok('PM14 server log contains no codes',not re.search(r'(?<!\d)%s(?!\d)'%eml_code(m),log))
elif S=='phpmailfail':   # port 8087: mail() cannot reach a mail system
    o,t=fresh();s,r=send(o,t,'pf%s@example.com'%uniq());ok('PF1 mail() failure -> generic 502 + localhost-only detail naming mail()',s==502 and r.get('error','').startswith('We could not send') and 'mail()' in r.get('debug',''),(s,r))
    s,h,_=go(o,'api/send_otp.php',{'channel':'email','email':'pg%s@example.com'%uniq(),'csrf':csrf(o)},hdr={'Host':'spa.example.com'});j=json.loads(h);ok('PF2 other visitors get no technical detail',s==502 and 'debug' not in j,(s,j))
    s,r=send(o,t,'ph%s@example.com'%uniq());ok('PF3 a failed send does not start the cooldown (can retry at once)',s==502,(s,r))
elif S=='phpmailnofrom':   # port 8088: MAIL_DRIVER=mail but no From address
    o,t=fresh();s,r=send(o,t,'pn%s@example.com'%uniq());ok('PN1 mail() selected but no From address -> honest 503 "not set up"',s==503 and 'not set up' in r.get('error',''),(s,r))
