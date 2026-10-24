"""Site audit: crawls every page as guest / customer / admin, flags HTTP errors, broken links/assets and any PHP warning,
then fuzzes every form with missing, empty, array and oversized input. Run with PHP started with -d display_errors=1."""
import urllib.request,urllib.parse,http.cookiejar,re,subprocess,sys,os,html
PORT=int(os.environ.get('PORT',8080));O='http://127.0.0.1:%d'%PORT;B=O+'/servicehub/'
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*a,**k):return None
def sess():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedir)
def go(o,url,data=None):
    if not url.startswith('http'):url=B+url
    d=urllib.parse.urlencode(data,doseq=True).encode() if isinstance(data,dict) else data
    try:r=o.open(url,d,timeout=20);return r.status,r.read().decode(errors='ignore'),r.headers.get('Location')
    except urllib.error.HTTPError as e:return e.code,e.read().decode(errors='ignore'),e.headers.get('Location')
def sql(q):return subprocess.run(['mysql','-uroot','-N','-B','servicehub','-e',q],capture_output=True,text=True).stdout.strip()
PHPERR=re.compile(r'(<b>)?(Warning|Notice|Deprecated|Fatal error|Parse error)(</b>)?:|Stack trace|Uncaught')
bad=[];seen=set();N=[0]
def check(who,url,s,h):
    N[0]+=1
    if s>=400 and s!=405 or PHPERR.search(h):bad.append((who,url.replace(B,''),s,(PHPERR.search(h) or re.search(r'.{0,0}',h)).group(0)[:40]))
def crawl(who,o,seeds):
    q=list(seeds)
    while q:
        u=q.pop(0);u=urllib.parse.urldefrag(u)[0]
        if u in seen or not u.startswith(O):continue
        seen.add(u);s,h,l=go(o,u);check(who,u,s,h)
        if 'text/html' in '' or s==200:
            for m in re.findall(r'(?:href|src)="([^"]+)"',h):
                m=html.unescape(m)
                if m.startswith(('#','mailto:','javascript:','tel:','data:')) or (m.startswith('http') and not m.startswith(O)):continue
                if 'logout' in m:continue
                q.append(urllib.parse.urljoin(u,m))
def tok(o,p):
    m=re.search(r'name="csrf" value="(\w+)"',go(o,p)[1]);return m.group(1) if m else ''
# seed data
H=subprocess.run(['php','-r','echo password_hash("Secret123",PASSWORD_DEFAULT);'],capture_output=True,text=True).stdout
sql("DELETE FROM bookings;DELETE FROM users WHERE email LIKE 'aud%'")
sql("INSERT INTO users(name,email,phone,password,is_verified,email_verified_at,phone_verified_at) VALUES('Aud One','aud1@x.com','+919800000001','%s',1,NOW(),NOW()),('Aud Two','aud2@x.com',NULL,'%s',1,NOW(),NOW())"%(H,H))
sql("INSERT INTO bookings(user_id,service_id,bdate,btime,address,status) SELECT id,1,CURDATE()+INTERVAL 2 DAY,'10:00','<b>x</b>','pending' FROM users WHERE email='aud1@x.com'")
g=sess();crawl('guest',g,[B,B+'login.php',B+'register.php',B+'admin/login.php'])
u=sess();go(u,'login.php',{'csrf':tok(u,'login.php'),'email':'aud1@x.com','password':'Secret123'});crawl('user',u,[B+'user/dashboard.php',B+'user/dashboard.php?service=2',B])
a=sess();go(a,'admin/login.php',{'csrf':tok(a,'admin/login.php'),'email':'admin@servicehub.com','password':'password'})
crawl('admin',a,[B+'admin/index.php',B+'admin/bookings.php?status=pending&q=aud',B+'admin/bookings.php?status=bogus',B+'admin/users.php?q=%27%22',B+'admin/services.php',B+'admin/settings.php',B+'admin/verification.php',B+'admin/setup-admin.php',B])
print('pages checked: %d'%N[0])
# fuzz POSTs
fz=[];arr={'a[]':'x'}
def fuzz(o,page,tpl,variants):
    for name,mut in variants:
        d=dict(tpl);d.update(mut);d['csrf']=tok(o,page);s,h,l=go(o,page,d);N[0]+=1
        if s>=500 or PHPERR.search(h):fz.append((page,name,s,(PHPERR.search(h) or re.search(r'.{0,0}',h)).group(0)[:40]))
