// Autoplay the silent decorative loop, without a video tap target or controls.
const heroStage = document.querySelector('.hero-stage');
const heroVideo = document.querySelector('#hero-video');
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

