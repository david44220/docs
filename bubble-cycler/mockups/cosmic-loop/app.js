(function () {
  "use strict";

  var designs = [
    {id:"01",name:"AURA / ORBIT",palette:"INDIGO · ICE",layout:"orbit",theme:"night",
      headline:{fr:["La bulle","en mouvement."],en:["Bubbles","in motion."]},
      lead:{fr:"Le cycle publicitaire, bulle après bulle.",en:"An advertising cycle, bubble by bubble."},
      storyTitle:{fr:"Chaque bulle suit sa propre orbite.",en:"Every bubble follows its own orbit."},
      alt:{fr:"Orbite de bulles translucides dans un ciel indigo",en:"Translucent bubble orbit in an indigo sky"}},
    {id:"02",name:"PINK CURRENT",palette:"PÊCHE · ROSE IRISÉ",layout:"poster",theme:"rose",
      headline:{fr:["Faites","circuler","la lumière."],en:["Let the light","find its way","around."]},
      lead:{fr:"La publicité rencontre un nouveau courant.",en:"Advertising meets a new current."},
      storyTitle:{fr:"Un cycle clair, tout en couleur.",en:"A clear cycle, full of colour."},
      alt:{fr:"Bulles irisées flottant dans une lumière pêche et rose",en:"Iridescent bubbles drifting through peach and pink light"}},
    {id:"03",name:"GILDED LOOP",palette:"OBSIDIAN · OR",layout:"frame",theme:"gold",
      headline:{fr:["Le cycle","avant l’éclat."],en:["The cycle","before the spark."]},
      lead:{fr:"Un cycle publicitaire à suivre jusqu’à l’échéance.",en:"An advertising cycle to follow through to expiry."},
      storyTitle:{fr:"Le détail compte, jusqu’à l’expiration.",en:"Every detail matters, right through to expiry."},
      alt:{fr:"Orbite de verre et d’or sur un décor d’obsidienne",en:"Glass and gold orbit against an obsidian landscape"}},
    {id:"04",name:"BLUE PULSE",palette:"COBALT · CYAN",layout:"pulse",theme:"blue",
      headline:{fr:["Suivez","l’impulsion."],en:["Follow","the impulse."]},
      lead:{fr:"La publicité donne le rythme.",en:"Advertising sets the rhythm."},
      storyTitle:{fr:"La diffusion fait partie du mouvement.",en:"Ad delivery is part of the motion."},
      alt:{fr:"Anneau de bulles lumineuses bleu électrique",en:"Electric blue ring of luminous bubbles"}},
    {id:"05",name:"VERDANT",palette:"JADE · ÉMERAUDE",layout:"verdant",theme:"green",
      headline:{fr:["Une durée.","Un cycle."],en:["A set term.","A full cycle."]},
      lead:{fr:"La publicité accompagne chaque bulle.",en:"Advertising follows every bubble."},
      storyTitle:{fr:"La durée est au cœur du cycle.",en:"Duration is at the heart of the cycle."},
      alt:{fr:"Bulles de verre vertes en orbite au-dessus d’une eau sombre",en:"Green glass bubbles orbiting above dark water"}},
    {id:"06",name:"CORAL SHIFT",palette:"CORAIL · PRUNE",layout:"shift",theme:"coral",
      headline:{fr:["Chaque bulle","ouvre un cycle."],en:["Every bubble","starts a cycle."]},
      lead:{fr:"Une nouvelle diffusion, un nouveau départ.",en:"A new ad cycle. A new beginning."},
      storyTitle:{fr:"Suivez le mouvement jusqu’au terme.",en:"Follow the motion through to the end."},
      alt:{fr:"Traînée de bulles corail et prune en mouvement",en:"Coral and plum bubbles sweeping through the frame"}},
    {id:"07",name:"PEARL / SILVER",palette:"PERLE · ARGENT",layout:"pearl",theme:"pearl",
      headline:{fr:["Tout devient","plus limpide."],en:["Everything","comes into focus."]},
      lead:{fr:"Suivez la durée et l’échéance de chaque bulle.",en:"Follow each bubble’s term and expiry."},
      storyTitle:{fr:"Des informations claires, à chaque étape.",en:"Clear information at every step."},
      alt:{fr:"Sculpture de bulles nacrées et argentées sur fond blanc",en:"Pearlescent silver bubble sculpture on white"}},
    {id:"08",name:"COSMIC LOOP",palette:"VIOLET · NÉBULEUSE",layout:"cosmic",theme:"violet",
      headline:{fr:["Une autre","orbite."],en:["A different","orbit."]},
      lead:{fr:"Les bulles entrent dans leur cycle publicitaire.",en:"Bubbles enter their advertising cycle."},
      storyTitle:{fr:"Chaque échéance marque un nouveau point.",en:"Every expiry marks a new point."},
      alt:{fr:"Bulles planétaires en orbite dans une nébuleuse violette",en:"Planet-like bubbles orbiting through a violet nebula"}},
    {id:"09",name:"AFTERGLOW",palette:"AMBRE · ROSE DORÉ",layout:"afterglow",theme:"plum",
      headline:{fr:["Le cycle","continue de briller."],en:["Let the cycle","keep glowing."]},
      lead:{fr:"Du premier affichage à l’expiration.",en:"From first ad delivery to expiry."},
      storyTitle:{fr:"Le ROI se suit jusqu’à l’échéance.",en:"Track ROI through to expiry."},
      alt:{fr:"Bulles ambrées illuminées par une lumière de coucher de soleil",en:"Amber bubbles lit by the last light of sunset"}},
    {id:"10",name:"LIME / RUSH",palette:"CHARBON · LIME",layout:"rush",theme:"lime",
      headline:{fr:["Achetez.","Suivez le cycle."],en:["Buy a bubble.","Follow its cycle."]},
      lead:{fr:"Un parcours direct, du départ à l’échéance.",en:"A direct path from purchase to expiry."},
      storyTitle:{fr:"Le cycle, de l’achat à l’expiration.",en:"The cycle, from purchase to expiry."},
      alt:{fr:"Bulles sombres aux reflets vert lime sur fond charbon",en:"Dark bubbles with lime reflections on charcoal"}}
  ];

  var editionSlugs = {
    "01":"aura-orbit","02":"pink-current","03":"gilded-loop","04":"blue-pulse","05":"verdant",
    "06":"coral-shift","07":"pearl-silver","08":"cosmic-loop","09":"afterglow","10":"lime-rush"
  };
  function downloadName(d) {
    return "bubble-cycler-edition-" + d.id + "-" + editionSlugs[d.id] + ".zip";
  }
  function downloadHref(d) { return "https://bubble-cycler-studio.david44220.chatgpt.site/downloads/" + downloadName(d); }

  var app = document.getElementById("app");
  var edition = document.body.getAttribute("data-edition");
  var savedLang = "";
  try { savedLang = localStorage.getItem("bubble-cycler-language") || ""; } catch (e) {}
  var params = new URLSearchParams(window.location.search);
  var currentLang = params.get("lang") || savedLang || ((navigator.language || "").toLowerCase().indexOf("en") === 0 ? "en" : "fr");
  if (currentLang !== "en") currentLang = "fr";

  var copy = {
    fr:{
      navExperience:"L’application",navLoop:"Le cycle",navEditions:"Les éditions",switchLabel:"English",
      heroIntro:"Achetez une bulle, suivez sa durée et sa diffusion publicitaire, puis consultez le ROI cible annoncé de 160 % à son expiration.",
      explore:"Comprendre le cycle",more:"Explorer les éditions",scroll:"Faire défiler",
      storyKicker:"Bubble Cycler",cardsKicker:"Le fonctionnement",cardsTitle:"Une bulle. Une durée. Une échéance.",
      loopKicker:"Étapes du cycle",loopTitle:"De l’achat à l’expiration, suivez chaque étape.",
      closeKicker:"Avant d’acheter",closeTitle:"Comprendre le cycle, c’est essentiel.",
      closeBody:"Consultez la durée, les modalités publicitaires et les conditions de calcul avant tout achat.",
      back:"Retour aux 10 éditions",footer:"Bubble Cycler · application de bulles et de cycles publicitaires.",
      galleryEyebrow:"10 univers · 10 visuels originaux · FR / EN",
      galleryTitle:"Une bulle. Un cycle publicitaire.",
      galleryBody:"Bubble Cycler associe des bulles achetées à une diffusion publicitaire sur une durée définie. Le ROI cible annoncé est de 160 % à l’expiration; le résultat réel dépend des revenus publicitaires générés et n’est pas garanti.",
      galleryTitle2:"Choisissez votre univers.",note:"Application Bubble Cycler",
      open:"Ouvrir l’édition",go:"Voir la landing",downloadLanding:"Télécharger la landing",downloadCard:"Télécharger",disclaimer:"Les 160 % sont une cible indicative, pas un rendement garanti. Les revenus publicitaires peuvent varier et le capital est exposé à un risque de perte partielle ou totale."
    },
    en:{
      navExperience:"The app",navLoop:"The cycle",navEditions:"Editions",switchLabel:"Français",
      heroIntro:"Buy a bubble, follow its term and ad delivery, then view the stated 160% target ROI at expiry.",
      explore:"Understand the cycle",more:"Explore editions",scroll:"Scroll",
      storyKicker:"Bubble Cycler",cardsKicker:"How it works",cardsTitle:"One bubble. One term. One expiry.",
      loopKicker:"Cycle steps",loopTitle:"Follow each step, from purchase to expiry.",
      closeKicker:"Before you buy",closeTitle:"Understand the cycle first.",
      closeBody:"Review the duration, advertising terms and calculation rules before making a purchase.",
      back:"Back to all 10 editions",footer:"Bubble Cycler · bubbles and advertising cycles.",
      galleryEyebrow:"10 worlds · 10 original visuals · FR / EN",
      galleryTitle:"One bubble. One advertising cycle.",
      galleryBody:"Bubble Cycler links purchased bubbles to advertising delivery over a defined term. The stated target ROI is 160% at expiry; actual results depend on advertising revenue and are not guaranteed.",
      galleryTitle2:"Choose your world.",note:"Bubble Cycler app",
      open:"Open edition",go:"View landing",downloadLanding:"Download landing page",downloadCard:"Download",disclaimer:"160% is an indicative target, not a guaranteed return. Advertising revenue can vary, and capital is subject to partial or total loss."
    }
  };

  var productCards = [
    {title:{fr:"Bulle achetée",en:"Purchased bubble"},body:{fr:"Chaque bulle achetée déclenche la diffusion de publicité pendant son cycle.",en:"Each purchased bubble triggers advertising delivery during its cycle."}},
    {title:{fr:"Durée définie",en:"Defined term"},body:{fr:"Chaque bulle suit une durée déterminée et expire au terme de son cycle.",en:"Each bubble follows a defined term and expires at the end of its cycle."}},
    {title:{fr:"ROI cible : 160 %",en:"Target ROI: 160%"},body:{fr:"Cible annoncée à l’échéance. Le résultat réel dépend des revenus publicitaires et n’est pas garanti.",en:"Stated target at expiry. Actual results depend on advertising revenue and are not guaranteed."}}
  ];
  var productSteps = [
    {title:{fr:"Achetez une bulle",en:"Buy a bubble"},body:{fr:"Consultez le prix, la durée et les conditions du cycle avant l’achat.",en:"Review the price, term and cycle conditions before purchase."}},
    {title:{fr:"La publicité est diffusée",en:"Advertising is delivered"},body:{fr:"Chaque bulle achetée est associée à de la publicité pendant son cycle.",en:"Every purchased bubble is associated with advertising during its cycle."}},
    {title:{fr:"La bulle expire",en:"The bubble expires"},body:{fr:"Le ROI cible affiché est de 160 %. Le résultat réel peut différer; aucun rendement n’est garanti.",en:"The stated target ROI is 160%. Actual results may differ; no return is guaranteed."}}
  ];
  var productStory = {
    fr:"Chaque bulle achetée déclenche la diffusion de publicité pendant un cycle à durée définie. À son expiration, le ROI cible annoncé est de 160 %. Le résultat réel dépend des revenus publicitaires générés; aucun rendement n’est garanti et un risque de perte existe.",
    en:"Every purchased bubble triggers advertising delivery during a defined cycle. At expiry, the stated target ROI is 160%. Actual results depend on advertising revenue; no return is guaranteed and losses are possible."
  };

  function esc(text) {
    return String(text).replace(/[&<>"']/g, function (char) {
      return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[char];
    });
  }
  function t(item) { return esc(item[currentLang] || item.fr); }
  function logo() {
    return '<a class="brand" href="#top" aria-label="Bubble Cycler — accueil"><span class="brand__mark" aria-hidden="true"><i></i><i></i><i></i></span><span>Bubble<span class="brand__light"> Cycler</span></span></a>';
  }
  function languageButton() {
    return '<button class="language-switch" type="button" data-lang-switch aria-label="' + esc(copy[currentLang].switchLabel) + '">' + (currentLang === "fr" ? "EN" : "FR") + '<span aria-hidden="true">↗</span></button>';
  }
  function header(onLanding) {
    return '<header class="site-header">' + logo() +
      '<nav class="site-nav" aria-label="' + (currentLang === "fr" ? "Navigation principale" : "Main navigation") + '">' +
      (onLanding ? '<a href="#experience">' + esc(copy[currentLang].navExperience) + '</a><a href="#loop">' + esc(copy[currentLang].navLoop) + '</a>' : '') +
      '<a href="https://bubble-cycler-studio.david44220.chatgpt.site/#editions">' + esc(copy[currentLang].navEditions) + '</a></nav>' + languageButton() + '</header>';
  }
  function renderGallery() {
    document.body.className = "gallery-page";
    document.title = currentLang === "fr" ? "Bubble Cycler — 10 éditions" : "Bubble Cycler — 10 editions";
    document.documentElement.lang = currentLang;
    var cards = designs.map(function (d) {
      return '<article class="edition-card theme-' + d.theme + '"><a class="edition-card__preview" href="/editions/' + d.id + '/" aria-label="' + esc(copy[currentLang].open) + ' ' + d.id + ' — ' + esc(d.name) + '">' +
        '<div class="edition-card__image"><img src="assets/hero-' + d.id + '.webp" alt="' + esc(d.alt[currentLang]) + '" loading="lazy" decoding="async"><span class="edition-card__number">' + d.id + '</span><span class="edition-card__arrow" aria-hidden="true">↗</span></div>' +
        '<div class="edition-card__meta"><div><span class="edition-card__name">' + esc(d.name) + '</span><h2>' + esc(d.lead[currentLang]) + '</h2><span class="edition-card__palette">' + esc(d.palette) + '</span></div><span class="edition-card__link">' + esc(copy[currentLang].go) + '</span></div></a>' +
        '<a class="edition-card__download" href="' + downloadHref(d) + '" download="' + downloadName(d) + '" aria-label="' + esc(copy[currentLang].downloadLanding) + ' ' + d.id + ' — ' + esc(d.name) + '"><span>' + esc(copy[currentLang].downloadCard) + '</span><span aria-hidden="true">↓</span></a></article>';
    }).join("");
    app.innerHTML = header(false) +
      '<main><section class="gallery-hero"><img class="gallery-hero__image" src="assets/hero-01.webp" alt="" fetchpriority="high"><div class="gallery-hero__shade"></div><div class="gallery-hero__content"><p class="eyebrow">' + esc(copy[currentLang].galleryEyebrow) + '</p><h1>' + esc(copy[currentLang].galleryTitle) + '</h1><p class="gallery-hero__body">' + esc(copy[currentLang].galleryBody) + '</p><a class="button button--lime" href="#editions">' + esc(copy[currentLang].more) + '<span aria-hidden="true">↓</span></a></div><div class="gallery-hero__side">BUBBLE CYCLER<br>ADVERTISING CYCLES</div></section>' +
      '<section class="gallery-list" id="editions"><div class="gallery-list__heading"><div><p class="eyebrow">' + esc(copy[currentLang].note) + '</p><h2>' + esc(copy[currentLang].galleryTitle2) + '</h2></div><span class="gallery-list__count">01 — 10</span></div><div class="edition-grid">' + cards + '</div><p class="risk-note">' + esc(copy[currentLang].disclaimer) + '</p></section></main>' +
      '<footer class="site-footer gallery-footer"><a class="brand" href="#top"><span class="brand__mark" aria-hidden="true"><i></i><i></i><i></i></span><span>Bubble<span class="brand__light"> Cycler</span></span></a><p>' + esc(copy[currentLang].footer) + '</p><a href="#editions">' + esc(copy[currentLang].more) + ' ↑</a></footer>';
  }
  function renderLanding(d) {
    if (!d) { window.location.replace("/"); return; }
    document.body.className = "landing-page theme-" + d.theme + " layout-" + d.layout;
    document.body.style.setProperty("--page-bg", d.theme === "pearl" || d.theme === "rose" ? "#f5f3f0" : "#07090f");
    document.body.style.setProperty("--ink", d.theme === "pearl" ? "#17212b" : (d.theme === "rose" ? "#321d2c" : "#f7f5f0"));
    document.body.style.setProperty("--muted", d.theme === "pearl" ? "#59636d" : (d.theme === "rose" ? "#66505b" : "#b8bdc9"));
    document.documentElement.lang = currentLang;
    document.title = "Bubble Cycler — " + d.name;
    var c = copy[currentLang];
    var lines = d.headline[currentLang].map(function (line, i) { return '<span class="hero__line' + (i === d.headline[currentLang].length - 1 ? ' hero__line--accent' : '') + '">' + esc(line) + '</span>'; }).join("");
    var featureCards = productCards.map(function (card, i) {
      return '<article class="feature-card"><span class="feature-card__index">0' + (i + 1) + '</span><h3>' + t(card.title) + '</h3><p>' + t(card.body) + '</p><span class="feature-card__rule" aria-hidden="true"></span></article>';
    }).join("");
    var steps = productSteps.map(function (step, i) {
      return '<article class="step-card"><span class="step-card__index">0' + (i + 1) + '</span><div><h3>' + t(step.title) + '</h3><p>' + t(step.body) + '</p></div></article>';
    }).join("");
    app.innerHTML = header(true) +
      '<main><section class="hero hero--' + d.layout + '"><img class="hero__image" src="assets/hero-' + d.id + '.webp" alt="' + esc(d.alt[currentLang]) + '" fetchpriority="high"><div class="hero__overlay"></div><div class="hero__content"><p class="eyebrow"><span class="eyebrow__line"></span>' + esc(d.name) + ' <span class="eyebrow__dot">·</span> ' + d.id + ' / 10</p><h1>' + lines + '</h1><p class="hero__lead">' + t(d.lead) + '</p><p class="hero__intro">' + esc(c.heroIntro) + '</p><div class="hero__actions"><a class="button button--primary" href="#experience">' + esc(c.explore) + '<span aria-hidden="true">↘</span></a><a class="text-link" href="https://bubble-cycler-studio.david44220.chatgpt.site/#editions">' + esc(c.more) + '</a><a class="button button--download" href="' + downloadHref(d) + '" download="' + downloadName(d) + '">' + esc(c.downloadLanding) + '<span aria-hidden="true">↓</span></a></div></div><div class="hero__caption"><span>BC / ' + d.id + '</span><span>' + esc(d.palette) + '</span></div><a class="scroll-cue" href="#experience" aria-label="' + esc(c.scroll) + '"><span aria-hidden="true">↓</span></a></section>' +
      '<section class="experience section" id="experience"><div class="experience__copy"><p class="eyebrow">' + esc(c.storyKicker) + ' / ' + d.id + '</p><h2>' + t(d.storyTitle) + '</h2><p class="section-copy">' + t(productStory) + '</p></div><div class="experience__art"><img src="assets/hero-' + d.id + '.webp" alt="" loading="lazy" decoding="async"><span class="experience__art-tag">' + esc(d.name) + '</span><span class="experience__art-note">' + (currentLang === "fr" ? "CYCLE PUBLICITAIRE" : "ADVERTISING CYCLE") + '</span></div></section>' +
      '<section class="features section"><div class="section-heading"><div><p class="eyebrow">' + esc(c.cardsKicker) + '</p><h2>' + esc(c.cardsTitle) + '</h2></div><span class="section-heading__mark" aria-hidden="true">✳</span></div><div class="feature-grid">' + featureCards + '</div><p class="risk-note">' + esc(c.disclaimer) + '</p></section>' +
      '<section class="loop-section section" id="loop"><div class="loop-section__intro"><p class="eyebrow">' + esc(c.loopKicker) + '</p><h2>' + esc(c.loopTitle) + '</h2></div><div class="step-grid">' + steps + '</div></section>' +
      '<section class="closing-section"><div class="closing-section__content"><p class="eyebrow">' + esc(c.closeKicker) + '</p><h2>' + esc(c.closeTitle) + '</h2><p>' + esc(c.closeBody) + '</p><a class="button button--primary" href="https://bubble-cycler-studio.david44220.chatgpt.site/#editions">' + esc(c.back) + '<span aria-hidden="true">↗</span></a></div></section></main>' +
      '<footer class="site-footer"><a class="brand" href="#top"><span class="brand__mark" aria-hidden="true"><i></i><i></i><i></i></span><span>Bubble<span class="brand__light"> Cycler</span></span></a><p>' + esc(c.footer) + '</p><a href="https://bubble-cycler-studio.david44220.chatgpt.site/#editions">' + esc(c.more) + ' ↑</a></footer>';
  }

  document.addEventListener("click", function (event) {
    var switcher = event.target.closest("[data-lang-switch]");
    if (!switcher) return;
    currentLang = currentLang === "fr" ? "en" : "fr";
    try { localStorage.setItem("bubble-cycler-language", currentLang); } catch (e) {}
    var url = new URL(window.location.href);
    url.searchParams.set("lang", currentLang);
    try { history.replaceState(null, "", url.pathname + url.search + url.hash); } catch (e) {}
    render();
  });

  function render() {
    if (edition === "gallery") renderGallery();
    else renderLanding(designs.filter(function (d) { return d.id === edition; })[0]);
  }
  render();
})();
