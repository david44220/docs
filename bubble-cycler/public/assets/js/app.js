/*!
 * BubbleCycle — progressive enhancements. Every page works without JS;
 * the server enforces every rule (ad timer included).
 */
(() => {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const csrf = () => $('meta[name="csrf-token"]')?.content || '';
  const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Money helpers (mirror money() / to_units() in PHP) ----------- */
  const fmtMoney = (units, symbol = '$') => {
    const negative = units < 0;
    const scaled = Math.floor((Math.abs(units) + 50) / 100); // 4 decimals, half up
    const whole = Math.floor(scaled / 10000);
    let fraction = String(scaled % 10000).padStart(4, '0').replace(/0+$/, '');
    fraction = fraction.padEnd(2, '0');
    return (negative ? '−' : '') + symbol + whole.toLocaleString('en-US') + '.' + fraction;
  };
  const parseMoney = (text) => {
    let s = String(text || '').replace(/[\s $€£]/g, '');
    s = s.includes(',') && !s.includes('.') ? s.replace(',', '.') : s.replace(/,/g, '');
    const m = /^(\d{0,12})(?:\.(\d{0,6}))?$/.exec(s);
    if (!m || (m[1] === '' && (m[2] || '') === '')) return null;
    return Number(m[1] || 0) * 1e6 + Number((m[2] || '').padEnd(6, '0'));
  };
  const plural = (n, word) => `${n.toLocaleString('en-US')} ${word}${n === 1 ? '' : 's'}`;

  /* ---------- Sidebar (mobile) --------------------------------------------- */
  $$('[data-sidebar-open]').forEach((b) => b.addEventListener('click', () => document.body.classList.add('nav-open')));
  $$('[data-sidebar-close]').forEach((b) => b.addEventListener('click', () => document.body.classList.remove('nav-open')));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.body.classList.remove('nav-open'); });

  /* ---------- Sticky public header ----------------------------------------- */
  const siteHeader = $('[data-site-header]');
  if (siteHeader) {
    const onScroll = () => siteHeader.classList.toggle('is-scrolled', window.scrollY > 8);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Toasts -------------------------------------------------------- */
  $$('.toast').forEach((toast, i) => {
    const close = () => {
      if (toast.classList.contains('is-leaving')) return;
      toast.classList.add('is-leaving');
      setTimeout(() => toast.remove(), 340);
    };
    $('[data-toast-close]', toast)?.addEventListener('click', close);
    const ttl = toast.classList.contains('toast--error') ? 12000 : 7000;
    let timer = setTimeout(close, ttl + i * 500);
    toast.addEventListener('mouseenter', () => clearTimeout(timer));
    toast.addEventListener('mouseleave', () => { timer = setTimeout(close, 3000); });
  });

  /* ---------- Confirmations -------------------------------------------------- */
  document.addEventListener('submit', (e) => {
    const message = e.target.dataset.confirm || e.submitter?.dataset.confirm;
    if (message && !window.confirm(message)) e.preventDefault();
  }, true);

  /* ---------- Misc ------------------------------------------------------------ */
  $$('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form?.submit()));
  $$('[data-back]').forEach((a) => a.addEventListener('click', (e) => {
    if (window.history.length > 1) { e.preventDefault(); window.history.back(); }
  }));
  $$('[data-file-input]').forEach((input) => input.addEventListener('change', () => {
    const label = input.closest('.file')?.querySelector('[data-file-name]');
    if (label) label.textContent = input.files?.[0]?.name || label.dataset.default || 'Choose an image';
  }));
  $$('[data-count]').forEach((field) => {
    const out = $(`[data-count-for="${field.dataset.count}"]`);
    if (!out) return;
    const update = () => { out.textContent = `${field.value.length}/${field.maxLength}`; };
    field.addEventListener('input', update);
    update();
  });

  /* ---------- Copy to clipboard ---------------------------------------------- */
  $$('[data-copy]').forEach((btn) => btn.addEventListener('click', async () => {
    const source = btn.closest('.copy__box, .copy')?.querySelector('[data-copy-source]');
    if (!source) return;
    const text = source.textContent.trim();
    try {
      await navigator.clipboard.writeText(text);
    } catch {
      const range = document.createRange();
      range.selectNodeContents(source);
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
      document.execCommand('copy');
    }
    const label = $('span', btn);
    const previous = label ? label.textContent : '';
    if (label) label.textContent = 'Copied!';
    btn.classList.add('is-copied');
    setTimeout(() => { if (label) label.textContent = previous; btn.classList.remove('is-copied'); }, 1600);
  }));

  /* ---------- Celebration ------------------------------------------------------ */
  const celebrate = (el) => {
    if (reducedMotion()) return;
    const rect = el.getBoundingClientRect();
    const layer = document.createElement('div');
    layer.className = 'burst-layer';
    for (let i = 0; i < 30; i++) {
      const p = document.createElement('span');
      const size = 6 + Math.random() * 20;
      p.className = 'burst-bubble';
      p.style.left = `${rect.left + rect.width * (0.15 + Math.random() * 0.7)}px`;
      p.style.top = `${rect.top + rect.height / 2}px`;
      p.style.width = `${size}px`;
      p.style.height = `${size}px`;
      p.style.setProperty('--dx', `${(Math.random() - 0.5) * 320}px`);
      p.style.setProperty('--dy', `${-60 - Math.random() * 260}px`);
      p.style.animationDelay = `${Math.random() * 0.3}s`;
      layer.appendChild(p);
    }
    document.body.appendChild(layer);
    setTimeout(() => layer.remove(), 2400);
  };
  $$('[data-celebrate]').forEach((el, i) => setTimeout(() => celebrate(el), 350 + i * 250));

  /* ---------- Ad gate countdown ------------------------------------------------ */
  const gate = $('[data-ad-gate]');
  if (gate) {
    const total = Number(gate.dataset.total) || 0;
    let remaining = Number(gate.dataset.remaining) || 0;
    const bar = $('[data-countdown-bar]', gate);
    const num = $('[data-countdown-num]', gate);
    const text = $('[data-countdown-text]', gate);
    const status = $('[data-ad-status]', gate);
    const submit = $('[data-buy-submit]');
    const circumference = 2 * Math.PI * 19;
    let unlocked = false;

    const paint = () => {
      if (bar) {
        bar.style.strokeDasharray = String(circumference);
        bar.style.strokeDashoffset = String(total > 0 ? circumference * (1 - remaining / total) : 0);
      }
      const secs = Math.ceil(remaining);
      if (num) num.textContent = String(secs);
      if (text) text.textContent = `${secs}s`;
    };

    const unlock = () => {
      if (unlocked) return;
      unlocked = true;
      gate.classList.add('is-done');
      if (status) {
        status.replaceChildren();
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.setAttribute('class', 'icon');
        icon.setAttribute('viewBox', '0 0 24 24');
        icon.setAttribute('fill', 'none');
        icon.setAttribute('stroke', 'currentColor');
        icon.setAttribute('stroke-width', '1.8');
        icon.setAttribute('stroke-linecap', 'round');
        icon.setAttribute('stroke-linejoin', 'round');
        icon.innerHTML = '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 6.8-1.2"/>';
        const span = document.createElement('span');
        span.textContent = 'Thanks for watching — your purchase is unlocked.';
        status.append(icon, span);
      }
      if (submit && submit.classList.contains('is-locked')) {
        submit.disabled = false;
        submit.classList.remove('is-locked');
        submit.classList.add('is-unlocked');
      }
      const token = gate.dataset.token;
      if (token && gate.dataset.completeUrl) {
        fetch(gate.dataset.completeUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf() },
          body: `token=${encodeURIComponent(token)}`,
        }).catch(() => { /* the purchase itself re-validates the view */ });
      }
    };

    paint();
    if (remaining <= 0) {
      unlock();
    } else {
      let last = performance.now();
      document.addEventListener('visibilitychange', () => { last = performance.now(); });
      const tick = (now) => {
        const delta = (now - last) / 1000;
        last = now;
        if (document.visibilityState === 'visible') remaining = Math.max(0, remaining - delta);
        paint();
        if (remaining <= 0) { unlock(); return; }
        requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    }
  }

  /* ---------- Buy panel -------------------------------------------------------- */
  const buy = $('[data-buy-form]');
  if (buy) {
    const price = Number(buy.dataset.price);
    const share = Number(buy.dataset.share);
    const credits = Number(buy.dataset.credits);
    const needed = Number(buy.dataset.needed);
    const symbol = buy.dataset.symbol || '$';
    const qty = $('[data-qty]', buy);
    const max = Number(qty.max) || 1000;
    const out = {
      label: $('[data-buy-label]', buy),
      total: $('[data-sum-total]', buy),
      pool: $('[data-sum-pool]', buy),
      credits: $('[data-sum-credits]', buy),
      sales: $('[data-sum-sales]', buy),
      warn: $('[data-buy-warning]', buy),
    };
    const clamp = (n) => Math.max(1, Math.min(max, Math.floor(n) || 1));

    const update = () => {
      const q = clamp(parseInt(qty.value, 10));
      const wallet = $('[data-wallet]:checked', buy)?.value === 'cash' ? 'Cash' : 'Purchase';
      const balance = Number(buy.dataset[`balance${wallet}`]);
      const total = price * q;
      if (out.total) out.total.textContent = fmtMoney(total, symbol);
      if (out.pool) out.pool.textContent = fmtMoney(share * q, symbol);
      if (out.credits) out.credits.textContent = `+${(credits * q).toLocaleString('en-US')}`;
      if (out.sales) {
        const left = Math.max(0, needed - share * (q - 1));
        const sales = share > 0 ? Math.ceil(left / share) : 0;
        out.sales.textContent = `~${plural(sales, 'more sale')}`;
      }
      if (out.label) out.label.textContent = `Buy ${plural(q, 'bubble')} · ${fmtMoney(total, symbol)}`;
      if (out.warn) out.warn.hidden = balance >= total;
      $$('[data-qty-set]', buy).forEach((chip) => chip.classList.toggle('is-active', Number(chip.dataset.qtySet) === q));
    };

    $$('[data-step]', buy).forEach((b) => b.addEventListener('click', () => {
      qty.value = String(clamp(parseInt(qty.value, 10) + Number(b.dataset.step)));
      update();
    }));
    $$('[data-qty-set]', buy).forEach((b) => b.addEventListener('click', () => {
      qty.value = String(clamp(Number(b.dataset.qtySet)));
      update();
    }));
    qty.addEventListener('input', update);
    qty.addEventListener('change', () => { qty.value = String(clamp(parseInt(qty.value, 10))); update(); });
    $$('[data-wallet]', buy).forEach((r) => r.addEventListener('change', update));
    buy.addEventListener('submit', (e) => {
      const btn = $('[data-buy-submit]', buy);
      if (btn?.disabled) { e.preventDefault(); return; }
      btn?.classList.add('is-loading');
      setTimeout(() => { if (btn) btn.disabled = true; }, 0);
    });
    update();
  }

  /* ---------- Fee calculators (deposit / withdraw) ------------------------------ */
  $$('[data-fee-calc]').forEach((form) => {
    const fixed = Number(form.dataset.feeFixed) || 0;
    const bp = Number(form.dataset.feeBp) || 0;
    const symbol = form.dataset.symbol || '$';
    const input = $('[data-fee-amount]', form);
    const output = $('[data-fee-result]', form);
    if (!input || !output) return;
    const update = () => {
      const amount = parseMoney(input.value);
      if (amount === null || amount <= 0) {
        output.textContent = '—';
        output.classList.remove('is-bad');
        return;
      }
      const fee = fixed + Math.floor((Math.floor(amount / 10000) * bp + 5000) / 10000) * 10000;
      const net = amount - fee;
      output.textContent = net > 0 ? fmtMoney(net, symbol) + (fee > 0 ? `  ·  fee ${fmtMoney(fee, symbol)}` : '') : 'Below the fee';
      output.classList.toggle('is-bad', net <= 0);
    };
    input.addEventListener('input', update);
    $$('[data-fill-amount]', form).forEach((b) => b.addEventListener('click', () => { input.value = b.dataset.fillAmount; update(); }));
    update();
  });

  /* ---------- Ad editor live preview --------------------------------------------- */
  const editor = $('[data-ad-editor]');
  if (editor) {
    const field = (name) => $(`[data-preview="${name}"]`, editor);
    const preview = {
      title: $('[data-preview-title]', editor),
      description: $('[data-preview-description]', editor),
      domain: $('[data-preview-domain]', editor),
      cta: $('[data-preview-cta]', editor),
      img: $('[data-preview-img]', editor),
      initial: $('[data-preview-initial]', editor),
    };
    const host = (value) => {
      try { return new URL(value).hostname.replace(/^www\./, ''); } catch { return 'yoursite.com'; }
    };
    const sync = () => {
      const url = field('url')?.value.trim() || '';
      preview.title.textContent = field('title')?.value.trim() || 'Your headline';
      preview.description.textContent = field('description')?.value.trim() || 'Your description appears here.';
      preview.domain.textContent = host(url);
      preview.cta.textContent = field('cta')?.value.trim() || 'Visit site';
      preview.initial.textContent = host(url).charAt(0).toUpperCase();
      const image = field('image')?.value.trim() || '';
      if (/^https:\/\/\S+$/i.test(image)) {
        if (preview.img.getAttribute('src') !== image) preview.img.src = image;
        preview.img.hidden = false;
      } else {
        preview.img.hidden = true;
        preview.img.removeAttribute('src');
      }
    };
    preview.img?.addEventListener('error', () => { preview.img.hidden = true; });
    editor.addEventListener('input', sync);
    sync();
  }

  /* ---------- Settings: live economics summary -------------------------------------- */
  const econ = $('[data-econ-summary]');
  if (econ) {
    const form = econ.closest('form');
    const symbol = econ.dataset.symbol || '$';
    const value = (name) => parseMoney($(`[data-econ="${name}"]`, form)?.value);
    const update = () => {
      const price = value('bubble_price');
      const share = value('pool_share');
      const target = value('bubble_target');
      const referral = value('referral_commission');
      const platformEl = $('[data-econ-platform]', econ);
      if ([price, share, referral].every((v) => v !== null)) {
        const platform = price - share - referral;
        platformEl.textContent = fmtMoney(platform, symbol);
        platformEl.classList.toggle('text-red', platform < 0);
      }
      if (share && target !== null) $('[data-econ-ratio]', econ).textContent = String(Math.round((target / share) * 100) / 100);
      if (price && target !== null) $('[data-econ-roi]', econ).textContent = `${Math.round((target / price) * 100)}%`;
    };
    form.addEventListener('input', update);
  }

  /* ---------- Chart tooltips --------------------------------------------------------- */
  $$('[data-chart]').forEach((chart) => {
    const tip = $('[data-chart-tooltip]', chart);
    if (!tip) return;
    const series = [['Bought', 'tipBought', 1], ['Expired', 'tipExpired', 2]];
    const show = (col) => {
      tip.replaceChildren();
      const title = document.createElement('strong');
      title.className = 'chart__tip-title';
      title.textContent = col.dataset.tipTitle || '';
      tip.append(title);
      series.forEach(([label, key, n]) => {
        const row = document.createElement('div');
        row.className = 'chart__tip-row';
        const swatch = document.createElement('i');
        swatch.className = `chart__tip-key chart__tip-key--${n}`;
        const val = document.createElement('b');
        val.textContent = Number(col.dataset[key] || 0).toLocaleString('en-US');
        const name = document.createElement('span');
        name.textContent = label;
        row.append(swatch, val, name);
        tip.append(row);
      });
      tip.hidden = false;
      const chartBox = chart.getBoundingClientRect();
      const colBox = col.getBoundingClientRect();
      const half = tip.offsetWidth / 2;
      const center = colBox.left - chartBox.left + colBox.width / 2;
      tip.style.left = `${Math.min(Math.max(center, half), chartBox.width - half)}px`;
      tip.style.top = `${colBox.top - chartBox.top}px`;
    };
    const hide = () => { tip.hidden = true; };
    $$('.chart__col', chart).forEach((col) => {
      col.addEventListener('pointerenter', () => show(col));
      col.addEventListener('focus', () => show(col));
      col.addEventListener('pointerleave', hide);
      col.addEventListener('blur', hide);
    });
  });

  /* ---------- Two-factor setup QR code ------------------------------------------------- */
  $$('[data-qr]').forEach((box) => {
    const uri = box.dataset.qr;
    if (!uri || typeof window.qrcode !== 'function') return;
    const qr = window.qrcode(0, 'M');
    qr.addData(uri);
    qr.make();
    const count = qr.getModuleCount();
    const margin = 3;
    const size = count + margin * 2;
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', `0 0 ${size} ${size}`);
    svg.setAttribute('shape-rendering', 'crispEdges');
    const background = document.createElementNS(ns, 'rect');
    background.setAttribute('width', String(size));
    background.setAttribute('height', String(size));
    background.setAttribute('fill', '#ffffff');
    let d = '';
    for (let row = 0; row < count; row++) {
      for (let col = 0; col < count; col++) {
        if (qr.isDark(row, col)) d += `M${col + margin} ${row + margin}h1v1h-1z`;
      }
    }
    const modules = document.createElementNS(ns, 'path');
    modules.setAttribute('d', d);
    modules.setAttribute('fill', '#0b0c1a');
    svg.append(background, modules);
    box.replaceChildren(svg);
    box.classList.add('is-ready');
  });

  /* ---------- Live pool refresh --------------------------------------------------------- */
  const live = $('[data-pool-live]');
  if (live) {
    const endpoint = live.dataset.poolLive;
    const refresh = async () => {
      if (document.visibilityState !== 'visible') return;
      try {
        const response = await fetch(endpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        $$('[data-live]').forEach((el) => {
          const value = data[el.dataset.live];
          if (typeof value === 'string') el.textContent = value;
        });
        const head = data.head;
        $$('[data-live-meter]').forEach((m) => { m.style.width = `${head ? head.fill : 0}%`; });
        $$('[data-live-head-label]').forEach((el) => {
          if (head) el.textContent = el.dataset.liveHeadLabel.replace('{label}', head.label);
        });
        $$('[data-live-head]').forEach((bubble) => {
          if (!head) return;
          bubble.style.setProperty('--fill', String(head.fill));
          bubble.classList.toggle('is-empty', head.fill <= 0);
          bubble.classList.remove('is-idle');
          bubble.classList.add('is-filling');
          const strong = $('.bubble__text strong', bubble);
          const small = $('.bubble__text small', bubble);
          if (strong) strong.textContent = bubble.dataset.liveHead === 'percent' ? `${Math.round(head.fill)}%` : head.label;
          if (small) small.textContent = `${head.filled} / ${head.target}`;
        });
      } catch { /* offline — keep the last render */ }
    };
    setInterval(refresh, 15000);
  }
})();
