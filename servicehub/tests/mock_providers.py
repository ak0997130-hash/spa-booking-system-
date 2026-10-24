"""Local fake SMTP server (port 2525) and fake SMS API (port 9099) for testing. Not for production.
Every message the site "sends" is appended to /tmp/otp_outbox.jsonl so tests can read the code."""
import asyncio,json,re,threading,urllib.parse
from http.server import BaseHTTPRequestHandler,HTTPServer
OUT='/tmp/otp_outbox.jsonl'
def save(**k):
    with open(OUT,'a') as f:f.write(json.dumps(k)+'\n')
async def smtp(r,w):
    w.write(b'220 sink ESMTP\r\n');rcpt=''
    while True:
        l=await r.readline()
        if not l:break
        c=l.decode(errors='ignore').strip();u=c.upper()
        if u.startswith(('EHLO','HELO')):w.write(b'250-sink\r\n250 AUTH LOGIN PLAIN\r\n')
        elif u.startswith('AUTH LOGIN'):
            w.write(b'334 VXNlcm5hbWU6\r\n');await r.readline();w.write(b'334 UGFzc3dvcmQ6\r\n');await r.readline();w.write(b'235 ok\r\n')
        elif u.startswith('AUTH'):w.write(b'235 ok\r\n')
        elif u.startswith('RCPT'):rcpt=re.search(r'<([^>]+)>',c).group(1);w.write(b'250 ok\r\n')
        elif u=='DATA':
            w.write(b'354 go\r\n');buf=b''
            while True:
                l=await r.readline()
                if l==b'.\r\n':break
                buf+=l
            m=re.search(r'code is[^0-9]{0,40}(\d{6})',buf.decode(errors='ignore'))
            save(type='email',to=rcpt,code=m.group(1) if m else None);w.write(b'250 queued\r\n')
        elif u=='QUIT':w.write(b'221 bye\r\n');await w.drain();break
        else:w.write(b'250 ok\r\n')
        await w.drain()
    w.close()
class SMS(BaseHTTPRequestHandler):
    def log_message(self,*a):pass
    def reply(self,code,obj):
        b=json.dumps(obj).encode();self.send_response(code);self.send_header('Content-Type','application/json');self.send_header('Content-Length',str(len(b)));self.end_headers();self.wfile.write(b)
    def do_POST(self):
        n=int(self.headers.get('Content-Length',0) or 0);body=self.rfile.read(n).decode();u=urllib.parse.urlparse(self.path)
        if u.path.startswith('/2010-04-01/Accounts/'):   # Twilio-shaped
            f=urllib.parse.parse_qs(body)
            if not (self.headers.get('Authorization') or '').startswith('Basic '):return self.reply(401,{'message':'auth'})
            to=f['To'][0];m=re.search(r'verification code: (\d{6})',f['Body'][0])
            if to.endswith('0000'):return self.reply(400,{'code':21211,'message':'bad number'})
            save(type='sms',provider='twilio',to=to,code=m.group(1) if m else None);return self.reply(201,{'sid':'SMtest'})
        if u.path.startswith('/api/v5/otp'):             # MSG91-shaped
            q=urllib.parse.parse_qs(u.query)
            if self.headers.get('authkey')!='testkey':return self.reply(401,{'type':'error'})
            if q['mobile'][0].endswith('0000'):return self.reply(200,{'type':'error','message':'invalid'})
            save(type='sms',provider='msg91',to='+'+q['mobile'][0],code=q['otp'][0]);return self.reply(200,{'type':'success'})
        self.reply(404,{})
threading.Thread(target=HTTPServer(('127.0.0.1',9099),SMS).serve_forever,daemon=True).start()
async def main():
    s=await asyncio.start_server(smtp,'127.0.0.1',2525)
    async with s:await s.serve_forever()
asyncio.run(main())
