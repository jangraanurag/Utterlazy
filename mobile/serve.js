const http = require('http');
const fs = require('fs');
const path = require('path');
const PORT = 8000;
const DIR = path.join(__dirname, 'apks');

const MIME = {
  '.apk': 'application/vnd.android.package-archive',
  '.html': 'text/html',
};

http.createServer((req, res) => {
  let file = req.url === '/' ? '/index.html' : req.url;
  file = path.join(DIR, file);
  if (!fs.existsSync(file)) { res.writeHead(404); res.end('Not found'); return; }
  const ext = path.extname(file);
  const stat = fs.statSync(file);
  const headers = {
    'Content-Type': MIME[ext] || 'application/octet-stream',
    'Content-Length': stat.size,
  };
  if (ext === '.apk') {
    headers['Content-Disposition'] = 'attachment; filename="' + path.basename(file) + '"';
  }
  res.writeHead(200, headers);
  fs.createReadStream(file).pipe(res);
}).listen(PORT, '0.0.0.0', () => console.log(`Serving on http://0.0.0.0:${PORT}`));
