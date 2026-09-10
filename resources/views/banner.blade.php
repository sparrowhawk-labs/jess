{{-- Demo-session corner banner ("気配" design, approved 2026-07-31 review).
     Injected before </body> on full-page HTML responses while a demo
     session is active. Collapses to a dot after 3s; hover re-expands.
     The recipe label ellipsizes so the exit link always stays visible.
     Corner comes from config('jess.banner_position') — bottom-right by
     default, so it never covers top-right app chrome (edit / close buttons).
     $hasReturn: the session already knows where exit should land (Referer
     recorded at landing), so the in-app origin recorder is not consulted. --}}
@php
    $position = (string) config('jess.banner_position', 'bottom-right');
    $edgeY = str_starts_with($position, 'top') ? 'top' : 'bottom';
    $edgeX = str_ends_with($position, 'left') ? 'left' : 'right';
@endphp
<style>
#jess-banner{position:fixed;{{ $edgeY }}:14px;{{ $edgeX }}:14px;z-index:2147483000;display:flex;align-items:center;gap:9px;height:30px;padding:0 13px;border-radius:999px;background:rgba(16,17,19,.84);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);color:rgba(255,255,255,.85);font-family:system-ui,sans-serif;font-size:11.5px;letter-spacing:.04em;line-height:1;max-width:min(72vw,560px);overflow:hidden;white-space:nowrap;transition:max-width .45s cubic-bezier(.4,0,.2,1),padding .45s cubic-bezier(.4,0,.2,1)}
#jess-banner .jess-dot{flex:none;width:6px;height:6px;border-radius:50%;background:#e5a94d}
#jess-banner .jess-label{flex:none;font-weight:600}
#jess-banner code{flex:0 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;font-family:ui-monospace,monospace;font-size:10.5px;letter-spacing:0;color:rgba(255,255,255,.45)}
#jess-banner a{flex:none;color:rgba(255,255,255,.78);text-decoration:none;border-bottom:1px solid rgba(255,255,255,.3);padding-bottom:1px;margin-left:3px;cursor:pointer}
#jess-banner a:hover{color:#fff;border-bottom-color:rgba(255,255,255,.6)}
#jess-banner .jess-label,#jess-banner code,#jess-banner a{transition:opacity .25s ease .2s}
#jess-banner.jess-collapsed{max-width:30px;padding:0 12px}
#jess-banner.jess-collapsed .jess-label,#jess-banner.jess-collapsed code,#jess-banner.jess-collapsed a{opacity:0;transition:opacity .12s ease}
#jess-banner.jess-collapsed:hover{max-width:min(72vw,560px);padding:0 13px}
#jess-banner.jess-collapsed:hover .jess-label,#jess-banner.jess-collapsed:hover code,#jess-banner.jess-collapsed:hover a{opacity:1;transition:opacity .25s ease .2s}
</style>
<div id="jess-banner" role="status">
  <span class="jess-dot"></span>
  <span class="jess-label">{{ __('jess::messages.banner_label') }}</span>
  <code>{{ $recipe }}</code>
  <a id="jess-exit" href="{{ route('jess.exit') }}">{{ __('jess::messages.banner_exit') }}</a>
</div>
<script>
(function () {
  var banner = document.getElementById('jess-banner');
  setTimeout(function () { banner.classList.add('jess-collapsed'); }, 3000);
@unless ($hasReturn ?? false)

  // Exit with return-to-origin: if the recorder (injected on non-demo
  // pages) captured where the user was before entering the demo, stash
  // the scroll target and route the exit through ?to=<that url>. With no
  // recording (entered from outside the app), the plain href falls back
  // to config('jess.exit_redirect') server-side. Skipped entirely when the
  // session recorded a return target at landing — the server lands there.
  document.getElementById('jess-exit').addEventListener('click', function (e) {
    var raw = null;
    try { raw = sessionStorage.getItem('jess_origin'); } catch (err) {}
    if (raw === null) return;
    try {
      var origin = JSON.parse(raw);
      sessionStorage.setItem('jess_restore', String(origin.y));
      e.preventDefault();
      location.href = this.href + (this.href.indexOf('?') >= 0 ? '&' : '?') + 'to=' + encodeURIComponent(origin.url);
    } catch (err) {}
  });
@endunless
})();
</script>
