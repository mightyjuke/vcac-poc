// Public notices are rendered by WordPress so they also work without JavaScript.
(() => {
  const labels={en:'Important notice','zh-Hant':'重要通知','zh-Hans':'重要通知'};
  const fallback={'zh-Hant':'此通知暫時只提供英文。','zh-Hans':'此通知暂时只提供英文。'};
  let banner=document.querySelector('#vcac-emergency');
  const started=Date.now();
  let timer;
  function update(){
    if(!banner)return;
    clearTimeout(timer);
    const remaining=Number(banner.dataset.remaining)*1000-(Date.now()-started);
    if(remaining<=0){banner.hidden=true;return;}
    const locale=Object.hasOwn(labels,document.documentElement.lang)?document.documentElement.lang:'en';
    const blocks=[...banner.querySelectorAll('[data-notice-language]')];
    const match=blocks.find(node=>node.dataset.noticeLanguage===locale);
    const chosen=match||blocks.find(node=>node.dataset.noticeLanguage==='en');
    blocks.forEach(node=>node.hidden=node!==chosen);
    banner.querySelector('.emergency-label').textContent=labels[locale];
    const note=banner.querySelector('.emergency-fallback');
    note.textContent=!match&&locale!=='en'?fallback[locale]:'';
    note.hidden=!note.textContent;
    banner.hidden=false;
    timer=setTimeout(update,Math.min(remaining,2147483000));
  }
  document.addEventListener('vcac:language',update);
  document.addEventListener('visibilitychange',update);
  window.addEventListener('pageshow',update);
  update();
  // Explicit local-only fixture. The JSON is excluded from the plugin ZIP.
  if(!banner&&['127.0.0.1','localhost'].includes(location.hostname)&&new URLSearchParams(location.search).get('emergency')==='demo'){
    fetch('demo/emergency.json').then(r=>r.json()).then(data=>{
      banner=document.createElement('section');banner.id='vcac-emergency';banner.className='emergency-banner';banner.dataset.remaining='3600';banner.dataset.tone=new URLSearchParams(location.search).get('tone')==='red'?'red':'yellow';
      banner.setAttribute('aria-label','Important notice');
      const badge=document.createElement('span');badge.className='emergency-label';banner.append(badge);
      for(const [lang,copy]of Object.entries(data)){
        const block=document.createElement('div');block.dataset.noticeLanguage=lang;block.lang=lang;
        const title=document.createElement('h2');title.className='emergency-title';title.textContent=copy.title;
        const message=document.createElement('p');message.className='emergency-message';message.textContent=copy.message;
        const link=document.createElement('a');link.className='emergency-link';link.href='#visit';link.textContent=copy.link;
        block.append(title,message,link);banner.append(block);
      }
      const note=document.createElement('p');note.className='emergency-fallback';banner.append(note);
      document.querySelector('.language-picker').after(banner);update();
    }).catch(()=>{});
  }
})();