V=lambda *f:[('array-'+k,{k+'[]':'x',k:''}) for k in f]
for p,tpl,fs in [('login.php',{'email':'a@b.co','password':'x'},['email','password']),('register.php',{'name':'N','email':'a@b.co','cc':'+91','phone':'9876543210','password':'Secret123','confirm':'Secret123'},['name','email','cc','phone','password','confirm'])]:
    o=sess();fuzz(o,p,tpl,V(*fs)+[('empty',{k:'' for k in tpl}),('huge',{k:'A'*5000 for k in tpl}),('missing',{})]+[('unicode',{k:'ਪ\u0000é' for k in tpl})])
o=sess();go(o,'user/dashboard.php');o=u
fuzz(u,'user/dashboard.php',{'action':'book','service':'1','bdate':'2099-01-01','btime':'10:00','address':'x'},V('service','bdate','btime','address','action')+[('bad-date',{'bdate':'31-02-2030'}),('neg-service',{'service':'-5'}),('huge',{'address':'A'*100000})])
fuzz(u,'user/dashboard.php',{'action':'cancel','id':'1'},V('id','action')+[('missing',{'id':''})])
fuzz(a,'admin/bookings.php',{'action':'status','id':'1','status':'confirmed'},V('id','status','action')+[('bad-status',{'status':'zzz'}),('unknown-id',{'id':'99999'})])
fuzz(a,'admin/users.php',{'action':'block','id':'1'},V('id','action'))
fuzz(a,'admin/services.php',{'action':'save','id':'0','name':'Fz','description':'d','price':'10','duration':'10'},V('name','description','price','duration','id')+[('neg',{'price':'-1','duration':'-1'}),('words',{'price':'abc'}),('empty-name',{'name':''}),('symbols-name',{'name':'!!!'})])
fuzz(a,'admin/settings.php',{'action':'password','current':'x','new':'abcdefgh1','confirm':'abcdefgh1'},V('current','new','confirm','action'))
for ep,tpl in [('api/send_otp.php',{'channel':'email','email':'a@b.co'}),('api/verify_otp.php',{'channel':'email','email':'a@b.co','code':'123456'})]:
    o=sess();t=tok(o,'register.php')
    for name,mut in V(*list(tpl))+[('missing',{k:'' for k in tpl}),('huge',{k:'9'*5000 for k in tpl}),('phone-channel',{'channel':'phone'})]:
        d=dict(tpl);d.update(mut);d['csrf']=t;s,h,l=go(o,ep,d);N[0]+=1
        if s>=500 or PHPERR.search(h):fz.append((ep,name,s,h[:60]))
fuzz(a,'admin/verification.php',{'action':'save','MAIL_HOST':'','MAIL_PORT':'587','MAIL_ENCRYPTION':'tls','MAIL_FROM':'a@b.co'},V('MAIL_HOST','MAIL_PORT','MAIL_ENCRYPTION','MAIL_FROM','action')+[('huge',{'MAIL_HOST':'A'*5000})])
fuzz(a,'admin/verification.php',{'action':'test_email','to':'a@b.co'},V('to','action'))
sql("DELETE FROM bookings;DELETE FROM users WHERE email LIKE 'aud%'")
print('requests total: %d'%N[0]);print('PAGE PROBLEMS:',len(bad));[print('  ',x) for x in bad[:25]];print('FORM/API PROBLEMS:',len(fz));[print('  ',x) for x in fz[:40]]
