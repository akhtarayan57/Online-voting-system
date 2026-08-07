<!-- Loading Screen -->
<div class="loading-overlay" id="loadingScreen">
  <div class="loader"></div>
  <p class="loading-text">Please wait...</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var overlay = document.getElementById('loadingScreen');

  // Show loading on all internal link clicks
  document.querySelectorAll('a[href]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var href = this.getAttribute('href');
      if (
        href &&
        !href.startsWith('#') &&
        !href.startsWith('http') &&
        !href.startsWith('mailto') &&
        href !== ''
      ) {
        overlay.classList.add('show');
      }
    });
  });

  // Show loading on all form submits
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      overlay.classList.add('show');
    });
  });

  // Hide loading when page is fully loaded
  window.addEventListener('pageshow', function () {
    overlay.classList.remove('show');
  });
});
</script>