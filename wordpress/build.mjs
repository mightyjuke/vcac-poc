import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import {fileURLToPath} from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const source = path.dirname(here);
const output = path.resolve(process.argv[2] || path.join(source, 'dist', 'wordpress'));
const target = path.join(output, 'vcac-landing-page');
await fs.mkdir(path.join(target, 'site', 'assets'), {recursive:true});
const phpFiles = ['vcac-landing-page.php', 'page-template.php', 'content-feed.php', 'calendar-adapter.php', 'image-crop.php', 'admin-crop.js', 'admin-crop.css', 'emergency.php', 'emergency-admin.js', 'emergency-admin.css'];
for (const name of phpFiles) {
  await fs.copyFile(path.join(here, 'vcac-landing-page', name), path.join(target, name));
}
const files = ['index.html','style.css','hero.css','theme.css','readability.css','community.css','app.js','languages.js','community.js','emergency.css','emergency.js',
  'assets/congregation.jpg','assets/logo.png','assets/questrial-regular.ttf'];
const manifest = {builtAt:new Date().toISOString(), files:{}};
for (const name of files) {
  let bytes = await fs.readFile(path.join(source, name));
  if (name === 'index.html') {
    const html = bytes.toString();
    if (!html.includes('<video id="hero-video"')) throw new Error('Hero markup changed; review the adapter.');
    bytes = Buffer.from('<?php http_response_code(404); exit; ?>\n' + html.replace('<video id="hero-video"', '<video id="hero-video" data-src="%%VCAC_HERO_VIDEO_URL%%"'));
  }
  if (name === 'app.js') {
    const js = bytes.toString();
    const localVideo = "heroVideo.dataset.src || 'assets/VCAC-cover-V5.mp4'";
    if (!js.includes(localVideo)) throw new Error('Video source changed; review the adapter.');
    bytes = Buffer.from(js.replace(localVideo, 'heroVideo.dataset.src'));
  }
  const destination = name === 'index.html' ? 'document.php' : name;
  await fs.writeFile(path.join(target, 'site', destination), bytes);
  manifest.files['site/' + destination] = crypto.createHash('sha256').update(bytes).digest('hex');
}
for (const name of phpFiles) {
  manifest.files[name] = crypto.createHash('sha256').update(await fs.readFile(path.join(target,name))).digest('hex');
}
manifest.build = crypto.createHash('sha256').update(JSON.stringify(manifest.files)).digest('hex').slice(0,12);
await fs.writeFile(path.join(target,'build-manifest.json'), JSON.stringify(manifest,null,2)+'\n');
await fs.copyFile(path.join(here,'README.md'),path.join(target,'README.md'));
await fs.copyFile(path.join(here,'vcac-landing-page','THIRD_PARTY_NOTICES.md'),path.join(target,'THIRD_PARTY_NOTICES.md'));
await fs.cp(path.join(source,'licenses'),path.join(target,'licenses'),{recursive:true});
console.log(JSON.stringify({target,build:manifest.build,files:Object.keys(manifest.files).length},null,2));
