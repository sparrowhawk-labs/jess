{{-- Injected before </body> on full-page HTML responses of NON-demo pages
     (only while jess.enabled). Two jobs:
     1. Record "where I am" (URL + scroll position) into sessionStorage so
        the demo banner's exit can bring the user straight back here.
     2. If we just arrived via that exit, restore the scroll position and
        show a brief confirmation toast. --}}
<script>
(function () {
  var save = function () {
    try {
      sessionStorage.setItem('jess_origin', JSON.stringify({
        url: location.pathname + location.search,
        y: window.scrollY || 0
      }));
    } catch (e) {}
  };
  addEventListener('scroll', save, { passive: true });
  addEventListener('pagehide', save);
  save();

  var restore = null;
  try { restore = sessionStorage.getItem('jess_restore'); } catch (e) {}
  if (restore === null) return;
  try { sessionStorage.removeItem('jess_restore'); } catch (e) {}
  window.scrollTo({ top: parseInt(restore, 10) || 0 });

  var toast = document.createElement('div');
  toast.textContent = @json(__('jess::messages.banner_restored'));
  toast.style.cssText = 'position:fixed;bottom:18px;left:50%;transform:translateX(-50%);z-index:2147483000;background:rgba(16,17,19,.88);color:rgba(255,255,255,.9);font-family:system-ui,sans-serif;font-size:12px;line-height:1;padding:10px 16px;border-radius:999px;opacity:0;transition:opacity .4s;pointer-events:none';
  document.body.appendChild(toast);
  requestAnimationFrame(function () { toast.style.opacity = '1'; });
  setTimeout(function () { toast.style.opacity = '0'; }, 2800);
})();
</script>
