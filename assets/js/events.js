// Live search and category filter for the events page (AJAX with fetch).
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

  // Small helper to build elements. Text is always set with textContent (safe from XSS).
  function el(tag, attrs, text) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) { node.setAttribute(k, attrs[k]); });
    if (text !== undefined && text !== null) { node.textContent = text; }
    return node;
  }

  function hidden(name, value) {
    return el('input', { type: 'hidden', name: name, value: value });
  }

  function actionForm(ev, action) {
    var f = el('form', { method: 'post', action: 'event_register.php', 'class': 'inline' });
    f.appendChild(hidden('csrf', csrf));
    f.appendChild(hidden('event_id', ev.id));
    f.appendChild(hidden('action', action));
    if (action === 'cancel') {
      f.appendChild(el('span', { 'class': 'badge badge-published' }, 'Registered'));
      f.appendChild(document.createTextNode(' '));
      f.appendChild(el('button', { type: 'submit', 'class': 'btn btn-danger' }, 'Cancel'));
    } else {
      f.appendChild(el('button', { type: 'submit', 'class': 'btn btn-primary' }, 'Register'));
    }
    return f;
  }

  function card(ev) {
    var c = el('div', { 'class': 'event-card', 'data-event-id': ev.id });
    if (ev.category) { c.appendChild(el('span', { 'class': 'badge' }, ev.category)); }
    c.appendChild(el('h3', {}, ev.title));
    c.appendChild(el('p', { 'class': 'muted' }, ev.date_label + ', ' + ev.time_label));
    c.appendChild(el('p', { 'class': 'muted' }, ev.venue));
    var more = el('p');
    more.appendChild(el('a', { href: 'event_view.php?id=' + ev.id }, 'Details & schedule'));
    c.appendChild(more);
    if (ev.description) { c.appendChild(el('p', { 'class': 'desc' }, ev.description)); }

    var foot = el('div', { 'class': 'event-foot' });
    foot.appendChild(el('span', { 'class': 'seats' }, ev.seats_left > 0 ? ev.seats_left + ' seats left' : 'Full'));
    if (ev.is_registered) {
      foot.appendChild(actionForm(ev, 'cancel'));
    } else if (ev.can_register) {
      foot.appendChild(actionForm(ev, 'register'));
    } else {
      var full = el('button', { type: 'button', 'class': 'btn', disabled: 'disabled' }, 'Full');
      foot.appendChild(full);
    }
    c.appendChild(foot);
    return c;
  }

  function showMessage(text, id) {
    list.innerHTML = '';
    list.appendChild(el('p', { 'class': 'muted', id: id || 'list-message' }, text));
  }

  function render(events) {
    if (!events.length) {
      showMessage('No upcoming events found.', 'no-events');
      return;
    }
    list.innerHTML = '';
    var grid = el('div', { 'class': 'event-grid' });
    events.forEach(function (ev) { grid.appendChild(card(ev)); });
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
        showMessage('Could not load events. Please try again.', 'load-error');
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
