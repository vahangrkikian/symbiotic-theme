/* WC AI Chatbot — Product importer admin UI */
(function ($) {
  'use strict';

  $('#wcaic-import-btn').on('click', function () {
    const url = $('#wcaic-import-url').val().trim();
    if (!url) return;

    const $btn    = $(this);
    const $result = $('#wcaic-import-result');

    $btn.prop('disabled', true).text('Importing…');
    $result.text('');

    $.ajax({
      url: ajaxurl,
      method: 'POST',
      data: {
        action:   'wcaic_import_product',
        url:      url,
        _wpnonce: $('#wcaic-import-nonce').val(),
      },
      success: function (resp) {
        if (resp.success) {
          $result.text('Product created (ID: ' + resp.data.product_id + ')');
        } else {
          $result.text('Error: ' + (resp.data || 'Unknown error'));
        }
      },
      error: function () {
        $result.text('Request failed.');
      },
    }).always(function () {
      $btn.prop('disabled', false).text('Import Product');
    });
  });
})(jQuery);
