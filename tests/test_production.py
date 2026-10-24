"""Live-server simulation: APP_ENV=production behind HTTPS, hosting-style databases, admin setup key. Needs the servers started by the run script."""
import ssl,urllib.request,urllib.parse,http.cookiejar,re,json,time,subprocess,os,sys
CTX=ssl._create_unverified_context();S='https://127.0.0.1:8443/servicehub/';S2='https://127.0.0.1:8444/servicehub/';OUT='/tmp/otp_outbox.jsonl'
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*a,**k):return None
def sess():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),urllib.request.HTTPSHandler(context=CTX),NoRedir)
def go(o,url,data=None,hdr=None):
    d=urllib.parse.urlencode(data).encode() if isinstance(data,dict) else data
    try:r=o.open(urllib.request.Request(url,d,hdr or {}),timeout=25);return r.status,r.read().decode(),r.headers.get('Location'),r.headers
    except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.headers.get('Location'),e.headers
def tok(o,u):m=re.search(r'name="csrf" value="(\w+)"',go(o,u)[1]);return m.group(1) if m else ''
def post(o,u,d):return go(o,u,dict(d,csrf=tok(o,u)))
def sql(q,db='servicehub'):return subprocess.run(['mysql','-uroot','-N','-B',db,'-e',q],capture_output=True,text=True).stdout.strip()
def code_for(to,wait=5):
    t=time.time()
    while time.time()-t<wait:
        m=[json.loads(l) for l in open(OUT)] if os.path.exists(OUT) else [];m=[x for x in m if x['to']==to]
        if m:return m[-1]['code']
        time.sleep(.1)
