"""Security tests: encryption at rest, headers/CSP, login lockout, idle sign-out, database TLS. Needs the servers started by the run script."""
import sys,os,re,json,time,subprocess
S=sys.argv[1];sys.argv=[sys.argv[0],'none']
import test_otp as T
from test_otp import go,sql,ok,sess,uniq,pn,decp,outbox
def B(port):return 'http://127.0.0.1:%d/servicehub/'%port
def tok(o,page,base=None):return re.search(r'name="csrf" value="(\w+)"',go(o,page,base=base)[1]).group(1)
def post(o,page,d,base=None):return go(o,page,dict(d,csrf=tok(o,page,base)),base=base)
def admin(port=8080,pw='password',email='admin@servicehub.com'):
    a=sess();s,h,l=post(a,'admin/login.php',{'email':email,'password':pw},B(port));return a if s==302 else None
def cust(email,pw,port=8080):
    c=sess();s,h,l=post(c,'login.php',{'email':email,'password':pw},B(port));return c if s==302 else None
def dump():return subprocess.run(['mysqldump','-uroot','servicehub','users','bookings'],capture_output=True,text=True).stdout
if S=='enc':
    a=admin();ok('E0 admin session for the checks',a is not None)
    o,t=T.fresh();em='zo%s@example.com'%uniq();ph=pn();NAME='Zoë Müllerström'
    ok('E1 customer registers with a non-ASCII name',T.verified(o,t,em) and T.reg(o,t,em,ph,name=NAME)[0]==302)
    row=sql("SELECT name,phone FROM users WHERE email='%s'"%em).split('\t')
    ok('E2 name and phone are stored encrypted (AES-GCM blobs, no plaintext)',row[0].startswith('enc1:') and row[1].startswith('enc1:') and 'Müller' not in row[0] and ph not in row[1],row)
    c=cust(em,'Secret123');fut=time.strftime('%Y-%m-%d',time.localtime(time.time()+4*86400));NOTE='Gate code 4321, 3rd floor, ring twice'
    s,h,l=post(c,'user/dashboard.php',{'action':'book','service':'1','bdate':fut,'btime':'15:00','address':NOTE})
    adr=sql("SELECT b.address FROM bookings b JOIN users u ON u.id=b.user_id WHERE u.email='%s'"%em)
    ok('E3 booking notes are stored encrypted',s==302 and adr.startswith('enc1:') and '4321' not in adr,adr[:40])
    d=dump();leak=[x for x in ['Müllerström','Zoë',ph,'Gate code','ring twice'] if x in d];ok('E4 a raw database dump contains no plaintext name, phone or note',not leak,('found:',leak,[d[max(0,d.find(x)-60):d.find(x)+60] for x in leak]))
    ok('E5 customer sees their own name (decrypted) on the dashboard','Zoë' in go(c,'user/dashboard.php',base=None)[1])
    s,h,_=go(a,'admin/users.php');ok('E6 admin sees decrypted name and phone',NAME in h and ph in h)
    ok('E7 admin booking list shows the decrypted note and name','4321' in go(a,'admin/bookings.php')[1] and NAME in go(a,'admin/bookings.php')[1])
    ok('E8 admin search still works on encrypted fields (name, phone, note, case-insensitive)',NAME in go(a,'admin/users.php?q=m%C3%BCllerstr')[1] and NAME in go(a,'admin/users.php?q='+ph[-6:])[1] and NAME in go(a,'admin/bookings.php?q=zo%C3%AB')[1] and NAME not in go(a,'admin/users.php?q=nomatchxyz')[1])
    o2,t2=T.fresh();em2='zp%s@example.com'%uniq();T.verified(o2,t2,em2);T.reg(o2,t2,em2,ph,name=NAME)
    p2=sql("SELECT phone FROM users WHERE email='%s'"%em2);ok('E9 same value encrypts differently each time (random IV)',p2!=row[1] and p2.startswith('enc1:'))
    ok('E10 a ciphertext cannot be moved to another column (bound to its field)',decp(row[1],'users.name')=='' and decp(row[1],'users.phone')=='+91'+ph)
    sql("UPDATE users SET name=CONCAT(LEFT(name,12),IF(SUBSTR(name,13,1)='A','B','A'),SUBSTR(name,14)) WHERE email='%s'"%em2)
    s,h,_=go(a,'admin/users.php');ok('E11 a tampered value is rejected safely (page still works, nothing shown)',s==200 and 'Fatal' not in h and 'Warning' not in h)
    o3,t3=T.fresh();em3='em%s@example.com'%uniq();T.verified(o3,t3,em3);EM='😀'*100
    ok('E12 a 100-character name of 4-byte characters fits the column',T.reg(o3,t3,em3,pn(),name=EM)[0]==302 and decp(sql("SELECT name FROM users WHERE email='%s'"%em3),'users.name')==EM and len(sql("SELECT name FROM users WHERE email='%s'"%em3))<=600)
    H=subprocess.run(['php','-r','echo password_hash("x",PASSWORD_DEFAULT);'],capture_output=True,text=True).stdout
    sql("INSERT INTO users(name,email,phone,password,is_verified) VALUES('Legacy Lady','legacy%s@x.com','+919000000001','%s',1)"%(uniq(),H));sql("INSERT INTO bookings(user_id,service_id,bdate,btime,address,status) SELECT id,1,CURDATE()+INTERVAL 9 DAY,'10:00','plain legacy note','pending' FROM users WHERE name='Legacy Lady' LIMIT 1")
    s,h,_=go(a,'admin/users.php');ok('E13 records saved before encryption are still shown (migration-friendly)','Legacy Lady' in h and '+919000000001' in h)
    s,h,_=go(a,'admin/security.php');ok('E14 Security page flags the plain-text records',s==200 and 'still plain text' in h and 'Action needed' in h,re.sub('<[^>]+>',' ',h)[:300])
    s,h,l=post(a,'admin/security.php',{'action':'encrypt'});s,h,_=go(a,'admin/security.php')
    ok('E15 "Encrypt existing data" encrypts them',sql("SELECT COUNT(*) FROM users WHERE name='Legacy Lady' OR phone='+919000000001'")=='0' and sql("SELECT COUNT(*) FROM bookings WHERE address='plain legacy note'")=='0' and 'Encrypted' in h)
    ok('E16 ...and they still display correctly afterwards','Legacy Lady' in go(a,'admin/users.php')[1] and 'plain legacy note' in go(a,'admin/bookings.php')[1])
    s,h,l=post(a,'admin/security.php',{'action':'encrypt'});s,h,_=go(a,'admin/security.php');ok('E17 running it again is harmless (nothing left to encrypt)','Encrypted 0 customer' in h and sql("SELECT COUNT(*) FROM users WHERE name NOT LIKE 'enc1:%' AND name<>'' AND name NOT IN('Aud One','Aud Two','Old One','Old Two','Old Three','Unv')")=='0')
    t=re.sub('<[^>]+>',' ',h);ok('E18 Security page shows the key fingerprint but never the key',re.search(r'fingerprint: [0-9a-f]{8}',t) is not None and os.environ['DATA_KEY'] not in h)
