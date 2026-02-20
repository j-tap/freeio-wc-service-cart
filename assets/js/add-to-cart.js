(function () {
  'use strict';

  var config = window.freeioServiceCart || {};
  if (!config.ajaxUrl) return;

  var FORM_SELECTOR = '.freeio-add-to-cart-form';
  var COUNT_SELECTOR = '.freeio-cart-link-count';
  var ADDING_CLASS = 'freeio-adding';
  var ADDED_CLASS = 'freeio-added';

  function updateCartCounts(count) {
    var counters = document.querySelectorAll(COUNT_SELECTOR);
    for (var i = 0; i < counters.length; i++) {
      counters[i].textContent = String(count);
    }
  }

  function showButtonFeedback(button, success) {
    var cls = success ? ADDED_CLASS : '';
    button.classList.remove(ADDING_CLASS);
    if (cls) {
      button.classList.add(cls);
      setTimeout(function () { button.classList.remove(cls); }, 1500);
    }
  }

  function handleSubmit(e) {
    e.preventDefault();

    var form = e.target;
    var button = form.querySelector('button[type="submit"]');
    if (!button || button.classList.contains(ADDING_CLASS)) return;

    var originalText = button.textContent;
    button.classList.add(ADDING_CLASS);
    button.disabled = true;
    if (config.addingText) {
      button.textContent = config.addingText;
    }

    var data = new FormData(form);
    data.set('action', config.action);

    fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: data,
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        var payload = json.data || {};
        var count = typeof payload.cart_count !== 'undefined' ? payload.cart_count : null;

        if (count !== null) {
          updateCartCounts(count);
        }

        button.textContent = json.success
          ? (config.addedText || originalText)
          : (payload.message || originalText);

        showButtonFeedback(button, json.success);

        setTimeout(function () {
          button.textContent = originalText;
          button.disabled = false;
        }, 1500);
      })
      .catch(function () {
        button.textContent = originalText;
        button.disabled = false;
        button.classList.remove(ADDING_CLASS);
        form.submit();
      });
  }

  function init() {
    var forms = document.querySelectorAll(FORM_SELECTOR);
    for (var i = 0; i < forms.length; i++) {
      forms[i].addEventListener('submit', handleSubmit);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
