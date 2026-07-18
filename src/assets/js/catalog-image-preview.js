document.querySelectorAll('[data-catalog-image-preview]').forEach(function (container) {
  var input = container.querySelector('[data-catalog-image-input]');
  var preview = container.querySelector('[data-catalog-image-target]');
  var info = container.querySelector('[data-catalog-image-info]');
  var objectUrl = null;
  if (!input || !preview || !info) return;
  input.addEventListener('change', function () {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null;
    var file = input.files && input.files[0] ? input.files[0] : null;
    if (!file) return;
    objectUrl = URL.createObjectURL(file);
    preview.src = objectUrl;
    info.textContent = file.name + ' · ' + Math.ceil(file.size / 1024) + ' KB';
  });
  window.addEventListener('beforeunload', function () { if (objectUrl) URL.revokeObjectURL(objectUrl); });
});