elif S=='wrongkey':   # port 8097: same database, a DIFFERENT data key
    a=admin(8097);ok('K0 admin session',a is not None)
    s,h,_=go(a,'admin/users.php',base=B(8097));ok('K1 with the wrong key nothing crashes and no encrypted text leaks into pages',s==200 and 'enc1:' not in h and 'Fatal' not in h and 'Zoë' not in h)
    s,h,_=go(a,'admin/security.php',base=B(8097));t=re.sub('<[^>]+>',' ',h);ok('K2 Security page warns that data cannot be decrypted','cannot be decrypted' in t and 'Action needed' in h,t[:400])
elif S=='autokey':   # port 8083: no DATA_KEY configured anywhere
    a=admin(8083);ok('A0 admin session',a is not None);go(a,'admin/security.php',base=B(8083));lf='/mnt/user-data/outputs/servicehub/includes/local.settings.php';raw=open(lf).read() if os.path.exists(lf) else ''
    ok('A1 a data key is generated automatically and saved in the protected settings file',raw.startswith('<?php exit; ?>') and re.search(r'"DATA_KEY": "[0-9a-f]{64}"',raw) is not None)
    s,h,_=go(a,'admin/security.php',base=B(8083));ok('A2 Security page reports where the key comes from','includes/local.settings.php' in h)
