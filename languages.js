// All translations are local prototype copy; no external translation service is used.
const english = {};
document.querySelectorAll('[data-i18n]').forEach(el => english[el.dataset.i18n] = el.textContent);
document.querySelectorAll('[data-i18n-html]').forEach(el => english[el.dataset.i18nHtml] = el.innerHTML);
const translations = {
'en': {...english, title:'A place to belong · VCAC', homeLabel:'VCAC home', navigation:'Main navigation', languageLabel:'Display language', status:'Page language: English'},
'zh-Hant': {
title:'歡迎你 · 溫哥華華人宣道會',skip:'跳至主要內容',church:'溫哥華華人宣道會',headerChurch:'溫哥華華人宣道會',identity:'溫哥華華人宣道會 · 乃街堂',plan:'計劃到訪',vision:'在基督裏合一<br><em>活出神的使命</em>',mission:'我們教會的使命是敬拜耶穌、裝備門徒、建立教會和傳揚整全福音。',join:'這個主日，歡迎你來',address:'溫哥華 Knight Street 3330 號',sunday:'主日崇拜',place:'這裏有你的位置。',english:'英語',cantonese:'粵語',mandarin:'國語',am:'上午',englishLink:'英語事工',cantoneseLink:'粵語事工',mandarinLink:'國語事工',first:'第一次參加主日崇拜',welcome:'我們期待<br><em>與你見面。</em>',invitation:'不必等到所有疑問都有答案。歡迎你帶着真實的自己來，讓我們一起踏出下一步。',expect:'到訪須知（英文）',home:'歡迎來到我們當中。',find:'教會地址',fullAddress:'3330 Knight Street<br>溫哥華，卑詩省 V5N 3K8',worship:'主日崇拜時間',times:'英語 — 上午 9:30<br>粵語 — 上午 8:00 及 11:15<br>國語 — 上午 11:15',parking:'泊車資訊',parkingInfo:'可使用教會停車場及附近街道的泊車位。請遵守路旁標誌，並勿阻塞鄰居的車道。',directions:'查看路線',local:'網站設計預覽 · 並非正式網站',homeLabel:'返回 VCAC 首頁',navigation:'主要導覽',languageLabel:'顯示語言',status:'頁面語言：繁體中文（粵語）'
},
'zh-Hans': {
title:'欢迎你 · 温哥华华人宣道会',skip:'跳至主要内容',church:'溫哥華華人宣道會',headerChurch:'溫哥華華人宣道會',identity:'温哥华华人宣道会 · 乃街堂',plan:'计划到访',vision:'在基督里合一<br><em>活出神的使命</em>',mission:'我们教会的使命是敬拜耶稣、装备门徒、建立教会和传扬整全福音。',join:'这个主日，欢迎你来',address:'温哥华 Knight Street 3330 号',sunday:'主日崇拜',place:'这里有你的位置。',english:'英语',cantonese:'粤语',mandarin:'国语',am:'上午',englishLink:'英语事工',cantoneseLink:'粤语事工',mandarinLink:'国语事工',first:'第一次参加主日崇拜',welcome:'我们期待<br><em>与你见面。</em>',invitation:'不必等到所有疑问都有答案。欢迎你带着真实的自己来，让我们一起踏出下一步。',expect:'到访须知（英文）',home:'欢迎来到我们当中。',find:'教会地址',fullAddress:'3330 Knight Street<br>温哥华，不列颠哥伦比亚省 V5N 3K8',worship:'主日崇拜时间',times:'英语 — 上午 9:30<br>粤语 — 上午 8:00 及 11:15<br>国语 — 上午 11:15',parking:'停车信息',parkingInfo:'可使用教会停车场及附近街道的停车位。请遵守路旁标志，并勿阻塞邻居的车道。',directions:'查看路线',local:'网站设计预览 · 并非正式网站',homeLabel:'返回 VCAC 首页',navigation:'主要导航',languageLabel:'显示语言',status:'页面语言：简体中文（国语）'
}
};
function setLanguage(language, announce = true) {
  if (!Object.hasOwn(translations, language)) language = 'en';
  const copy = translations[language];
  document.documentElement.lang = language;
  document.title = copy.title;
  document.querySelectorAll('[data-i18n]').forEach(el => el.textContent = copy[el.dataset.i18n] ?? english[el.dataset.i18n]);
  // Markup comes only from the fixed dictionaries above, never visitor input.
  document.querySelectorAll('[data-i18n-html]').forEach(el => el.innerHTML = copy[el.dataset.i18nHtml] ?? english[el.dataset.i18nHtml]);
  document.querySelectorAll('[data-language]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.language === language)));
  document.querySelector('.brand').setAttribute('aria-label', copy.homeLabel);
  document.querySelector('.header nav').setAttribute('aria-label', copy.navigation);
  document.querySelector('.language-picker').setAttribute('aria-label', copy.languageLabel);
  if (announce) document.querySelector('#language-status').textContent = copy.status;
  try { localStorage.setItem('vcac-display-language', language); } catch { /* Works without storage too. */ }
}
document.querySelectorAll('[data-language]').forEach(button => button.addEventListener('click', () => setLanguage(button.dataset.language)));
let savedLanguage = 'en';
try { savedLanguage = localStorage.getItem('vcac-display-language') || 'en'; } catch { /* Default to English. */ }
setLanguage(savedLanguage, false);






