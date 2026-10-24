"""Test helper: HTTPS front for a plain PHP server (like a real host's SSL layer). Usage: https_proxy.py LISTEN_PORT UPSTREAM_PORT"""
import http.client,ssl,sys
from http.server import BaseHTTPRequestHandler,ThreadingHTTPServer
L,UP=int(sys.argv[1]),int(sys.argv[2])
class H(BaseHTTPRequestHandler):
    protocol_version='HTTP/1.0'
    def log_message(self,*a):pass
    def fwd(self):
        n=int(self.headers.get('Content-Length',0) or 0);body=self.rfile.read(n) if n else None
        h={k:v for k,v in self.headers.items() if k.lower() not in('connection','keep-alive')};h['X-Forwarded-Proto']='https'
        c=http.client.HTTPConnection('127.0.0.1',UP,timeout=30);c.request(self.command,self.path,body,h);r=c.getresponse();d=r.read()
        self.send_response(r.status)
        for k,v in r.getheaders():
            if k.lower() not in('transfer-encoding','connection','content-length'):self.send_header(k,v)
        self.send_header('Content-Length',str(len(d)));self.end_headers();self.wfile.write(d)
    do_GET=do_POST=fwd
s=ThreadingHTTPServer(('127.0.0.1',L),H);c=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);c.load_cert_chain('/tmp/tls.crt','/tmp/tls.key');s.socket=c.wrap_socket(s.socket,server_side=True);s.serve_forever()