elif S=='headers':
    P=lambda p,port=8080:go(sess(),p,base=B(port))
    import urllib.request
    def hdrs(path,port=8080):
        r=urllib.request.build_opener().open(B(port)+path) if True else None;return r.headers
    for page in ['','login.php','register.php','admin/login.php']:
        h=hdrs(page);csp=h.get('Content-Security-Policy','');sc=[x.strip() for x in csp.split(';') if x.strip().startswith('script-src')]
        ok('H1 %s: strict CSP (scripts only from this site, no inline)'%(page or 'home'),sc==["script-src 'self'"],csp)
        ok('H2 %s: anti-clickjacking, no-sniff, referrer, permissions, no-cache headers; no X-Powered-By'%(page or 'home'),h.get('X-Frame-Options')=='DENY' and h.get('X-Content-Type-Options')=='nosniff' and 'no-store' in h.get('Cache-Control','') and bool(h.get('Referrer-Policy')) and bool(h.get('Permissions-Policy')) and h.get('Cross-Origin-Opener-Policy')=='same-origin' and 'X-Powered-By' not in h,dict(h))
    h=hdrs('');csp=h.get('Content-Security-Policy','');ok('H3 CSP forbids frames, plugins, base-tag and foreign form targets',all(x in csp for x in ["frame-ancestors 'none'","object-src 'none'","base-uri 'self'","form-action 'self'","default-src 'self'"]))
    ok('H4 over plain HTTP the CSP does not force upgrades',"upgrade-insecure-requests" not in csp)
    ok('H5 images and fonts may come only from the known hosts',"img-src 'self' data: https://images.unsplash.com" in csp and "font-src 'self' https://fonts.gstatic.com" in csp)
elif S=='csp':   # every page must work under the strict policy: no inline scripts / handlers, only allowed external hosts
    ALLOWED={'fonts.googleapis.com','fonts.gstatic.com','images.unsplash.com'};a=admin();u=cust('old3@x.com','oldpass1');g=sess()
    pages=[(g,p) for p in ['','login.php','register.php','admin/login.php']]+[(u,'user/dashboard.php')]+[(a,'admin/%s.php'%p) for p in ['index','bookings','services','users','settings','verification','security']]
    bad=[];n=0
    for o,p in pages:
        s,h,_=go(o,p);n+=1
        if s!=200:bad.append((p,'status',s));continue
        if re.search(r'<[^>]+\son[a-z]+\s*=',h,re.I):bad.append((p,'inline event handler'))
        if re.search(r'<script(?![^>]*\ssrc=)[^>]*>',h,re.I):bad.append((p,'inline script'))
        if re.search(r'javascript:',h,re.I):bad.append((p,'javascript: URL'))
        for m in re.findall(r'<(?:script|link|img)[^>]+(?:src|href)=["\'](https?://[^"\']+)',h,re.I)+re.findall(r'url\((https?://[^)\'"]+)',h):
            host=re.match(r'https?://([^/]+)',m).group(1)
            if host not in ALLOWED:bad.append((p,'external host not allowed by CSP',host))
    ok('C1 all %d pages (visitor, customer, admin) are compatible with the strict policy'%n,not bad,bad)
    ok('C2 the shared script is served and is the only script besides the sign-up one',go(g,'assets/js/app.js')[0]==200 and set(re.findall(r'<script[^>]+src="([^"]+)"',go(g,'register.php')[1]))=={'/servicehub/assets/js/app.js','/servicehub/assets/js/register.js'})
elif S=='force':
    s,h,l=go(sess(),'',base=B(8098));ok('F1 FORCE_HTTPS=1 redirects even on a development setup',s==301 and (l or '').startswith('https://'),(s,l))
elif S=='throttle':
    sql("DELETE FROM rate_limits");E='old2@x.com'
    for i in range(5):s,h,_=post(sess(),'login.php',{'email':E,'password':'wrong%d'%i});ok('L%d wrong password #%d is just rejected'%(i+1,i+1),'Wrong email or password' in h) if i in(0,4) else None
    s,h,l=post(sess(),'login.php',{'email':E,'password':'oldpass1'});ok('L3 after 5 failures even the CORRECT password is locked out',s==200 and 'Too many failed attempts' in h and l is None,(s,l))
    ok('L4 other accounts are not affected',cust('old3@x.com','oldpass1') is not None)
    sql("DELETE FROM rate_limits");ok('L5 lockout ends when the 15-minute window passes (simulated)',cust(E,'oldpass1') is not None)
    sql("DELETE FROM rate_limits")
    for i in range(3):post(sess(),'login.php',{'email':E,'password':'bad'})
    ok('L6 a successful login clears the counter',cust(E,'oldpass1') is not None)
    for i in range(4):post(sess(),'login.php',{'email':E,'password':'bad'})
    ok('L7 ...so 4 more mistakes do not lock the account',cust(E,'oldpass1') is not None)
    sql("DELETE FROM rate_limits");E2='nobody%s@example.com'%uniq()
    for i in range(5):post(sess(),'login.php',{'email':E2,'password':'bad'})
    s,h,_=post(sess(),'login.php',{'email':E2,'password':'bad'});ok('L8 unknown emails lock too (no way to tell which emails exist)','Too many failed attempts' in h)
    sql("DELETE FROM rate_limits")
    for i in range(5):post(sess(),'admin/login.php',{'email':'admin@servicehub.com','password':'bad%d'%i})
    s,h,l=post(sess(),'admin/login.php',{'email':'admin@servicehub.com','password':'password'});ok('L9 the admin sign-in is locked the same way',s==200 and 'Too many failed attempts' in h and l is None,(s,l))
    sql("DELETE FROM rate_limits");ok('L10 admin signs in again once the lock has passed',admin() is not None)
    sql("DELETE FROM rate_limits")
    for i in range(30):post(sess(),'login.php',{'email':'spray%d@example.com'%i,'password':'bad'})
    s,h,_=post(sess(),'login.php',{'email':'old3@x.com','password':'oldpass1'});ok('L11 password-spraying from one address (30 failures) locks that address out','Too many failed attempts' in h)
    sql("DELETE FROM rate_limits")
