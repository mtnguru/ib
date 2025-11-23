(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.azBook = {
    attach(context) {
      // Process existing elements once.
      once('azBookInit', '#az-book', context).forEach(processAzBook);

      // Create a single observer for the whole page.
      once('azBookObserver', 'body', document).forEach(() => {
        const observer = new MutationObserver(handleMutations);

        observer.observe(document.querySelector('#az-book') || document.body, {
          childList: true,
          subtree: true
        });

        console.log('azBook observer attached');
      });

      // -----------------------------
      // Helper: applies hide/show logic
      // -----------------------------
      function processAzBook(el) {
        const $el = $(el);
        const hide = localStorage.getItem('az_book_unpublished') === 'false' ? false : true;
        const expand = localStorage.getItem('az_book_expand') === 'false' ? false : true;

        // We always use the hyphen version to match your Twig.
        (hide) ? $el.removeClass('hide-unpublished') : $el.addClass('hide-unpublished');
        (expand) ? $el.removeClass('expanded') : $el.addClass('expanded');
      }

      // --------------------------------
      // Helper: run processAzBook on updates
      // --------------------------------
      function handleMutations(mutations) {
        // Anytime #az-book changes internally, re-process
        const el = document.querySelector('#az-book');
        if (el) {
          processAzBook(el);
        }
      }

      // User presses arrow button to expand/collapse menu item
      $(document, context)
        .off('click.azBook.arrow')
        .on('click.azBook.arrow', 'li.has-children', function (e) {
          // ignore clicks on the link itself
          if (e.target.tagName === 'A' || e.target.closest('a')) {
            return; // exit early
          }
          let $li = $(e.target).closest('li');
          $li.toggleClass('is-expanded');
          e.stopPropagation();
          e.preventDefault();
        });

      // toggle show unpublished button
      $(document, context)
        .off('click.azBook.toggleUnpub')
        .on('click.azBook.toggleUnpub', '#az-toggle-unpublished', function (e) {
          e.preventDefault();
          e.stopPropagation();
          const $az_book = $('#az-book');
          $az_book.toggleClass('hide-unpublished');
          if ($az_book.hasClass('hide-unpublished')) {
            localStorage.setItem('az_book_unpublished', 'false');
          } else {
            localStorage.setItem('az_book_unpublished', 'true');
          }
        });

      // toggle expand button
      $(document, context)
        .off('click.azBook.toggleExpand')
        .on('click.azBook.toggleExpand', '#az-toggle-expand', function (e) {
          e.preventDefault();
          e.stopPropagation();
          const $az_book = $('#az-book');
          $az_book.toggleClass('expanded');
          if ($az_book.hasClass('expanded')) {
            localStorage.setItem('az_book_expand', 'false');
          } else {
            localStorage.setItem('az_book_expand', 'true');
          }
        });
    }
  }
})(jQuery, Drupal, drupalSettings);


