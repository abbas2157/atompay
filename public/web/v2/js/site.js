/* AtomPay storefront v2 behaviours.
   Deliberately not a copy of atomshop/public/web/v2/js/site.js: that file also
   carries the shop's mega menus, search and Ask Atom, none of which exist here.
   The data-* hooks and class names are the same ones, so markup moves between
   the two sites unchanged.

   No inline <script> anywhere in v2: config/security.php allows scripts from
   'self' plus a per-request nonce, and keeping JS in this file means pages
   never need the nonce. */
(function () {
  'use strict';

  var doc = document;

  function all(selector, root) {
    return Array.prototype.slice.call((root || doc).querySelectorAll(selector));
  }

  /* ------------------------------------------------- page loader (is-ready)
     site.css covers the page until the document is ready, and removes itself
     after 2.5s even if this script never runs. */
  function ready() {
    doc.documentElement.classList.add('is-ready');
  }
  if (doc.readyState === 'complete' || doc.readyState === 'interactive') {
    ready();
  } else {
    doc.addEventListener('DOMContentLoaded', ready);
  }

  /* -------------------------------------------------------------- toast */
  var toastTimer = null;
  function toast(message) {
    if (!message) return;
    var existing = doc.querySelector('.toast');
    if (existing) existing.remove();

    var el = doc.createElement('div');
    el.className = 'toast';
    el.setAttribute('role', 'status');
    el.textContent = message;
    doc.body.appendChild(el);

    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.remove(); }, 4000);
  }

  /* ---------------------------------------------------------- accordion
     <div class="acc"><button aria-expanded>…</button><div class="acc-body">…
     One open at a time is not enforced: these are reference answers, and
     people compare them. */
  all('.acc > button').forEach(function (button) {
    var acc = button.parentElement;
    button.setAttribute('aria-expanded', acc.classList.contains('open') ? 'true' : 'false');

    button.addEventListener('click', function () {
      var open = acc.classList.toggle('open');
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  /* ------------------------------------------------------------- drawer
     The header folds into a drawer below 1100px. */
  function closeDrawer() {
    var drawer = doc.querySelector('[data-drawer]');
    if (!drawer || drawer.hidden) return;
    drawer.hidden = true;
    doc.body.style.overflow = '';
    all('[data-drawer-open]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
  }

  all('[data-drawer-open]').forEach(function (button) {
    button.addEventListener('click', function () {
      var drawer = doc.querySelector('[data-drawer]');
      if (!drawer) return;
      drawer.hidden = false;
      doc.body.style.overflow = 'hidden';
      button.setAttribute('aria-expanded', 'true');
      var first = drawer.querySelector('a, button');
      if (first) first.focus();
    });
  });

  all('[data-drawer-close]').forEach(function (button) {
    button.addEventListener('click', closeDrawer);
  });

  // The backdrop is the drawer element itself; the panel sits inside it.
  var drawerEl = doc.querySelector('[data-drawer]');
  if (drawerEl) {
    drawerEl.addEventListener('click', function (event) {
      if (event.target === drawerEl) closeDrawer();
    });
  }

  /* ----------------------------------------------------- dropdown menus
     <button data-toggle="shop"> opens <div data-menu="shop" hidden>. */
  function closeMenus(except) {
    all('[data-menu]').forEach(function (menu) {
      if (menu === except) return;
      menu.hidden = true;
      var owner = doc.querySelector('[data-toggle="' + menu.dataset.menu + '"]');
      if (owner) owner.setAttribute('aria-expanded', 'false');
    });
  }

  all('[data-toggle]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.stopPropagation();
      var menu = doc.querySelector('[data-menu="' + button.dataset.toggle + '"]');
      if (!menu) return;
      var open = menu.hidden;
      closeMenus(open ? menu : null);
      menu.hidden = !open;
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  doc.addEventListener('click', function (event) {
    if (!event.target.closest('[data-menu]')) closeMenus();
  });

  doc.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    closeMenus();
    closeDrawer();
  });

  /* ------------------------------------------------- filter pills / tabs
     <button class="pill" data-filter="repaying"> shows the [data-state]
     children of the group named by data-filters. */
  all('[data-filters]').forEach(function (group) {
    var targets = all('[data-state]', doc.querySelector(group.dataset.filters) || doc);

    all('.pill[data-filter]', group).forEach(function (pill) {
      pill.addEventListener('click', function () {
        var want = pill.dataset.filter;

        all('.pill[data-filter]', group).forEach(function (other) {
          var on = other === pill;
          other.classList.toggle('on', on);
          other.setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        var shown = 0;
        targets.forEach(function (target) {
          var show = want === 'all' || target.dataset.state === want;
          target.hidden = !show;
          if (show) shown++;
        });

        var empty = doc.querySelector(group.dataset.empty || '[data-filter-empty]');
        if (empty) empty.hidden = shown > 0;
      });
    });
  });

  /* ------------------------------------------------------- back to top */
  var toTop = doc.querySelector('.to-top');
  if (toTop) {
    toTop.hidden = true;
    window.addEventListener('scroll', function () {
      toTop.hidden = window.scrollY < 600;
    }, { passive: true });
    toTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ---------------------------------------------------- scroll progress */
  var progress = doc.querySelector('.scroll-progress > span');
  if (progress) {
    window.addEventListener('scroll', function () {
      var height = doc.documentElement.scrollHeight - window.innerHeight;
      progress.style.width = (height > 0 ? (window.scrollY / height) * 100 : 0) + '%';
    }, { passive: true });
  }

  /* --------------------------------------------------------- copy button
     data-copy="__url__" copies this page's address; anything else is copied
     as written. Clipboard access can be refused, so fall back to selecting. */
  all('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var value = button.dataset.copy === '__url__' ? window.location.href : button.dataset.copy;

      function done() { toast('Copied'); }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done, function () { fallback(value); });
      } else {
        fallback(value);
      }
    });
  });

  function fallback(value) {
    var field = doc.createElement('textarea');
    field.value = value;
    field.setAttribute('readonly', 'readonly');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    doc.body.appendChild(field);
    field.select();
    try { doc.execCommand('copy'); toast('Copied'); } catch (e) { toast('Press Ctrl+C to copy'); }
    field.remove();
  }

  /* --------------------------------------------------- password visibility */
  all('.pw-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = button.parentElement.querySelector('input');
      if (!input) return;
      var shown = input.type === 'text';
      input.type = shown ? 'password' : 'text';
      button.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');
      button.setAttribute('aria-pressed', shown ? 'false' : 'true');
    });
  });

  /* ------------------------------------------------------- busy buttons
     A form that is submitting says so, and cannot be submitted twice. */
  all('form[data-busy]').forEach(function (form) {
    form.addEventListener('submit', function () {
      all('button[type=submit]', form).forEach(function (button) {
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;
      });
    });
  });

  /* ------------------------------------------- flash message from the server */
  var flash = doc.querySelector('[data-flash]');
  if (flash) toast(flash.dataset.flash);

  /* ------------------------------------------------------------- cookies
     Only rendered when Google Analytics is configured; without it the site
     sets no optional cookies and there is nothing to ask about. */
  var cookies = doc.querySelector('[data-cookie-notice]');
  if (cookies) {
    var KEY = 'atompay.cookies';
    var answered = null;
    try { answered = window.localStorage.getItem(KEY); } catch (e) { answered = null; }

    if (!answered) cookies.hidden = false;

    all('[data-cookie-choice]', cookies).forEach(function (button) {
      button.addEventListener('click', function () {
        try { window.localStorage.setItem(KEY, button.dataset.cookieChoice); } catch (e) { /* private window */ }
        cookies.hidden = true;
        if (button.dataset.cookieChoice === 'accept' && typeof window.atompayEnableAnalytics === 'function') {
          window.atompayEnableAnalytics();
        }
      });
    });
  }

  /* --------------------------------------------------- resend countdown
     <form data-resend="45"> keeps its button disabled until the cool-off
     has passed, so a second code cannot be asked for before the server
     would send one. The server enforces the same window regardless. */
  all('[data-resend]').forEach(function (form) {
    var button = form.querySelector('[data-resend-button]');
    if (!button) return;

    var label = button.textContent.trim();
    var left = parseInt(form.dataset.resend, 10) || 0;

    function tick() {
      if (left > 0) {
        button.disabled = true;
        button.textContent = 'Resend code in ' + left + 's';
        left--;
        setTimeout(tick, 1000);
      } else {
        button.disabled = false;
        button.type = 'submit';
        button.textContent = label;
      }
    }
    tick();
  });

  /* ------------------------------------------------------- FAQ filter
     The box is hidden in the markup and shown here, because without JS it
     could not filter anything. Matching is on data-search, which carries
     the question and its answer lowercased. */
  all('[data-faq-search]').forEach(function (box) {
    var input = box.querySelector('input');
    var count = box.querySelector('[data-faq-count]');
    var list = doc.querySelector(box.dataset.faqSearch);
    if (!input || !list) return;

    box.hidden = false;
    var items = all('[data-search]', list);

    input.addEventListener('input', function () {
      var query = input.value.trim().toLowerCase();
      var shown = 0;

      items.forEach(function (item) {
        var hit = query === '' || item.dataset.search.indexOf(query) !== -1;
        item.hidden = !hit;
        if (hit) shown++;
      });

      if (!count) return;
      if (query === '') {
        count.textContent = '';
      } else if (shown === 0) {
        count.textContent = 'No questions match that. Try a different word.';
      } else {
        count.textContent = shown + (shown === 1 ? ' question matches' : ' questions match');
      }
    });
  });

  /* ---------------------------------------------------- image fallback
     A product picture comes from AtomShop, so AtomPay cannot guarantee the
     file is there. When one fails, hide it and show the placeholder glyph
     beside it rather than a broken-image icon with its alt text. */
  all('img[data-fallback]').forEach(function (img) {
    function fail() {
      img.hidden = true;
      var glyph = img.parentElement && img.parentElement.querySelector('.ph-glyph');
      if (glyph) glyph.hidden = false;
    }

    img.addEventListener('error', fail);
    // A cached failure can beat this listener; complete with no dimensions is one.
    if (img.complete && img.naturalWidth === 0) fail();
  });

  /* ============================================================ Ask Atom
     Floating button, panel, and one POST per question. Answers come from
     App\Services\AskAtomService - built-in rules, so there is no key here
     and nothing a visitor types leaves the server.

     The conversation is kept in sessionStorage so closing the panel, or
     moving to another page, does not lose it. */
  (function () {
    var configEl = doc.getElementById('atom-config');
    var fab = doc.querySelector('.atom-fab');
    var panel = doc.getElementById('atom-panel');
    if (!configEl || !fab || !panel) return;

    var config;
    try { config = JSON.parse(configEl.textContent); } catch (e) { return; }

    var log = panel.querySelector('.atom-log');
    var chips = panel.querySelector('.atom-chips');
    var form = panel.querySelector('[data-atom-form]');
    var input = panel.querySelector('#atom-input');
    var send = panel.querySelector('.atom-send');
    var KEY = 'atompay.atom';
    var busy = false;

    /* ---------------------------------------------------------- history */
    function history(next) {
      try {
        if (next === undefined) return JSON.parse(window.sessionStorage.getItem(KEY) || '[]');
        window.sessionStorage.setItem(KEY, JSON.stringify(next.slice(-20)));
      } catch (e) { /* private window: the panel still works, it just forgets */ }
      return next || [];
    }

    /* ------------------------------------------------------------ render */
    function bubble(side, html, actions) {
      var msg = doc.createElement('div');
      msg.className = 'atom-msg ' + side;

      var body = doc.createElement('div');
      body.className = 'atom-b';
      // Atom's own answers are trusted markup from the server; a visitor's
      // question is set as text, never as HTML.
      if (side === 'atom') { body.innerHTML = html; } else { body.textContent = html; }
      msg.appendChild(body);

      if (actions && actions.length) {
        var row = doc.createElement('div');
        row.className = 'atom-actions';
        actions.forEach(function (action) {
          var link = doc.createElement('a');
          link.href = action.url;
          link.textContent = action.label;
          if (action.url.indexOf('http') === 0 && action.url.indexOf(window.location.origin) !== 0) {
            link.target = '_blank';
            link.rel = 'noopener';
          }
          row.appendChild(link);
        });
        body.appendChild(row);
      }

      log.appendChild(msg);
      log.scrollTop = log.scrollHeight;
      return msg;
    }

    function typing() {
      var msg = doc.createElement('div');
      msg.className = 'atom-msg atom atom-typing';
      msg.innerHTML = '<div class="atom-b"><span></span><span></span><span></span></div>';
      log.appendChild(msg);
      log.scrollTop = log.scrollHeight;
      return msg;
    }

    function drawChips(list) {
      chips.textContent = '';
      (list || []).forEach(function (text) {
        var chip = doc.createElement('button');
        chip.type = 'button';
        chip.className = 'atom-chip';
        chip.textContent = text;
        chip.addEventListener('click', function () { ask(text); });
        chips.appendChild(chip);
      });
    }

    function restore() {
      log.textContent = '';
      var past = history();

      if (!past.length) {
        bubble('atom', '<p>' + config.greeting + '</p>');
      } else {
        past.forEach(function (turn) {
          bubble(turn.side, turn.html, turn.actions);
        });
      }

      drawChips(config.chips);
    }

    /* --------------------------------------------------------------- ask */
    function ask(question) {
      question = (question || '').trim();
      if (!question || busy) return;

      busy = true;
      if (send) send.disabled = true;
      input.value = '';

      bubble('you', question);
      var past = history();
      past.push({ side: 'you', html: question });

      var dots = typing();

      fetch(config.url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': config.csrf,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ question: question })
      })
        .then(function (response) {
          if (!response.ok) throw new Error(response.status);
          return response.json();
        })
        .then(function (data) {
          dots.remove();
          bubble('atom', data.answer, data.actions);
          past.push({ side: 'atom', html: data.answer, actions: data.actions });
          history(past);
          if (data.chips) drawChips(data.chips);
        })
        .catch(function () {
          dots.remove();
          bubble('atom', '<p>Something went wrong reaching me just then. Try again, or read the FAQs.</p>', [
            { label: 'Read the FAQs', url: '/faq' }
          ]);
        })
        .then(function () {
          busy = false;
          if (send) send.disabled = false;
          input.focus();
        });
    }

    /* ------------------------------------------------------------- panel */
    function open() {
      panel.hidden = false;
      fab.setAttribute('aria-expanded', 'true');
      doc.body.classList.add('atom-open');
      if (!log.childElementCount) restore();
      input.focus();
    }

    function close() {
      panel.hidden = true;
      fab.setAttribute('aria-expanded', 'false');
      doc.body.classList.remove('atom-open');
      fab.focus();
    }

    fab.addEventListener('click', function () {
      if (panel.hidden) { open(); } else { close(); }
    });

    var closer = panel.querySelector('.atom-close');
    if (closer) closer.addEventListener('click', close);

    doc.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden) close();
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      ask(input.value);
    });

    // Anything on the page can open it with a question already asked:
    // <button data-ask="What does AtomPay cost?">
    all('[data-ask]').forEach(function (trigger) {
      trigger.addEventListener('click', function () {
        open();
        if (trigger.dataset.ask) ask(trigger.dataset.ask);
      });
    });

    // The hero's own ask box hands its question to the same panel, so there is
    // one conversation rather than two.
    all('[data-atom-ask]').forEach(function (heroForm) {
      var heroInput = heroForm.querySelector('input');
      heroForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var question = (heroInput && heroInput.value || '').trim();
        open();
        if (question) { ask(question); heroInput.value = ''; }
      });
    });
  }());

  /* ====================================================== estimators
     Two calculators, both mirroring the server exactly so a figure on the
     page can never disagree with one the server would quote.

       [data-plan-calc]   price  -> AtomPay plan   (InstalmentQuoteService::atompayPlan)
       [data-limit-calc]  income -> limit and cap  (CreditAssessmentService)

     Each reads its rules from data- attributes rather than hardcoding them,
     so changing config/atompay.php changes both the page and the maths. */
  function rupees(n) {
    return 'Rs. ' + Math.round(n).toLocaleString('en-US');
  }

  var MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  function monthsFromNow(offset) {
    var now = new Date();
    // The 31st never silently becomes the 1st, as addMonthsNoOverflow avoids.
    var d = new Date(now.getFullYear(), now.getMonth() + offset, Math.min(now.getDate(), 28));
    return d.getDate() + ' ' + MONTH_NAMES[d.getMonth()] + ' ' + d.getFullYear();
  }

  all('[data-plan-calc]').forEach(function (box) {
    var months = parseInt(box.dataset.months, 10) || 3;
    var feePercent = parseFloat(box.dataset.fee) || 0;

    var number = box.querySelector('[data-plan-price]');
    var range = box.querySelector('[data-plan-range]');
    var rows = box.querySelector('[data-plan-schedule]');
    var out = {};
    all('[data-plan-out]', box).forEach(function (el) {
      (out[el.dataset.planOut] = out[el.dataset.planOut] || []).push(el);
    });
    function put(name, text) {
      (out[name] || []).forEach(function (el) { el.textContent = text; });
    }

    var min = parseInt((number && number.min) || 1000, 10);
    var max = parseInt((number && number.max) || 1000000, 10);

    function plan(price) {
      var monthly = Math.floor(price / months);
      return {
        price: price,
        monthly: monthly,
        last: price - monthly * (months - 1),
        total: price,
        fee: Math.round(price * feePercent / 100)
      };
    }

    function render(price) {
      var p = plan(price);

      put('monthly', rupees(p.monthly));
      put('last', rupees(p.last));
      put('total', rupees(p.total));
      put('fee', rupees(p.fee));
      put('price', rupees(p.price));

      if (!rows) return;

      var html = '';
      for (var i = 1; i <= months; i++) {
        var isLast = i === months;
        var note = isLast && p.last !== p.monthly
          ? '<span class="sub">Takes the rounding</span>'
          : '';

        html += '<tr><td>' + monthsFromNow(i) + '</td>'
          + '<td>Payment ' + i + ' of ' + months + note + '</td>'
          + '<td class="money" style="text-align:right">' + rupees(isLast ? p.last : p.monthly) + '</td></tr>';
      }
      rows.innerHTML = html;
    }

    function clamp(value) {
      value = Math.round(Number(value) || 0);
      if (!isFinite(value) || value < min) value = min;
      if (value > max) value = max;
      return value;
    }

    function sync(source) {
      var price = clamp(source.value);
      if (number && source !== number) number.value = price;
      if (range && source !== range) range.value = price;
      render(price);
    }

    if (number) {
      number.addEventListener('input', function () { sync(number); });
      number.addEventListener('blur', function () { number.value = clamp(number.value); sync(number); });
    }
    if (range) range.addEventListener('input', function () { sync(range); });

    // The artifact's quick prices: one tap instead of typing.
    all('[data-plan-set]', box).forEach(function (button) {
      button.addEventListener('click', function () {
        if (number) number.value = button.dataset.planSet;
        if (range) range.value = button.dataset.planSet;
        render(clamp(button.dataset.planSet));
      });
    });

    render(clamp((number || range).value));
  });

  all('[data-limit-calc]').forEach(function (box) {
    var limitRatio = parseFloat(box.dataset.limitRatio) || 0;
    var capRatio = parseFloat(box.dataset.capRatio) || 0;
    var feePercent = parseFloat(box.dataset.feePercent) || 0;

    var income = box.querySelector('[data-limit-income]');
    var result = box.querySelector('[data-limit-result]');
    var submit = box.querySelector('button[type=submit]');
    // Once the visitor's own income drives the card it is no longer an example,
    // so the badge and the note say so rather than contradicting the figures.
    var badge = box.querySelector('[data-limit-badge]');
    var note = box.querySelector('[data-limit-note]');
    var badgeExample = badge ? badge.textContent : '';
    var noteExample = note ? note.innerHTML : '';
    var out = {};
    all('[data-limit-out]', box).forEach(function (el) {
      (out[el.dataset.limitOut] = out[el.dataset.limitOut] || []).push(el);
      // Remember the server-rendered example, to put back on an empty field.
      el.dataset.example = el.textContent;
    });

    if (!income) return;

    function put(name, text) {
      (out[name] || []).forEach(function (el) { el.textContent = text; });
    }

    function render() {
      var value = Number(income.value) || 0;
      var valid = value >= Number(income.min || 1000);

      if (result) result.hidden = !valid;
      if (submit) submit.disabled = !valid;

      if (!valid) {
        // Back to the example figures rather than a half-filled card.
        Object.keys(out).forEach(function (name) {
          (out[name] || []).forEach(function (el) { el.textContent = el.dataset.example; });
        });
        if (badge) badge.textContent = badgeExample;
        if (note) note.innerHTML = noteExample;
        return;
      }

      var limit = value * limitRatio;

      put('income', rupees(value));
      put('limit', rupees(limit));
      put('cap', rupees(value * capRatio));
      put('fee', rupees(limit * feePercent / 100));

      if (badge) badge.textContent = 'Your estimate';
      if (note) {
        note.textContent = 'Based on the income you typed, not a verified figure. The processing fee is shown with '
          + 'every AtomPay plan as a disclaimer and is not added to the order total; the figure above is '
          + feePercent + '% of a purchase using the whole limit. Your limit is decided by AtomPay staff after '
          + 'identity, address and income checks.';
      }
    }

    income.addEventListener('input', render);
    render();
  });

  /* ------------------------------------------------- KYC application wizard
     [data-kyc-wizard]  the 4-step application on /my/application

     One <form> holds every step; this only shows and hides them, so with
     JavaScript off the customer sees all four sections and can still submit.
     Ratios come from data- attributes, so config/atompay.php stays the one
     place the limit and cap percentages are set. */
  all('form[data-kyc-wizard]').forEach(function (form) {
    var steps = all('[data-wizard-step]', form);
    if (!steps.length) return;

    var dots = all('[data-wizard-dot]', form);
    var back = form.querySelector('[data-wizard-back]');
    var next = form.querySelector('[data-wizard-next]');
    var submit = form.querySelector('[data-wizard-submit]');
    var consent = form.querySelector('[data-wizard-consent]');

    var limitRatio = parseFloat(form.dataset.limitRatio) || 0;
    var capRatio = parseFloat(form.dataset.capRatio) || 0;

    // A step is reachable once reached; a server error reopens its own step.
    var errorStep = parseInt(form.dataset.errorStep, 10);
    var furthest = parseInt(form.dataset.furthest, 10) || 0;
    var current = isNaN(errorStep) ? 0 : errorStep;
    if (current > furthest) furthest = current;

    function show(i) {
      current = Math.max(0, Math.min(i, steps.length - 1));
      if (current > furthest) furthest = current;

      steps.forEach(function (section, n) { section.hidden = n !== current; });
      dots.forEach(function (dot, n) {
        dot.classList.toggle('now', n === current);
        if (n <= furthest) {
          dot.setAttribute('role', 'button');
          dot.setAttribute('tabindex', '0');
        }
      });

      var last = current === steps.length - 1;
      if (back) back.hidden = current === 0;
      if (next) next.hidden = last;
      if (submit) submit.hidden = !last;
      if (last) echo();
      gateSubmit();

      // Only scroll when the customer moved, never on first paint.
      if (show.touched) steps[current].scrollIntoView({ block: 'start', behavior: 'smooth' });
      show.touched = true;
    }

    /* Only the fields of the step being left are checked, so a later step's
       empty required field cannot block an earlier Continue. */
    function stepValid(i) {
      var fields = all('input, select, textarea', steps[i]).filter(function (el) {
        return !el.disabled && el.type !== 'file' && typeof el.checkValidity === 'function';
      });
      var bad = null;
      fields.forEach(function (el) {
        el.removeAttribute('aria-invalid');
        if (!el.checkValidity() && !bad) bad = el;
      });
      if (bad) {
        bad.setAttribute('aria-invalid', 'true');
        bad.focus();
        if (typeof bad.reportValidity === 'function') bad.reportValidity();
      }
      return !bad;
    }

    function gateSubmit() {
      if (submit && consent) submit.disabled = !consent.checked;
    }

    if (next) next.addEventListener('click', function () {
      if (stepValid(current)) show(current + 1);
    });
    if (back) back.addEventListener('click', function () { show(current - 1); });
    if (consent) consent.addEventListener('change', gateSubmit);

    dots.forEach(function (dot, n) {
      function jump() { if (n <= furthest) show(n); }
      dot.addEventListener('click', jump);
      dot.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); jump(); }
      });
    });

    /* ----------------------------------------------- employer, shown by status
       Mirrors EmploymentStatus::hasEmployer() - the server still enforces it. */
    var HAS_EMPLOYER = ['salaried', 'self_employed', 'business_owner'];
    var status = form.querySelector('[data-wizard-employment]');
    var employerWrap = form.querySelector('[data-wizard-employer]');

    function syncEmployer() {
      if (!status || !employerWrap) return;
      var needed = HAS_EMPLOYER.indexOf(status.value) !== -1;
      employerWrap.hidden = !needed;
      var input = employerWrap.querySelector('input');
      if (input) input.required = needed;
    }
    if (status) status.addEventListener('change', syncEmployer);

    /* ------------------------------------------------- live provisional figures */
    var income = form.querySelector('[data-wizard-income]');
    var outgoings = all('[data-wizard-outgoing]', form);
    var estimate = form.querySelector('[data-wizard-estimate]');

    function put(name, text) {
      all('[data-wizard-out="' + name + '"]', form).forEach(function (el) { el.textContent = text; });
    }

    function figures() {
      var value = Number(income && income.value) || 0;
      var spent = outgoings.reduce(function (sum, el) { return sum + (Number(el.value) || 0); }, 0);
      var disposable = value - spent;
      var valid = value >= Number((income && income.min) || 1000);

      put('disposable', value ? rupees(disposable) : '—');
      all('[data-wizard-out="disposable"]', form).forEach(function (el) {
        el.classList.toggle('neg', disposable < 0);
      });

      if (estimate) estimate.hidden = !valid;
      if (!valid) return;

      // The cap is the smaller of the income share and what is actually free.
      put('limit', rupees(value * limitRatio));
      put('cap', rupees(Math.max(0, Math.min(value * capRatio, disposable))));
    }

    if (income) income.addEventListener('input', figures);
    outgoings.forEach(function (el) { el.addEventListener('input', figures); });

    /* ------------------------------------------------ document tiles
       The hidden file input is labelled by its tile; show the chosen name so
       the customer can tell the three apart before submitting. */
    all('[data-doc-tile]', form).forEach(function (tile) {
      var input = tile.querySelector('input[type=file]');
      var name = tile.querySelector('[data-doc-name]');
      if (!input || !name) return;
      var original = name.textContent;
      input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        name.textContent = file ? file.name : original;
        tile.classList.toggle('filled', !!file || tile.classList.contains('filled'));
      });
    });

    /* ------------------------------------------------ pakistani formats
       Replaces v1's x-pk-format. The pattern attributes still hold for anyone
       without JavaScript, and the request normalises either way. */
    all('[data-pk-format]', form).forEach(function (input) {
      input.addEventListener('input', function () {
        var digits = input.value.replace(/\D/g, '');
        if (input.dataset.pkFormat === 'cnic') {
          digits = digits.slice(0, 13);
          input.value = digits.replace(/^(\d{5})(\d{0,7})(\d{0,1}).*$/, function (m, a, b, c) {
            return a + (b ? '-' + b : '') + (c ? '-' + c : '');
          });
        } else {
          if (digits.indexOf('92') === 0) digits = '0' + digits.slice(2);
          if (digits.indexOf('0') !== 0 && digits) digits = '0' + digits;
          digits = digits.slice(0, 11);
          input.value = digits.replace(/^(\d{4})(\d{0,7}).*$/, function (m, a, b) {
            return a + (b ? ' ' + b : '');
          });
        }
      });
    });

    /* ------------------------------------------------ review step echo */
    function echo() {
      all('[data-wizard-echo]', form).forEach(function (cell) {
        var key = cell.dataset.wizardEcho;

        if (key === 'documents') {
          var picked = all('input[type=file]', form).map(function (f) {
            var file = f.files && f.files[0];
            if (file) return f.name.replace(/_/g, ' ') + ': ' + file.name;
            return f.closest('[data-doc-tile]') && f.closest('[data-doc-tile]').classList.contains('filled')
              ? f.name.replace(/_/g, ' ') + ': on file'
              : f.name.replace(/_/g, ' ') + ': missing';
          });
          cell.textContent = picked.join(' · ');
          return;
        }

        var el = form.querySelector('[name="' + key + '"]');
        if (!el) return;
        var value = el.value;
        if (el.tagName === 'SELECT') value = el.selectedIndex > 0 ? el.options[el.selectedIndex].text : '';
        if (/^(monthly_income|existing_instalments|monthly_expenses)$/.test(key)) {
          value = value === '' ? '' : rupees(Number(value) || 0);
        }
        cell.textContent = value || '—';
      });
    }

    syncEmployer();
    figures();
    show(current);
  });

  /* ------------------------------------------------- searchable dropdowns
     <select data-search>  ->  type-to-filter combobox

     Progressive enhancement over the real <select>: the element stays in the
     DOM and keeps its name, so the form posts exactly as it would without
     this, and a customer with no JavaScript gets the native dropdown. The
     combobox only mirrors it, and writes back with a change event so
     dependent pickers (city -> area) still fire.

     Needed because some of these lists are long: 99 cities, and 474 areas in
     Lahore alone. */
  function comboFor(select) {
    if (select.__combo) return select.__combo;

    var box = doc.createElement('div');
    box.className = 'combo';

    var input = doc.createElement('input');
    input.type = 'text';
    input.className = 'combo-input';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');
    input.autocomplete = 'off';
    if (select.id) input.id = select.id + '-combo';

    var list = doc.createElement('ul');
    list.className = 'combo-list';
    list.setAttribute('role', 'listbox');
    list.id = (select.id || select.name) + '-list';
    list.hidden = true;
    input.setAttribute('aria-controls', list.id);

    // The native control still exists; it is just taken out of the tab order
    // and hidden from assistive tech, which now sees the combobox instead.
    select.classList.add('combo-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    select.parentNode.insertBefore(box, select);
    box.appendChild(select);
    box.appendChild(input);
    box.appendChild(list);

    var options = [];
    var active = -1;

    function label(i) {
      return options[i] ? options[i].text : '';
    }

    function placeholderText() {
      // A leading blank option is the list's own placeholder ("Select city").
      var first = select.options[0];
      return first && first.value === '' ? first.text : 'Type to search';
    }

    function readOptions() {
      options = Array.prototype.slice.call(select.options).filter(function (o) { return o.value !== ''; });
      input.placeholder = placeholderText();
      input.disabled = select.disabled || options.length === 0;
      if (input.disabled) input.value = '';
      else if (select.selectedIndex > -1 && select.value !== '') input.value = select.options[select.selectedIndex].text;
    }

    function close() {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      active = -1;
    }

    function choose(i) {
      if (!options[i]) return;
      select.value = options[i].value;
      input.value = options[i].text;
      close();
      // Let city -> area and any filter form hear it, as a real change would.
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function render(filter) {
      var needle = (filter || '').trim().toLowerCase();
      list.textContent = '';

      var shown = options.filter(function (o) {
        return !needle || o.text.toLowerCase().indexOf(needle) !== -1;
      });

      if (!shown.length) {
        var none = doc.createElement('li');
        none.className = 'combo-none';
        none.textContent = 'No match';
        list.appendChild(none);
      }

      shown.slice(0, 200).forEach(function (o) {
        var i = options.indexOf(o);
        var li = doc.createElement('li');
        li.className = 'combo-opt';
        li.id = list.id + '-' + i;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', String(select.value === o.value));
        li.dataset.index = String(i);
        li.textContent = o.text;
        li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(i); });
        list.appendChild(li);
      });

      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }

    function move(step) {
      var items = all('.combo-opt', list);
      if (!items.length) return;
      var at = items.findIndex(function (el) { return el.classList.contains('on'); });
      at = at === -1 ? (step > 0 ? 0 : items.length - 1) : Math.max(0, Math.min(at + step, items.length - 1));
      items.forEach(function (el) { el.classList.remove('on'); });
      items[at].classList.add('on');
      items[at].scrollIntoView({ block: 'nearest' });
      active = parseInt(items[at].dataset.index, 10);
      input.setAttribute('aria-activedescendant', items[at].id);
    }

    input.addEventListener('focus', function () { render(''); input.select(); });
    input.addEventListener('input', function () { render(input.value); });
    input.addEventListener('blur', function () {
      // Nothing picked and nothing typed clears the native value too, so the
      // two never disagree about what is selected.
      if (!list.hidden) close();
      if (input.value.trim() === '') { select.value = ''; return; }
      var match = options.filter(function (o) { return o.text.toLowerCase() === input.value.trim().toLowerCase(); })[0];
      if (match) select.value = match.value;
      else input.value = select.value && select.selectedIndex > -1 ? select.options[select.selectedIndex].text : '';
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (list.hidden) render(input.value);
        move(e.key === 'ArrowDown' ? 1 : -1);
      } else if (e.key === 'Enter') {
        if (!list.hidden && active > -1) { e.preventDefault(); choose(active); }
      } else if (e.key === 'Escape') {
        if (!list.hidden) { e.stopPropagation(); close(); }
      } else if (e.key === 'Tab') {
        close();
      }
    });

    doc.addEventListener('click', function (e) {
      if (!box.contains(e.target)) close();
    });

    readOptions();

    select.__combo = { refresh: function () { readOptions(); close(); } };
    return select.__combo;
  }

  all('select[data-search]').forEach(comboFor);

  /* --------------------------------------------- city -> area, on the form
     Areas are fetched for the chosen city rather than shipped with the page.
     The area select is a plain <select>, so a failed fetch leaves whatever the
     server rendered rather than an empty picker. */
  all('[data-area-for]').forEach(function (area) {
    var city = doc.getElementById(area.dataset.areaFor);
    if (!city) return;
    var template = area.dataset.areaUrl || '';
    var placeholder = area.options[0] ? area.options[0].text : 'Select area';

    city.addEventListener('change', function () {
      var id = city.value;
      area.innerHTML = '';
      area.appendChild(new Option(id ? 'Loading areas…' : 'Choose a city first', ''));
      area.disabled = true;
      if (area.__combo) area.__combo.refresh();
      if (!id) return;

      fetch(template.replace('__CITY__', encodeURIComponent(id)), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (rows) {
          area.innerHTML = '';
          area.appendChild(new Option(placeholder, ''));
          rows.forEach(function (row) { area.appendChild(new Option(row.title, row.id)); });
          area.disabled = rows.length === 0;
        })
        .catch(function () {
          area.innerHTML = '';
          area.appendChild(new Option('Could not load areas — try again', ''));
          area.disabled = true;
        })
        .then(function () { if (area.__combo) area.__combo.refresh(); });
    });
  });

  window.AtomUI = { toast: toast };
}());