def ok(n,c,i=''):print(('PASS ' if c else 'FAIL ')+n,'' if c else '  <- '+str(i)[:300])
DEF='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';KEY='setup-key-12345-xyz'
# --- HTTPS / headers / cookies
s,h,l,_=go(sess(),'http://127.0.0.1:8090/servicehub/');ok('X1 plain HTTP is redirected to HTTPS (301)',s==301 and (l or '').startswith('https://'),(s,l))
o=sess();s,h,l,hd=go(o,S+'register.php');ck=hd.get_all('Set-Cookie') or []
ok('X2 HSTS header sent',('max-age' in (hd.get('Strict-Transport-Security') or '')),dict(hd))
ok('X3 session cookie is Secure + HttpOnly + SameSite',bool(ck) and all(x.lower() in ck[0].lower() for x in ['Secure','HttpOnly','SameSite=Lax']),ck)
ok('X4 public pages render over HTTPS',all(go(sess(),S+p)[0]==200 for p in ['','login.php','register.php','admin/login.php']))
ok('X5 localhost-only tools are unavailable in production (migrate, reset)',all(go(sess(),S+p)[0]==500 and 'Not available' in go(sess(),S+p)[1] for p in ['migrate.php','admin/reset.php']))
s,h,l,_=go(sess(),S+'register.php');ok('X6 no "Setup needed" notice for anyone in production','Setup needed' not in h)
# --- no mail configured, production: honest error, no technical detail
o2=sess();t2=tok(o2,S2+'register.php');s,h,l,_=go(o2,S2+'api/send_otp.php',{'channel':'email','email':'a@example.com','csrf':t2});j=json.loads(h)
ok('X7 unconfigured email in production: honest 503, no hint/debug leaked',s==503 and 'debug' not in j and 'hint' not in j,(s,j))
ok('X8 setup-admin is switched off when no ADMIN_SETUP_KEY is set','switched off' in go(sess(),S2+'admin/setup-admin.php')[1])
# --- admin on a live server
sql("DELETE FROM rate_limits;DELETE FROM admins;INSERT INTO admins(email,password) VALUES('admin@servicehub.com','%s')"%DEF)
a=sess();s,h,l,_=post(a,S+'admin/login.php',{'email':'admin@servicehub.com','password':'password'});ok('Y1 default admin password is REFUSED on a live site (admin login)','cannot be used on a live site' in h and s==200,(s,l,h[-200:]))
a=sess();s,h,l,_=post(a,S+'login.php',{'email':'admin@servicehub.com','password':'password'});ok('Y2 ...and on the customer login page too','cannot be used on a live site' in h,(s,))
F=lambda **k:dict({'key':KEY,'email':'owner@mysite.com','password':'Str0ngPassw0rd','confirm':'Str0ngPassw0rd'},**k)
s,h,l,_=post(sess(),S+'admin/setup-admin.php',F(key='wrong-key-xxxxx'));ok('Y3 wrong setup key rejected','not correct' in h)
ok('Y4 weak admin password rejected','10+ characters' in post(sess(),S+'admin/setup-admin.php',F(password='short1A',confirm='short1A'))[1])
ok('Y5 mismatched admin password rejected','do not match' in post(sess(),S+'admin/setup-admin.php',F(confirm='Different1Aaaa'))[1])
s,h,l,_=post(sess(),S+'admin/setup-admin.php',F());ok('Y6 correct key creates the admin account, replacing the default',s==302 and 'admin/login' in (l or '') and sql("SELECT COUNT(*) FROM admins")=='1' and sql("SELECT email FROM admins")=='owner@mysite.com',(s,l))
a=sess();s,h,l,_=post(a,S+'admin/login.php',{'email':'owner@mysite.com','password':'Str0ngPassw0rd'});ok('Y7 new admin can sign in over HTTPS',s==302 and 'admin/index' in (l or ''),(s,l))
ok('Y8 admin dashboard works in production',all(go(a,S+'admin/%s.php'%p)[0]==200 for p in ['index','bookings','services','users','settings','verification']))
a2=sess();ok('Y9 the default password no longer works at all','Wrong email or password' in post(a2,S+'admin/login.php',{'email':'admin@servicehub.com','password':'password'})[1])
for i in range(9):s,h,l,_=post(sess(),S+'admin/setup-admin.php',F(key='wrong-key-%d'%i))
ok('Y10 setup-admin is rate limited against key guessing','Too many attempts' in h,re.sub('<[^>]+>',' ',h)[-120:])
# --- complete customer journey over HTTPS
sql("DELETE FROM rate_limits");c=sess();t=tok(c,S+'register.php');em='live%d@example.com'%(int(time.time())%100000)
s,h,l,_=go(c,S+'api/send_otp.php',{'channel':'email','email':em,'csrf':t});j=json.loads(h);ok('Z1 verification email sent through the configured SMTP server',s==200 and 'dev_code' not in j,(s,j))
cd=code_for(em);s,h,l,_=go(c,S+'api/verify_otp.php',{'channel':'email','email':em,'code':cd,'csrf':t});ok('Z2 email verified',json.loads(h).get('verified') is True)
s,h,l,_=go(c,S+'register.php',{'csrf':t,'name':'Live Customer','email':em,'cc':'+91','phone':'9811122233','password':'Secret123','confirm':'Secret123'});ok('Z3 registration completes (mobile number needs no code)',s==302 and 'login' in (l or ''),(s,l,h[-150:]))
s,h,l,_=post(c,S+'login.php',{'email':em,'password':'Secret123'});ok('Z4 customer logs in',s==302 and 'dashboard' in (l or ''))
fut=time.strftime('%Y-%m-%d',time.localtime(time.time()+3*86400));s,h,l,_=post(c,S+'user/dashboard.php',{'action':'book','service':'1','bdate':fut,'btime':'11:00','address':'online test'});ok('Z5 customer books a treatment',s==302)
s,h,_,_=go(a,S+'admin/bookings.php?q=Live');ok('Z6 the admin sees the booking (customer site and admin are connected)','Live Customer' in h and 'pending' in h)
s,h,_,_=go(a,S+'admin/users.php?q=Live');ok('Z7 the admin sees the customer with their mobile number','9811122233' in h)
# --- HTTPS-only protections
s,h,l,hd=go(sess(),S+'login.php');csp=hd.get('Content-Security-Policy','')
ok('Y11 over HTTPS the policy also upgrades any insecure request, and HSTS is on',"upgrade-insecure-requests" in csp and "script-src 'self'" in csp and 'max-age' in (hd.get('Strict-Transport-Security') or ''),csp)
ck=(hd.get_all('Set-Cookie') or [''])[0];ok('Y12 the session cookie is a "__Host-" cookie (HTTPS-only, this host only) with Secure, HttpOnly, SameSite','__Host-ServiceHubSID=' in ck and all(x in ck.lower() for x in ['secure','httponly','samesite=lax']) and 'domain' not in ck.lower(),ck)
s,h,_,_=go(a,S+'admin/security.php');t=re.sub('<[^>]+>',' ',h);row=lambda l:re.search(re.escape(l)+r'\s+(Good|Action needed|Info)',t)
ok('Y13 Security page over HTTPS: HTTPS, enforcement and cookie rows are Good',all(row(l) and row(l).group(1)=='Good' for l in ['This page is served over HTTPS','HTTPS is enforced (redirect + HSTS)','Session cookie is locked down','PHP errors are hidden from visitors','Content-Security-Policy blocks injected scripts','Admin password is not the default']),t[:600])
ok('Y14 ...and it tells the owner to remove development-only files left on the server',row('Development-only files are not on this server') and row('Development-only files are not on this server').group(1)=='Action needed')
# --- database errors never leak on a live site
s,h,_,_=go(sess(),'http://127.0.0.1:8092/servicehub/',hdr={'X-Forwarded-Proto':'https'});ok('D1 production with wrong DB credentials: generic message only',s==500 and 'temporarily unavailable' in h and not re.search(r'Access denied|nobody|MySQL|config\.php|\.env',h),re.sub('<[^>]+>',' ',h)[:200])
s,h,_,_=go(sess(),'http://127.0.0.1:8093/servicehub/');ok('D2 development with wrong DB credentials: helpful message (points to .env)','MySQL rejected the login' in h and '.env' in h)
# --- hosting-style database: different name, created from database_hosting.sql, no default admin
H='http://127.0.0.1:8094/servicehub/';o=sess()
ok('H1 hosting SQL imported into "host_db": tables exist, no starter admin',sql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='host_db'",'host_db')=='7' and sql("SELECT COUNT(*) FROM admins",'host_db')=='0' and sql("SELECT COUNT(*) FROM services",'host_db')=='6')
s,h,_,_=go(o,H);ok('H2 site runs on the custom DB_NAME and lists the treatments',s==200 and h.count('class="card svc"')==6,(s,))
s,h,_,_=go(o,H+'admin/login.php');ok('H3 admin login explains how to create the first admin','setup-admin' in h or 'reset.php' in h)
s,h,l,_=post(o,H+'admin/setup-admin.php',F());ok('H4 first admin created through setup-admin',s==302 and sql("SELECT COUNT(*) FROM admins",'host_db')=='1',(s,))
a3=sess();s,h,l,_=post(a3,H+'admin/login.php',{'email':'owner@mysite.com','password':'Str0ngPassw0rd'});ok('H5 admin signs in on the hosting database',s==302 and go(a3,H+'admin/index.php')[0]==200)
# --- local installer honours DB_NAME
I='http://127.0.0.1:8095/servicehub/';o=sess();s,h,l,_=post(o,I+'setup.php',{});ok('I1 setup.php creates and fills a custom-named database',s==302 and sql("SELECT COUNT(*) FROM services",'setup_db')=='6' and sql("SELECT COUNT(*) FROM admins",'setup_db')=='1',(s,l))
