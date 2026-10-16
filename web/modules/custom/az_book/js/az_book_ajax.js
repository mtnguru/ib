(function (Drupal, once) {
  Drupal.behaviors.azBookAjax = {
    attach: function (context, settings) {

      once('azBookAjax', '.az-book-ajax-link', context).forEach(function (el) {

        el.addEventListener('click', function (e) {
          e.preventDefault();

          const nid = this.dataset.nid;

          // Update current class
          document.querySelectorAll('.book-item.is-current').forEach(li => {
            li.classList.remove('is-current');
          });
          this.parentElement.classList.add('is-current');

          fetch('/az-book/load/' + nid, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          })
            .then(res => res.json())
            .then(data => {

              const main = document.querySelector('.region--content');
              if (main) {
                main.innerHTML = data.html;
                contentAbove = document.querySelector('.region--content-above');
                if (contentAbove) {
                  contentAbove.style.display = 'none';
                }
              }

              // Update browser URL without reload
              window.history.pushState({}, data.title, '/node/' + nid);

            });
        });
      });

      // Handle browser Back button
      window.onpopstate = function () {
        const nid = window.location.pathname.replace('/node/', '');
        if (nid && /^\d+$/.test(nid)) {
          fetch('/az-book/load/' + nid)
            .then(res => res.json())
            .then(data => {
              const main = document.querySelector('.site-main .region--content-above');
              if (main) {
                main.innerHTML = data.html;
              }
            });
        }
      };

    }
  };
})(Drupal, once);
