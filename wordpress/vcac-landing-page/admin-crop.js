(() => {
  'use strict';
  const box = document.querySelector('.vcac-card-crop');
  if (!box) return;
  const fit = box.querySelector('[name="vcac_image_fit"]');
  const x = box.querySelector('[name="vcac_image_x"]');
  const y = box.querySelector('[name="vcac_image_y"]');
  const image = box.querySelector('.vcac-crop-preview img');
  const message = box.querySelector('.vcac-crop-message');
  const controls = box.querySelector('.vcac-crop-controls');
  function render() {
    image.style.objectFit = fit.value === 'contain' ? 'contain' : 'cover';
    image.style.objectPosition = fit.value === 'contain' ? '50% 50%' : `${x.value}% ${y.value}%`;
    controls.hidden = fit.value === 'contain';
    for (const input of [x,y]) box.querySelector(`output[for="${input.id}"]`).textContent = `${input.value}%`;
  }
  [fit,x,y].forEach(input => input.addEventListener('input',render));
  fit.addEventListener('change',render);
  box.querySelector('.vcac-crop-reset').addEventListener('click',() => { x.value='50'; y.value='50'; render(); });
  image.addEventListener('error',() => { image.hidden=true; message.textContent='Image preview unavailable. Check the featured image, then save and reload.'; });
  image.addEventListener('load',() => { image.hidden=false; });
  let selectedId = Number(box.dataset.thumbnailId) || 0;
  let request = 0;
  function updateImage(id) {
    id = Math.max(0,Number(id)||0);
    if (id === selectedId) return;
    selectedId=id;
    const current=++request;
    image.hidden=true;
    if (!id) { image.removeAttribute('src'); message.textContent='Choose a featured image to see its crop here.'; return; }
    message.textContent='Loading featured image…';
    if (!window.wp?.media?.attachment) { message.textContent='Save and reload to preview the new featured image.'; return; }
    const attachment=wp.media.attachment(id);
    attachment.fetch().then(() => {
      if(current!==request) return;
      const data=attachment.toJSON();
      const url=data.sizes?.large?.url || data.url;
      if (!url || !/^https?:/i.test(url)) throw new Error('Invalid image');
      image.src=url;
      image.hidden=false;
      message.textContent='Adjust the sliders to keep the important part visible.';
    }).catch(() => {
      if(current===request) message.textContent='Image preview unavailable. Save and reload to try again.';
    });
  }
  // Classic editor / WPBakery / MEC featured-image box.
  const featured = document.getElementById('postimagediv');
  if (featured) {
    const sync=()=>{const input=featured.querySelector('#_thumbnail_id');if(input)updateImage(input.value);};
    new MutationObserver(sync).observe(featured,{childList:true,subtree:true,attributes:true,attributeFilter:['value']});
    featured.addEventListener('change',sync);
    sync();
  }
  // Also follow the block editor's featured image, when that editor is present.
  if (window.wp?.data?.subscribe) {
    wp.data.subscribe(() => {
      const editor=wp.data.select('core/editor');
      const id=editor?.getEditedPostAttribute?.('featured_media');
      if(id!==undefined)updateImage(id);
    });
  }
  render();
})();
