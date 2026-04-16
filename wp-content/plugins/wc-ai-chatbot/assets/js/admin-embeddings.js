/* WC AI Chatbot — Embeddings admin UI */
(function ($) {
  'use strict';
  if (!window.wcaicEmbeddings) return;

  const $btn      = $('#wcaic-index-all');
  const $status   = $('#wcaic-index-status');
  const $fill     = $('#wcaic-progress-fill');
  const $results  = $('#wcaic-index-results');

  $btn.on('click', function () {
    $btn.prop('disabled', true).text('Indexing…');
    $status.show();
    $fill.css('width', '0%');
    $results.text('');

    $.post(
      wcaicEmbeddings.restUrl + 'wcaic/v1/embeddings/index-all',
      {},
      function (data) {
        if (data.success) {
          $fill.css('width', '100%');
          $results.text(
            'Indexed: ' + data.indexed + ' / ' + data.total +
            ' | Errors: ' + data.errors
          );
        }
      },
      'json'
    ).always(function () {
      $btn.prop('disabled', false).text('Index All Products');
    });
  });
})(jQuery);