elif S=='idle':   # port 8099: 2 s idle limits;   port 8109: 4 s maximum session age
    a=admin(8099);ok('I1 admin signs in',a is not None and go(a,'admin/index.php',base=B(8099))[0]==200)
    time.sleep(1.2);ok('I2 activity keeps the session alive',go(a,'admin/index.php',base=B(8099))[0]==200);time.sleep(1.2);ok('I3 ...',go(a,'admin/index.php',base=B(8099))[0]==200)
    time.sleep(3);s,h,l=go(a,'admin/index.php',base=B(8099));ok('I4 admin is signed out after the idle time',s==302 and 'admin/login' in (l or ''),(s,l))
    ok('I5 ...with an explanation','signed out' in go(a,'admin/login.php',base=B(8099))[1])
    c=cust('old3@x.com','oldpass1',8099);ok('I6 customer signs in',c is not None and go(c,'user/dashboard.php',base=B(8099))[0]==200);time.sleep(3);ok('I7 customer is signed out after the idle time',go(c,'user/dashboard.php',base=B(8099))[0]==302)
    a=admin(8109);ok('I8 (max-age server) admin signs in',a is not None)
    for i in range(3):time.sleep(1);go(a,'admin/index.php',base=B(8109))
    time.sleep(1.5);s,h,l=go(a,'admin/index.php',base=B(8109));ok('I9 a session is ended after its maximum age even if the person stays active',s==302,(s,l))
elif S=='dbtls':   # needs a MariaDB with TLS and a user that REQUIRES it
    r=subprocess.run(['mysql','-uroot','-N','-e',"SHOW VARIABLES LIKE 'have_ssl'"],capture_output=True,text=True).stdout;ok('D0 database server has TLS enabled','YES' in r,r)
    s,h,_=go(sess(),'',base=B(8100));ok('D1 site runs over a verified TLS database connection (DB_SSL=1 + CA)',s==200 and h.count('class="card svc"')>=1,(s,h[:200]))
    a=admin(8100);s,h,_=go(a,'admin/security.php',base=B(8100)) if a else (0,'',0);t=re.sub('<[^>]+>',' ',h);ok('D2 Security page confirms the encrypted connection (protocol + cipher)',bool(re.search(r'Encrypted with TLSv1\.[23] / [A-Z0-9_-]+',t)),t[t.find('Database connection'):][:200])
    s,h,_=go(sess(),'',base=B(8101));ok('D3 the database itself refuses a plain connection for that user (server-enforced)',s==500 and 'rejected the login' in h,(s,re.sub('<[^>]+>',' ',h)[:150]))
    s,h,_=go(sess(),'',base=B(8102));ok('D4 DB_SSL=1 over a local socket (not encrypted): the site refuses to run instead of silently continuing',s==500 and 'not encrypted' in h,(s,re.sub('<[^>]+>',' ',h)[:200]))
    s,h,_=go(sess(),'',base=B(8103));ok('D5 a server certificate that does not match the CA is rejected',s==500 and 'class="card svc"' not in h,(s,))
    s,h,_=go(sess(),'',base=B(8104));ok('D6 DB_SSL=1 without a CA still encrypts (unverified) and works',s==200)
    s,h,_=go(sess(),'',base=B(8080));a=admin();s,h,_=go(a,'admin/security.php');t=re.sub('<[^>]+>',' ',h);ok('D7 on the normal local setup the page says the connection is not TLS and why that is fine locally','Not encrypted' in t and 'same server' in t)
