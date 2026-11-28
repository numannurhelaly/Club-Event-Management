// Live search and category filter for the events page (AJAX with fetch).
// Builds the same ticket markup as includes/ticket.php.
(function () {
  'use strict';

  var list     = document.getElementById('events-list');
  var form     = document.getElementById('filter-form');
  var input    = document.getElementById('search-input');
  var category = document.getElementById('category-filter');
  if (!list || !form || !input || !category) { return; }

  var csrf       = list.getAttribute('data-csrf');
  var timer      = null;
  var controller = null;

  // Constant icon markup (never built from user data).
  var ICON_CLOCK = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
  var ICON_PIN   = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>';

  // Text is always set with textContent, so event titles can't inject HTML (XSS).
  function el(tag, attrs, text) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) { node.setAttribute(k, attrs[k]); });
    if (text !== undefined && text !== null) { node.textContent = text; }
    return node;
  }

  function hidden(name, value) {
    return el('input', { type: 'hidden', name: name, value: value });
  }

  function meta(icon, text) {
    var p = el('p', { 'class': 'ticket-meta' });
    var i = el('span', { 'class': 'icon' });
    i.innerHTML = icon;
    p.appendChild(i);
    p.appendChild(el('span', {}, text));
    return p;
  }

  function seatsText(n) {
    if (n <= 0) { return 'Full'; }
    if (n <= 5) { return 'Only ' + n + ' left'; }
    return n + ' seats left';
  }

  function actionForm(ev, action) {
    var f = el('form', { method: 'post', action: 'event_register.php', 'class': 'inline' });
    f.appendChild(hidden('csrf', csrf));
    f.appendChild(hidden('event_id', ev.id));
    f.appendChild(hidden('action', action));
    if (action === 'cancel') {
      f.appendChild(el('button', { type: 'submit', 'class': 'btn btn-danger' }, 'Cancel'));
    } else {
      f.appendChild(el('button', { type: 'submit', 'class': 'btn btn-primary' }, 'Register'));
    }
    return f;
  }

  function ticket(ev) {
    var cls = 'ticket';
    if (ev.is_registered) { cls += ' is-registered'; }
    else if (ev.seats_left <= 0) { cls += ' is-full'; }

    var t    = el('article', { 'class': cls, 'data-event-id': ev.id });
    var main = el('div', { 'class': 'ticket-main' });

    var top = el('div', { 'class': 'ticket-top' });
    top.appendChild(ev.category ? el('span', { 'class': 'badge' }, ev.category) : el('span'));
    if (ev.is_registered) { top.appendChild(el('span', { 'class': 'stamp' }, "You're in")); }
    main.appendChild(top);

    var h = el('h3', { 'class': 'ticket-title' });
    h.appendChild(el('a', { href: 'event_view.php?id=' + ev.id }, ev.title));
    main.appendChild(h);

    main.appendChild(meta(ICON_CLOCK, ev.time_label));
    main.appendChild(meta(ICON_PIN, ev.venue));
    if (ev.description) { main.appendChild(el('p', { 'class': 'ticket-desc' }, ev.description)); }

    var actions = el('div', { 'class': 'ticket-actions' });
    if (ev.is_registered) {
      actions.appendChild(actionForm(ev, 'cancel'));
    } else if (ev.can_register) {
      actions.appendChild(actionForm(ev, 'register'));
    } else {
      actions.appendChild(el('button', { type: 'button', 'class': 'btn', disabled: 'disabled' }, 'Full'));
    }
    actions.appendChild(el('a', { 'class': 'link-quiet', href: 'event_view.php?id=' + ev.id }, 'Details'));
    main.appendChild(actions);

    var stub = el('div', { 'class': 'ticket-stub' });
    stub.appendChild(el('span', { 'class': 'stub-dow' }, ev.dow));
    stub.appendChild(el('span', { 'class': 'stub-day' }, String(ev.day)));
    stub.appendChild(el('span', { 'class': 'stub-month' }, ev.month_label));
    stub.appendChild(el('span', { 'class': 'stub-seats' }, seatsText(ev.seats_left)));

    t.appendChild(main);
    t.appendChild(stub);
    return t;
  }

  function showMessage(text, id) {
    list.innerHTML = '';
    list.appendChild(el('p', { 'class': 'muted', id: id || 'list-message' }, text));
  }

  function render(events) {
    if (!events.length) {
      showMessage('No shows match your search. Try another word or category.', 'no-events');
      return;
    }
    list.innerHTML = '';
    var grid = el('div', { 'class': 'ticket-grid' });
    events.forEach(function (ev) { grid.appendChild(ticket(ev)); });
    list.appendChild(grid);
  }

  function search() {
    // Cancel the previous request so old results never overwrite newer ones.
    if (controller) { controller.abort(); }
    controller = new AbortController();

    var params = new URLSearchParams({ q: input.value.trim(), category: category.value });

    fetch('../api/events.php?' + params.toString(), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      signal: controller.signal
    })
      .then(function (res) {
        if (res.status === 401) { window.location.href = 'login.php'; return null; }
        if (!res.ok) { throw new Error('Request failed'); }
        return res.json();
      })
      .then(function (data) { if (data) { render(data.events); } })
      .catch(function (err) {
        if (err.name === 'AbortError') { return; }
        showMessage('Could not load events. Check your connection and try again.', 'load-error');
      });
  }

  input.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(search, 300);   // wait until the user stops typing
  });
  category.addEventListener('change', search);
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearTimeout(timer);
    search();
  });
})();
