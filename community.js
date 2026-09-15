// The packaged plugin embeds public feed data. No private REST credentials or remote scraping.
(() => {
  'use strict';
  const copy = {
    en: {from:'From the English ministry',visit:'Visit the English ministry',emptyCommunity:'No current opportunities to show here. Please check your ministry’s events.',emptyUpdates:'No current updates to show here.',unavailable:'Some information is temporarily unavailable. Please visit your ministry for the latest details.',details:'View details',events:'Ministry events',ongoing:'Ongoing programme',full:'Full / waiting list',source:'Details in',languages:{en:'English','zh-Hant':'Traditional Chinese','zh-Hans':'Simplified Chinese'},filter:'Community opportunities',demo:'Local design preview — example content for testing, not a live event listing.'},
    'zh-Hant': {from:'來自粵語事工',visit:'前往粵語事工',emptyCommunity:'這裏暫時沒有活動資訊，請查看堂會的聚會及活動。',emptyUpdates:'這裏暫時沒有最新消息。',unavailable:'部分資訊暫時無法顯示，請前往堂會網站查看最新詳情。',details:'查看詳情',events:'堂會聚會及活動',ongoing:'恆常活動',full:'名額已滿／候補',source:'詳情語言：',languages:{en:'英語','zh-Hant':'繁體中文','zh-Hans':'簡體中文'},filter:'社區事工與活動',demo:'本機設計預覽 — 範例內容僅供測試，並非即時活動資訊。'},
    'zh-Hans': {from:'来自国语事工',visit:'前往国语事工',emptyCommunity:'这里暂时没有活动信息，请查看堂会的聚会及活动。',emptyUpdates:'这里暂时没有最新消息。',unavailable:'部分信息暂时无法显示，请前往堂会网站查看最新详情。',details:'查看详情',events:'堂会聚会及活动',ongoing:'常规活动',full:'名额已满／候补',source:'详情语言：',languages:{en:'英语','zh-Hant':'繁体中文','zh-Hans':'简体中文'},filter:'社区事工与活动',demo:'本机设计预览 — 示例内容仅供测试，并非实时活动信息。'}
  };
  let payload = null;
  let filter = 'all';
  let demo = false;
  const directory = new URLSearchParams(location.search).get('vcac_view') === 'community';
  document.body.classList.toggle('community-directory', directory);
  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }
  function safeUrl(value) {
    if (typeof value !== 'string' || !value.trim()) return null;
    try { const url = new URL(value,location.href); return ['http:','https:'].includes(url.protocol) ? url.href : null; } catch { return null; }
  }
  function sourceFor(language) {
    const branch = {en:'english','zh-Hant':'cantonese','zh-Hans':'mandarin'}[language];
    const fallback = `https://www.vcac.ca/${branch}/`;
    return payload?.sources?.[language] || {home:fallback,visitors:fallback+'visitors/',events:fallback+'events/',updates:fallback+'about/announcements/'};
  }
  function setLink(id,href,text) {
    const link = document.getElementById(id);
    if (!link) return;
    const url = safeUrl(href);
    link.hidden = !url;
    if (url) link.href = url;
    if (text) link.textContent = text;
  }
  function dateText(timestamp,language,includeTime=false) {
    if (!Number.isFinite(Number(timestamp)) || Number(timestamp)<=0) return '';
    const date = new Date(Number(timestamp)*1000);
    if (!Number.isFinite(date.getTime())) return '';
    return new Intl.DateTimeFormat(language,{timeZone:'America/Vancouver',year:'numeric',month:'short',day:'numeric',...(includeTime?{hour:'numeric',minute:'2-digit'}:{})}).format(date);
  }
  function addStatus(targetId,message,source,label) {
    const target = document.getElementById(targetId);
    target.replaceChildren();
    if (!message) return;
    target.append(document.createTextNode(message+' '));
    const url = safeUrl(source);
    if (url) { const link = element('a','',label); link.href=url; target.append(link); }
  }
  function card(item,language,labels) {
    const article = element('article','community-card');
    const imageUrl = safeUrl(item.image);
    if (imageUrl) {
      const img = element('img'); img.src=imageUrl; img.alt=item.imageAlt || ''; img.loading='lazy'; img.width=600; img.height=400;
      img.addEventListener('error',()=>img.remove(),{once:true}); article.append(img);
    }
    const body = element('div','card-content');
    const status = item.status==='full' ? labels.full : item.kind==='programme' ? labels.ongoing : '';
    if(status) body.append(element('span','feed-badge',status));
    const schedule = item.start ? dateText(item.start,language,true) : item.schedule;
    if(schedule) body.append(element('p','card-meta',schedule));
    const heading=element('h3','',item.title); heading.lang=item.language || language; body.append(heading);
    if(item.summary) { const summary=element('p','',item.summary); summary.lang=item.language || language; body.append(summary); }
    if(item.language && item.language!==language) body.append(element('p','source-note',`${labels.source} ${labels.languages[item.language] || item.language}`));
    const url=safeUrl(item.url);
    if(url) { const link=element('a','text-link',labels.details); link.href=url; link.setAttribute('aria-label',`${labels.details}: ${item.title}`); body.append(link); }
    article.append(body); return article;
  }
  function render() {
    const language = Object.hasOwn(copy,document.documentElement.lang) ? document.documentElement.lang : 'en';
    const labels=copy[language]; const source=sourceFor(language); const data=payload?.locales?.[language];
    const available=data && Array.isArray(data.community) && Array.isArray(data.updates);
    const community=available ? data.community.filter(item=>item && item.title && safeUrl(item.url) && item.status!=='cancelled') : [];
    const filtered=community.filter(item=>filter==='all'||item.kind===filter);
    const cards=document.getElementById('community-cards'); cards.replaceChildren(...(directory?filtered:community.slice(0,3)).map(item=>card(item,language,labels)));
    const updates=available ? data.updates.filter(item=>item && item.title && safeUrl(item.url)).slice(0,3) : [];
    document.getElementById('update-list').replaceChildren(...updates.map(item=>{
      const row=element('article','update-row'); const time=element('time','',dateText(item.published,language));
      if(item.published>0) time.dateTime=new Date(item.published*1000).toISOString();
      const body=element('div'); const heading=element('h3'); const link=element('a','',item.title); link.href=safeUrl(item.url); heading.append(link); body.append(heading);
      if(item.summary) body.append(element('p','',item.summary)); row.append(time,body); return row;
    }));
    const failed=!available || data.status==='unavailable'; const partial=data?.status==='partial';
    addStatus('community-status',failed||partial?labels.unavailable:!(directory?filtered:community).length?labels.emptyCommunity:'',source.events,labels.events);
    addStatus('updates-status',failed||partial?labels.unavailable:!updates.length?labels.emptyUpdates:'',source.home,labels.visit);
    document.getElementById('updates-source').textContent=labels.from;
    setLink('ministry-home',source.home,labels.visit); setLink('updates-all',source.updates);
    const visitor=document.querySelector('#visit .button'); const visitorUrl=safeUrl(source.visitors); if(visitor){visitor.hidden=!visitorUrl;if(visitorUrl)visitor.href=visitorUrl;}
    document.querySelectorAll('[data-ministry]').forEach(link=>{ const url=safeUrl(sourceFor(link.dataset.ministry).home); link.hidden=!url;if(url)link.href=url; });
    const serviceLocales=['zh-Hant','en','zh-Hans']; document.querySelectorAll('.services>a').forEach((link,i)=>{const url=safeUrl(sourceFor(serviceLocales[i]).home);if(url)link.href=url;else link.removeAttribute('href');});
    const allUrl=new URL(location.href); allUrl.searchParams.set('vcac_view','community'); allUrl.searchParams.set('vcac_lang',language); allUrl.hash='community';
    const backUrl=new URL(location.href); backUrl.searchParams.delete('vcac_view'); backUrl.searchParams.set('vcac_lang',language); backUrl.hash='top';
    setLink('community-all',allUrl.href); setLink('community-back',backUrl.href);
    if(directory){
      document.querySelectorAll('.brand,.footer-brand').forEach(link=>link.href=backUrl.href);
      const visitUrl=new URL(backUrl);visitUrl.hash='visit';
      document.querySelector('.visit-link').href=visitUrl.href;
      document.getElementById('community-title').setAttribute('aria-level','1');
    }
    document.getElementById('community-all').hidden=directory;
    document.getElementById('community-back').hidden=!directory;
    const filters=document.getElementById('community-filters'); filters.hidden=!directory; filters.setAttribute('aria-label',labels.filter);
    document.querySelectorAll('[data-community-filter]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.communityFilter===filter)));
    if(demo) { let notice=document.getElementById('feed-demo-notice'); if(!notice){notice=element('p','demo-notice');notice.id='feed-demo-notice';document.body.prepend(notice);}notice.textContent=labels.demo; }
  }
  document.addEventListener('vcac:language',render);
  document.querySelectorAll('[data-community-filter]').forEach(button=>button.addEventListener('click',()=>{filter=button.dataset.communityFilter;render();}));
  const embedded=document.getElementById('vcac-feed-data');
  if(embedded){try{payload=JSON.parse(embedded.textContent);}catch{/* Use the visible ministry fallback. */}render();}
  else if(['127.0.0.1','localhost','[::1]'].includes(location.hostname)&&new URLSearchParams(location.search).get('demo')==='1'){
    demo=true; render(); fetch('demo/community.json').then(response=>{if(!response.ok)throw new Error('Preview unavailable');return response.json();}).then(data=>{payload=data;render();}).catch(()=>render());
  }else{render();}
})();
