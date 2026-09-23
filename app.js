// Autoplay the silent decorative loop, without a video tap target or controls.
const heroStage = document.querySelector('.hero-stage');
const heroVideo = document.querySelector('#hero-video');
// Fill the first landscape desktop screen through the worship strip's bottom divider.
// Measure real header/notice heights so language changes and WP's toolbar fit too.
if (heroStage) {
  const services = document.querySelector('.hero .services');
  let sizingFrame = 0;
  const sizeHero = () => {
    sizingFrame = 0;
    if (!services || !heroStage.getClientRects().length) return;
    const surrounding = Math.round((heroStage.getBoundingClientRect().top + window.scrollY
      + services.getBoundingClientRect().height) * 100) / 100;
    const value = `${surrounding}px`;
    if (heroStage.style.getPropertyValue('--hero-surrounding-height') !== value) {
      heroStage.style.setProperty('--hero-surrounding-height', value);
    }
  };
  const queueHeroSize = () => {
    if (!sizingFrame) sizingFrame = requestAnimationFrame(sizeHero);
  };
  if ('ResizeObserver' in window) {
    const sizingObserver = new ResizeObserver(queueHeroSize);
    [document.body, services, document.querySelector('.header'), document.querySelector('.language-picker')]
      .filter(Boolean).forEach(node => sizingObserver.observe(node));
  }
  window.addEventListener('resize', queueHeroSize, {passive:true});
  window.addEventListener('pageshow', queueHeroSize);
  document.addEventListener('vcac:language', queueHeroSize);
  if (document.fonts) document.fonts.ready.then(queueHeroSize);
  sizeHero();
}
if (heroStage && heroVideo && heroVideo.dataset.src !== '') {
  let heroVisible = false;
  let playbackRequest = 0;
  heroVideo.muted = true;
  heroVideo.defaultMuted = true;
  heroVideo.playsInline = true;
  async function syncHeroPlayback() {
    const request = ++playbackRequest;
    if (!heroVisible || document.hidden) {
      heroVideo.pause();
      return;
    }
    if (!heroVideo.getAttribute('src')) heroVideo.src = heroVideo.dataset.src || 'assets/VCAC-cover-V5.mp4';
    try {
      await heroVideo.play();
      if (!heroVisible || document.hidden) heroVideo.pause();
    } catch {
      if (request === playbackRequest) heroStage.classList.remove('is-playing');
    }
  }
  heroVideo.addEventListener('playing', () => heroStage.classList.add('is-playing'));
  heroVideo.addEventListener('error', () => heroStage.classList.remove('is-playing'));
  new IntersectionObserver(([entry]) => {
    heroVisible = entry.isIntersecting;
    syncHeroPlayback();
  }, {threshold:0}).observe(heroStage);
  document.addEventListener('visibilitychange', syncHeroPlayback);
}

const backToTop = document.querySelector('.back-to-top');
if (backToTop) {
  const updateBackToTop = () => backToTop.classList.toggle('is-visible', window.scrollY > Math.max(500, window.innerHeight * 0.75));
  updateBackToTop();
  window.addEventListener('scroll', updateBackToTop, {passive:true});
  window.addEventListener('resize', updateBackToTop);
}

