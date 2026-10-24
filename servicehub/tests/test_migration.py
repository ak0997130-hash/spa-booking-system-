"""Upgrade-path test: an OLD database (customers + booking, no OTP columns) is migrated and must keep all data."""
import urllib.request,urllib.parse,http.cookiejar,re,subprocess
B='http://127.0.0.1:8080/servicehub/'
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*a,**k):return None
o=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedir)
def go(p,d=None):
    d=urllib.parse.urlencode(d).encode() if d else None
    try:r=o.open(B+p,d);return r.status,r.read().decode()
    except urllib.error.HTTPError as e:return e.code,e.read().decode()
def sql(q):return subprocess.run(['mysql','-uroot','-N','-B','servicehub','-e',q],capture_output=True,text=True).stdout.strip()
def ok(n,c,i=''):print(('PASS ' if c else 'FAIL ')+n,'' if c else '  <- '+str(i)[:300])
def migrate():
    t=re.search(r'name="csrf" value="(\w+)"',go('migrate.php')[1]).group(1);return go('migrate.php',{'csrf':t})
s,h=go('');ok('M0 un-migrated database shows a clear "run migrate.php" message',s==500 and 'migrate.php' in h,(s,h[:150]))
before=(sql("SELECT COUNT(*) FROM users"),sql("SELECT COUNT(*) FROM bookings"),sql("SELECT COUNT(*) FROM services"))
s,h=migrate();ok('M1 migration runs',s==200 and 'Done' in h,(s,re.sub('<[^>]+>',' ',h)[-300:]))
after=(sql("SELECT COUNT(*) FROM users"),sql("SELECT COUNT(*) FROM bookings"),sql("SELECT COUNT(*) FROM services"))
ok('M2 users, bookings and services untouched',before==after,(before,after))
ok('M3 legacy customers grandfathered as verified',sql("SELECT COUNT(*) FROM users WHERE is_verified=1")==before[0])
ok('M4 existing phone numbers untouched',sql("SELECT COUNT(*) FROM users WHERE phone=''")=='2' and sql("SELECT phone FROM users WHERE email='old3@x.com'")=='9876543210')
ok('M5 verification tables exist; no unique phone index',sql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='servicehub' AND table_name IN('otp_challenges','rate_limits')")=='2' and sql("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='servicehub' AND table_name='users' AND index_name='uq_users_phone'")=='0')
ok('M6 booking foreign key still intact',sql("SELECT COUNT(*) FROM bookings b JOIN users u ON u.id=b.user_id")==before[1])
s,h=migrate();ok('M7 running the migration again is harmless',s==200 and 'Done' in h and 'Already in place' in h,(s,))
sql("UPDATE users SET phone=NULL WHERE phone='';ALTER TABLE users ADD COLUMN phone_verified_at DATETIME NULL;ALTER TABLE users ADD UNIQUE KEY uq_users_phone(phone)")
s,h=migrate();ok('M8 an earlier-version install (unique phone index) is upgraded: index removed, data kept',s==200 and 'Done' in h and sql("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='servicehub' AND table_name='users' AND index_name='uq_users_phone'")=='0' and sql("SELECT COUNT(*) FROM users")==before[0],(s,))
sql("INSERT INTO users(name,email,phone,password,is_verified) VALUES('D1','d1@x.com','+911111111111','x',1),('D2','d2@x.com','+911111111111','x',1)")
ok('M9 two customers may now share a mobile number',sql("SELECT COUNT(*) FROM users WHERE phone='+911111111111'")=='2');sql("DELETE FROM users WHERE email IN('d1@x.com','d2@x.com')")
