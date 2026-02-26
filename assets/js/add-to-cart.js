function showFreeioPopupMessage(text) {
  var id = 'wp-freeio-popup-message';
  var popup = document.getElementById(id);
  if (!popup) {
    popup = document.createElement('div');
    popup.id = id;
    popup.className = 'animated';
    popup.setAttribute('aria-live', 'polite');
    popup.innerHTML = '<div class="message-inner alert bg-warning">' + escapeHtml(text) + '</div>';
    (document.body || document.documentElement).appendChild(popup);
  } else {
    var inner = popup.querySelector('.message-inner');
    if (inner) {
      inner.textContent = text;
    } else {
      popup.textContent = text;
    }
  }
  popup.className = 'animated fadeInRight';
  popup.style.display = '';
  clearTimeout(popup._hideTimer);
  popup._hideTimer = setTimeout(function () {
    popup.className = 'animated delay-2s fadeOutRight';
    clearTimeout(popup._hideTimer);
    popup._hideTimer = setTimeout(function () {
      popup.style.display = 'none';
      popup.className = 'animated';
    }, 2500);
  }, 2500);
}

function escapeHtml(str) {
  var div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function () {
  var cfg = window.freeioServiceCart;
  if (!cfg || !cfg.ajaxUrl) return;

  document.querySelectorAll('.freeio-add-to-cart-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (cfg.requireLogin && !cfg.isLoggedIn) {
        showFreeioPopupMessage(cfg.loginRequiredPopupMessage || 'You do not have permission to buy this service. Please log in to continue.');
        return;
      }

      var btn = form.querySelector('button[type="submit"]');
      if (!btn || btn.dataset.busy === '1') return;
      btn.dataset.busy = '1';
      btn.disabled = true;

      var span = btn.querySelector('.freeio-btn-text');
      if (span) span.textContent = cfg.addingText || 'Adding…';

      var body = new FormData(form);
      body.set('action', cfg.action);

      fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          var d = res.data || {};

          if (d.cart_count !== undefined) {
            document.querySelectorAll('.freeio-cart-link-count').forEach(function (el) {
              el.textContent = d.cart_count;
            });
          }

          if (res.success) {
            var url = d.cart_url || form.getAttribute('data-cart-url') || '/';
            var text = cfg.viewCartText || 'View cart';

            var a = document.createElement('a');
            a.href = url;
            a.className = (btn.className || '').replace(/\s*freeio-adding\s*/, ' ').trim() + ' btn btn-theme btn-inverse w-100 mt-6 freeio-view-cart-btn';
            a.innerHTML = '<span class="freeio-btn-text">' + text + '</span>';
            btn.parentNode.insertBefore(a, btn);
            btn.style.display = 'none';
          } else {
            if (span) span.textContent = d.message || 'Error';
            btn.disabled = false;
            btn.dataset.busy = '';
          }
        })
        .catch(function () {
          btn.disabled = false;
          btn.dataset.busy = '';
          if (span) span.textContent = cfg.addingText || 'Add to cart';
        });
    });
  });
});
