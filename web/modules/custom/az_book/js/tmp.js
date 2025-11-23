$(document, context)
  .off('click.azBook.arrow')
  .on('click.azBook.arrow', '.az-book-toggle-arrow', function (e) {
    e.preventDefault();

    const $arrow = $(this);
    const $li = $arrow.closest('li');

    // Your real children:
    const $childList = $li.children('.az-book-list-wrapper');

    if ($childList.length) {
      $childList.toggleClass('is-collapsed');
      $arrow.text($childList.hasClass('is-collapsed') ? '▶' : '▼');
    }
    else {
      console.debug('az_book: no children found for', $arrow);
    }
  });
