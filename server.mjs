import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const root = path.dirname(fileURLToPath(import.meta.url));
const types = { '.html':'text/html; charset=utf-8', '.css':'text/css', '.js':'text/javascript', '.jpg':'image/jpeg', '.png':'image/png', '.mp4':'video/mp4', '.ttf':'font/ttf' };
http.createServer((req,res) => {
  if (!['GET','HEAD'].includes(req.method)) { res.writeHead(405).end(); return; }
  let name;
  try { name = decodeURIComponent(new URL(req.url, 'http://localhost').pathname); } catch { res.writeHead(400).end(); return; }
  const file = path.resolve(root, '.' + (name === '/' ? '/index.html' : name));
  if (!file.startsWith(root + path.sep)) { res.writeHead(403).end(); return; }
  fs.stat(file, (err, stat) => {
    if (err || !stat.isFile()) { res.writeHead(404).end('Not found'); return; }
    let start = 0, end = stat.size - 1, status = 200;
    const range = req.headers.range?.match(/^bytes=(\d+)-(\d*)$/);
    if (range) { start=Number(range[1]); end=range[2] ? Math.min(Number(range[2]),end) : end; status=206; }
    if(start>end || start>=stat.size) { res.writeHead(416,{'Content-Range':`bytes */${stat.size}`}).end(); return; }
    const headers = {'Content-Type':types[path.extname(file)] || 'application/octet-stream','Content-Length':end-start+1,'Accept-Ranges':'bytes','Cache-Control':'no-cache'};
    if(status===206) headers['Content-Range']=`bytes ${start}-${end}/${stat.size}`;
    res.writeHead(status,headers);
    if(req.method==='HEAD') res.end(); else fs.createReadStream(file,{start,end}).pipe(res);
  });
}).listen(4178,'127.0.0.1',()=>console.log('VCAC preview: http://127.0.0.1:4178'));

