document.addEventListener('DOMContentLoaded', function () {
  var cfg = window.freeioServiceCart;
  if (!cfg || !cfg.ajaxUrl) return;

  document.querySelectorAll('.freeio-add-to-cart-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

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

            // заменяем кнопку ссылкой
            var a = document.createElement('a');
            a.href = url;
            a.className = 'button product_type_simple add_to_cart_button freeio-view-cart-btn';
            a.innerHTML = '<span class="freeio-btn-text">' + text + '</span>' +
              '<i class="flaticon-right-up next"></i>';
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
