// All translations are local prototype copy; no external translation service is used.
const english = {};
document.querySelectorAll('[data-i18n]').forEach(el => english[el.dataset.i18n] = el.textContent);
document.querySelectorAll('[data-i18n-html]').forEach(el => english[el.dataset.i18nHtml] = el.innerHTML);
const translations = {
'en': {...english, title:'Vancouver Chinese Alliance Church', homeLabel:'VCAC home', navigation:'Main navigation', languageLabel:'Display language', status:'Page language: English'},
'zh-Hant': {
title:'Vancouver Chinese Alliance Church',skip:'跳至主要內容',church:'溫哥華華人宣道會',headerChurch:'溫哥華華人宣道會',identity:'溫哥華華人宣道會 · 乃街教會',plan:'計劃到訪',vision:'在基督裏合一<br><em>活出神的使命</em>',mission:'我們教會的使命是敬拜耶穌、裝備門徒、建立教會和傳揚整全福音。',join:'這個主日，歡迎你來',address:'溫哥華乃街 3330 號',sunday:'主日崇拜',place:'這裏有你的位置。',english:'英語',cantonese:'粵語',mandarin:'國語',am:'上午',englishLink:'英語事工',cantoneseLink:'粵語事工',mandarinLink:'國語事工',first:'第一次參加主日崇拜',welcome:'我們期待<br><em>與你見面。</em>',invitation:'不必等到所有疑問都有答案。歡迎你帶着真實的自己來，讓我們一起踏出下一步。',expect:'到訪須知（英文）',home:'歡迎來到我們當中。',find:'教會地址',fullAddress:'3330 Knight Street<br>溫哥華，卑詩省 V5N 3K8',worship:'主日崇拜時間',times:'粵語 — 上午 8:00 及 11:15<br>英語 — 上午 9:30<br>國語 — 上午 11:15',parking:'泊車資訊',parkingInfo:'可使用教會停車場及附近街道的泊車位。請遵守路旁標誌，並勿阻塞鄰居的車道。',directions:'查看路線',homeLabel:'返回 VCAC 首頁',navigation:'主要導覽',languageLabel:'顯示語言',status:'頁面語言：繁體中文（粵語）'
},
'zh-Hans': {
title:'Vancouver Chinese Alliance Church',skip:'跳至主要内容',church:'溫哥華華人宣道會',headerChurch:'溫哥華華人宣道會',identity:'温哥华华人宣道会 · 乃街教会',plan:'计划到访',vision:'在基督里合一<br><em>活出神的使命</em>',mission:'我们教会的使命是敬拜耶稣、装备门徒、建立教会和传扬整全福音。',join:'这个主日，欢迎你来',address:'温哥华乃街 3330 号',sunday:'主日崇拜',place:'这里有你的位置。',english:'英语',cantonese:'粤语',mandarin:'国语',am:'上午',englishLink:'英语事工',cantoneseLink:'粤语事工',mandarinLink:'国语事工',first:'第一次参加主日崇拜',welcome:'我们期待<br><em>与你见面。</em>',invitation:'不必等到所有疑问都有答案。欢迎你带着真实的自己来，让我们一起踏出下一步。',expect:'到访须知（英文）',home:'欢迎来到我们当中。',find:'教会地址',fullAddress:'3330 Knight Street<br>温哥华，不列颠哥伦比亚省 V5N 3K8',worship:'主日崇拜时间',times:'粤语 — 上午 8:00 及 11:15<br>英语 — 上午 9:30<br>国语 — 上午 11:15',parking:'停车信息',parkingInfo:'可使用教会停车场及附近街道的停车位。请遵守路旁标志，并勿阻塞邻居的车道。',directions:'查看路线',homeLabel:'返回 VCAC 首页',navigation:'主要导航',languageLabel:'显示语言',status:'页面语言：简体中文（国语）'
}
};
Object.assign(translations['zh-Hant'], {ministryShortcut:'選擇你的堂會',communityEyebrow:'一起生活',communityTitle:'社區事工與活動',communityIntro:'不止於主日。一起連繫、學習和成長。',communityAll:'查看所有活動',backHome:'返回歡迎頁面',filterAll:'所有活動',filterEvents:'即將舉行',filterProgrammes:'恆常活動',updatesTitle:'最新消息',updatesIntro:'與你的堂會保持聯繫。',updatesAll:'查看堂會所有消息',expect:'新來賓資料',backTop:'返回頁首'});
Object.assign(translations['zh-Hans'], {ministryShortcut:'选择你的堂会',communityEyebrow:'一起生活',communityTitle:'社区事工与活动',communityIntro:'不止于主日。一起联系、学习和成长。',communityAll:'查看所有活动',backHome:'返回欢迎页面',filterAll:'所有活动',filterEvents:'即将举行',filterProgrammes:'常规活动',updatesTitle:'最新消息',updatesIntro:'与你的堂会保持联系。',updatesAll:'查看堂会所有消息',expect:'新来宾资料',backTop:'返回顶部'});
function setLanguage(language, announce = true, remember = true) {
  if (!Object.hasOwn(translations, language)) language = 'en';
  const copy = translations[language];
  document.documentElement.lang = language;
  document.title = copy.title;
  document.querySelectorAll('[data-i18n]').forEach(el => el.textContent = copy[el.dataset.i18n] ?? english[el.dataset.i18n]);
  // Markup comes only from the fixed dictionaries above, never visitor input.
  document.querySelectorAll('[data-i18n-html]').forEach(el => el.innerHTML = copy[el.dataset.i18nHtml] ?? english[el.dataset.i18nHtml]);
  document.querySelectorAll('[data-language]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.language === language)));
  document.querySelector('.brand').setAttribute('aria-label', copy.homeLabel);
  const headerNavigation = document.querySelector('.header nav');
  if (headerNavigation) headerNavigation.setAttribute('aria-label', copy.navigation);
  document.querySelector('.language-picker').setAttribute('aria-label', copy.languageLabel);
  if (announce) document.querySelector('#language-status').textContent = copy.status;
  if (remember) {
    try { localStorage.setItem('vcac-display-language', language); } catch { /* Works without storage too. */ }
  }
  // Older builds exposed the display choice as a WordPress query variable.
  // Read those existing links once, then keep the preference in local storage
  // so a reload cannot change WordPress's front-page query.
  try {
    const url = new URL(location.href);
    if (url.searchParams.has('vcac_lang')) {
      url.searchParams.delete('vcac_lang');
      history.replaceState(history.state,'',url);
    }
  } catch { /* Language still works without history. */ }
  document.dispatchEvent(new CustomEvent('vcac:language', {detail:{language}}));
}
document.querySelectorAll('[data-language]').forEach(button => button.addEventListener('click', () => setLanguage(button.dataset.language)));

function detectBrowserLanguage() {
  const preferences = navigator.languages?.length ? navigator.languages : [navigator.language || 'en'];
  for (const preference of preferences) {
    const tag = String(preference).replace(/_/g, '-').toLowerCase();
    if (tag === 'en' || tag.startsWith('en-')) return 'en';
    if (tag.startsWith('yue') || /^(zh|cmn)(-|$)/.test(tag)) {
      if (/(^|-)(hant|hk|tw|mo)(-|$)/.test(tag) || tag.startsWith('yue')) return 'zh-Hant';
      if (/(^|-)(hans|cn|sg)(-|$)/.test(tag)) return 'zh-Hans';
      try {
        return new Intl.Locale(tag).maximize().script === 'Hant' ? 'zh-Hant' : 'zh-Hans';
      } catch {
        return 'zh-Hans';
      }
    }
  }
  return 'en';
}

let savedLanguage = null;
try { savedLanguage = localStorage.getItem('vcac-display-language'); } catch { /* Detect from the browser instead. */ }
const requestedLanguage = new URLSearchParams(location.search).get('vcac_lang');
const hasRequestedLanguage = Object.hasOwn(translations, requestedLanguage);
setLanguage(hasRequestedLanguage ? requestedLanguage : Object.hasOwn(translations, savedLanguage) ? savedLanguage : detectBrowserLanguage(), false, hasRequestedLanguage);
