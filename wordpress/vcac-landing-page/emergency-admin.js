(() => {
 const form=document.querySelector('#vcac-notice-form');if(!form)return;
 const preview=document.querySelector('#notice-preview');
 const picker=document.querySelector('#notice-preview-language');
 const input=name=>form.elements.namedItem(name);
 const value=(lang,key)=>input(`notice[copy][${lang}][${key}]`).value.trim();
 function update(){
   const lang=picker.value;
   preview.dataset.tone=input('notice[tone]').value;
   const missing=lang!=='en'&&(!value(lang,'title')||!value(lang,'message'));
   const source=missing?'en':lang;
   preview.querySelector('#notice-preview-copy').lang=source;
   preview.querySelector('.emergency-label').textContent=lang==='en'?'Important notice':'重要通知';
   preview.querySelector('.emergency-title').textContent=value(source,'title')||'Your notice heading';
   preview.querySelector('.emergency-message').textContent=value(source,'message')||'Write a short message about the change and what visitors should do next.';
   const link=preview.querySelector('.emergency-link');
   link.hidden=!input('notice[url]').value.trim();
   link.textContent=value(source,'link')||({en:'Read update','zh-Hant':'查看最新安排','zh-Hans':'查看最新安排'}[source]);
   const fallback=preview.querySelector('.emergency-fallback');fallback.hidden=!missing;
   fallback.textContent=missing?(lang==='zh-Hant'?'此通知暫時只提供英文。':'此通知暂时只提供英文。'):'';
   const enabled=input('notice[enabled]').checked;
   input('notice[expires]').required=enabled;
   input('notice[copy][en][title]').required=enabled;
   input('notice[copy][en][message]').required=enabled;
   document.querySelector('#notice-preview-state').textContent=enabled?'Will show after saving, until the expiry time.':'Banner is off. Save to keep it hidden.';
 }
 form.addEventListener('input',update);form.addEventListener('change',update);update();
})();
