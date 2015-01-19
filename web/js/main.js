$(function() {
  if (typeof(ga) === typeof(Function)) // analytics is enabled
  {
    $('body').on('click', '[data-ga]', function() {
      var data = $(this).data('ga');
      ga('send', {
        hitType: 'event',                 // Required.
        eventCategory: data.category,     // Required.
        eventAction: data.action,         // Required.
        eventLabel: data.label || 'none'
      });
    });
  }
});