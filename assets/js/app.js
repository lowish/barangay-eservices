// Small progressive enhancements. Every rule is also enforced on the server.
(function () {
  'use strict';

  // Ask for confirmation before destructive or irreversible actions: <button data-confirm="...">
  document.addEventListener('submit', function (event) {
    var form = event.target;
    var submitter = event.submitter || form.querySelector('[data-confirm]');
    var message = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
      return;
    }
    // Prevent double submission of the same form.
    if (form.dataset.submitted === '1') {
      event.preventDefault();
      return;
    }
    form.dataset.submitted = '1';
    window.setTimeout(function () { form.dataset.submitted = ''; }, 4000);
  });

  // Print buttons: <button data-print>
  document.querySelectorAll('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  // Character counters: <textarea data-maxlength-counter="id-of-counter" maxlength="...">
  document.querySelectorAll('[data-counter]').forEach(function (field) {
    var counter = document.getElementById(field.getAttribute('data-counter'));
    var max = parseInt(field.getAttribute('maxlength'), 10);
    if (!counter || !max) return;
    var update = function () { counter.textContent = field.value.length + ' / ' + max; };
    field.addEventListener('input', update);
    update();
  });

  // Status action forms: show the assignee / remarks fields relevant to the chosen action.
  document.querySelectorAll('[data-action-select]').forEach(function (select) {
    var form = select.closest('form');
    var toggle = function () {
      form.querySelectorAll('[data-show-for]').forEach(function (el) {
        var list = el.getAttribute('data-show-for').split('|');
        el.hidden = list.indexOf(select.value) === -1;
      });
    };
    select.addEventListener('change', toggle);
    toggle();
  });
})();
