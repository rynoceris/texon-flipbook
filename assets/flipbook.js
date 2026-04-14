(function(){
  'use strict';

  function padNum(n){ return String(n).padStart(2,'0'); }

  function pageImgSrc(cfg, pageNum){
    return cfg.pagesUrl + '/page-' + padNum(pageNum) + '.jpg';
  }

  function buildPage(pageNum, cfg){
    var wrap = document.createElement('div');
    wrap.className = 'texon-fb-page';
    var img = document.createElement('img');
    img.src = pageImgSrc(cfg, pageNum);
    img.alt = 'Page ' + pageNum;
    img.loading = 'lazy';
    wrap.appendChild(img);
    var spots = (cfg.hotspots && cfg.hotspots[pageNum]) || [];
    spots.forEach(function(s){
      if (!s.url) return;
      var a = document.createElement('a');
      a.className = 'texon-fb-hotspot';
      a.href = s.url;
      a.target = '_blank';
      a.rel = 'noopener';
      if (s.label) a.title = s.label;
      a.style.left   = (s.x * 100) + '%';
      a.style.top    = (s.y * 100) + '%';
      a.style.width  = (s.w * 100) + '%';
      a.style.height = (s.h * 100) + '%';
      a.addEventListener('click', function(e){ e.stopPropagation(); });
      wrap.appendChild(a);
    });
    return wrap;
  }

  function el(tag, className, text){
    var e = document.createElement(tag);
    if (className) e.className = className;
    if (text != null) e.textContent = text;
    return e;
  }

  function init(viewer){
    var cfg = JSON.parse(viewer.getAttribute('data-texon-fb-config'));
    var inline = viewer.closest('.texon-fb-inline') || viewer;
    while (viewer.firstChild) viewer.removeChild(viewer.firstChild);

    var stage = el('div', 'texon-fb-stage');
    var bookEl = el('div', 'texon-fb-book');
    stage.appendChild(bookEl);

    var btnSidePrev = el('button', 'texon-fb-side texon-fb-side-prev');
    btnSidePrev.type = 'button';
    btnSidePrev.setAttribute('aria-label', 'Previous page');
    btnSidePrev.appendChild(document.createTextNode('\u2039'));
    var btnSideNext = el('button', 'texon-fb-side texon-fb-side-next');
    btnSideNext.type = 'button';
    btnSideNext.setAttribute('aria-label', 'Next page');
    btnSideNext.appendChild(document.createTextNode('\u203A'));

    var topbar = el('div', 'texon-fb-topbar');
    var counter = el('span', null, '1 / ' + cfg.pageCount);
    topbar.appendChild(counter);

    // 24×24 icons (stroke-based, currentColor so CSS controls the color)
    var ICONS = {
      zoomIn:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/><path d="M11 8v6M8 11h6"/></svg>',
      zoomOut:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/><path d="M8 11h6"/></svg>',
      search:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>',
      share:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="12" r="2.4"/><circle cx="18" cy="6" r="2.4"/><circle cx="18" cy="18" r="2.4"/><path d="M8.1 10.9l7.8-3.8M8.1 13.1l7.8 3.8"/></svg>',
      download:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 20h16"/></svg>',
      print:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 9V4h10v5"/><rect x="4" y="9" width="16" height="8" rx="1.5"/><rect x="7" y="14" width="10" height="6" rx="1"/><circle cx="17" cy="12" r=".9" fill="currentColor" stroke="none"/></svg>',
      fullscreen: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>',
      exit:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg>',
      close:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>'
    };
    function makeIconBtn(iconKey, label){
      var b = document.createElement('button');
      b.type = 'button';
      b.innerHTML = ICONS[iconKey];
      b.title = label;
      b.setAttribute('aria-label', label);
      b.setAttribute('data-icon', iconKey);
      return b;
    }
    var tools = el('div', 'texon-fb-tools');
    var btnZoomIn  = makeIconBtn('zoomIn',  'Zoom in');
    var btnZoomOut = makeIconBtn('zoomOut', 'Zoom out');
    tools.appendChild(btnZoomIn);
    tools.appendChild(btnZoomOut);
    var btnSearch = makeIconBtn('search', 'Search');
    var btnShare  = makeIconBtn('share',  'Share');
    var btnDl     = makeIconBtn('download', 'Download PDF');
    var btnPrint  = makeIconBtn('print', 'Print');
    var btnFs     = makeIconBtn('fullscreen', 'Fullscreen');
    if (cfg.textUrl)       tools.appendChild(btnSearch);
    tools.appendChild(btnShare);
    if (cfg.pdfUrl){ tools.appendChild(btnDl); tools.appendChild(btnPrint); }
    tools.appendChild(btnFs);

    stage.appendChild(topbar);
    stage.appendChild(tools);
    stage.appendChild(btnSidePrev);
    stage.appendChild(btnSideNext);

    var thumbs = el('div', 'texon-fb-thumbs');
    thumbs.setAttribute('role', 'tablist');
    var thumbBtns = [];
    for (var t = 1; t <= cfg.pageCount; t++){
      var tb = document.createElement('button');
      tb.type = 'button';
      tb.className = 'texon-fb-thumb';
      tb.setAttribute('role', 'tab');
      tb.setAttribute('data-page', t);
      tb.setAttribute('aria-label', 'Go to page ' + t);
      var ti = document.createElement('img');
      ti.src = pageImgSrc(cfg, t);
      ti.loading = 'lazy';
      ti.alt = '';
      tb.appendChild(ti);
      var num = el('span', 'texon-fb-thumb-num', String(t));
      tb.appendChild(num);
      thumbs.appendChild(tb);
      thumbBtns.push(tb);
    }

    viewer.appendChild(stage);
    viewer.appendChild(thumbs);

    var aspect = cfg.pageWidth / cfg.pageHeight;
    function computeSize(){
      var rect = stage.getBoundingClientRect();
      var availW = Math.max(200, rect.width  - 120);
      var availH = Math.max(200, rect.height - 20);
      var isPortrait = rect.width < 700;
      var pagesAcross = isPortrait ? 1 : 2;
      var singleW = Math.min(availW / pagesAcross, availH * aspect);
      return { w: Math.max(100, Math.floor(singleW)), h: Math.max(100, Math.floor(singleW / aspect)) };
    }

    var sz = computeSize();
    var pageFlip = new St.PageFlip(bookEl, {
      width: sz.w,
      height: sz.h,
      size: 'stretch',
      minWidth: 200, maxWidth: 2400,
      minHeight: 200, maxHeight: 3000,
      maxShadowOpacity: 0.5,
      showCover: true,
      usePortrait: true,
      mobileScrollSupport: false,
      flippingTime: 700,
      drawShadow: true
    });

    var pages = [];
    for (var i = 1; i <= cfg.pageCount; i++) pages.push(buildPage(i, cfg));
    pageFlip.loadFromHTML(pages);

    var suppressHash = false;
    function readHash(){
      var m = /^#?page-(\d+)/i.exec(window.location.hash || '');
      if (!m) return 0;
      var p = parseInt(m[1], 10);
      if (p < 1 || p > cfg.pageCount) return 0;
      return p;
    }
    function writeHash(p){
      suppressHash = true;
      try { history.replaceState(null, '', '#page-' + p); } catch(e){}
      setTimeout(function(){ suppressHash = false; }, 10);
    }

    function activeThumb(cur){
      thumbBtns.forEach(function(b, idx){
        var on = (idx + 1 === cur);
        b.setAttribute('aria-current', on ? 'true' : 'false');
        if (on){
          var r = b.getBoundingClientRect();
          var pr = thumbs.getBoundingClientRect();
          if (r.left < pr.left || r.right > pr.right){
            b.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
          }
        }
      });
    }
    function update(){
      var cur = pageFlip.getCurrentPageIndex() + 1;
      counter.textContent = cur + ' / ' + cfg.pageCount;
      btnSidePrev.disabled = cur <= 1;
      btnSideNext.disabled = cur >= cfg.pageCount;
      activeThumb(cur);
      writeHash(cur);
      if (typeof resetZoom === 'function' && zState && zState.scale > 1) resetZoom();
    }
    pageFlip.on('flip', update);

    setTimeout(function(){
      var h = readHash();
      if (h > 1) {
        try { pageFlip.flip(h - 1, 'top'); } catch(e){ try { pageFlip.turnToPage(h - 1); } catch(_){} }
      }
      update();
    }, 80);

    btnSidePrev.addEventListener('click', function(){ pageFlip.flipPrev(); });
    btnSideNext.addEventListener('click', function(){ pageFlip.flipNext(); });
    thumbs.addEventListener('click', function(e){
      var targ = e.target.closest('.texon-fb-thumb');
      if (!targ) return;
      var p = parseInt(targ.getAttribute('data-page'), 10);
      if (!isFinite(p)) return;
      try { pageFlip.flip(p - 1, 'top'); } catch(err){ try { pageFlip.turnToPage(p - 1); } catch(_){} }
    });

    // iOS Safari/Chrome: Fullscreen API is unavailable on non-video elements,
    // so we always use a CSS-class fallback there.
    var isIOS = /iP(hone|od|ad)/.test(navigator.platform) ||
                (navigator.userAgent.indexOf('Mac') > -1 && 'ontouchend' in document);
    var hasFsApi = !isIOS && !!(
      inline.requestFullscreen || inline.webkitRequestFullscreen
    );
    var cssFs = false;
    function setFsIcon(on){
      btnFs.innerHTML = on ? ICONS.exit : ICONS.fullscreen;
      btnFs.setAttribute('aria-label', on ? 'Exit fullscreen' : 'Fullscreen');
    }
    setFsIcon(false);

    function nudgeResize(){
      var s = computeSize();
      try { pageFlip.update({ width: s.w, height: s.h }); } catch(e){}
    }

    // Portal the inline element out of any ancestor stacking context
    // (themes often wrap content in transform/filter/will-change parents,
    // which traps position:fixed elements and z-index inside that context —
    // symptom on iOS: other page chrome paints over the fullscreen view
    // and/or touch events fall through to it).
    var portalAnchor = null; // placeholder <div> we leave behind to restore position
    function portalToBody(){
      if (portalAnchor) return;
      portalAnchor = document.createElement('div');
      portalAnchor.style.display = 'none';
      inline.parentNode.insertBefore(portalAnchor, inline);
      document.body.appendChild(inline);
    }
    function restoreFromBody(){
      if (!portalAnchor) return;
      portalAnchor.parentNode.insertBefore(inline, portalAnchor);
      portalAnchor.parentNode.removeChild(portalAnchor);
      portalAnchor = null;
    }

    function enterCssFs(){
      cssFs = true;
      portalToBody();
      inline.classList.add('texon-fb-is-fullscreen');
      document.documentElement.classList.add('texon-fb-fs-lock');
      document.body.classList.add('texon-fb-fs-lock');
      setFsIcon(true);
      setTimeout(nudgeResize, 60);
    }
    function exitCssFs(){
      cssFs = false;
      inline.classList.remove('texon-fb-is-fullscreen');
      document.documentElement.classList.remove('texon-fb-fs-lock');
      document.body.classList.remove('texon-fb-fs-lock');
      restoreFromBody();
      setFsIcon(false);
      setTimeout(nudgeResize, 60);
    }

    function toggleFullscreen(){
      var doc = document;
      var apiActive = !!(doc.fullscreenElement || doc.webkitFullscreenElement);
      if (apiActive){
        var ex = doc.exitFullscreen || doc.webkitExitFullscreen;
        if (ex) ex.call(doc);
        return;
      }
      if (cssFs){ exitCssFs(); return; }
      if (hasFsApi){
        var req = inline.requestFullscreen || inline.webkitRequestFullscreen;
        try {
          var p = req.call(inline);
          if (p && typeof p.then === 'function') p.catch(function(){ enterCssFs(); });
        } catch(e){ enterCssFs(); }
      } else {
        enterCssFs();
      }
    }
    btnFs.addEventListener('click', toggleFullscreen);

    // === Zoom / pan ===
    // Apply CSS transform to bookEl. StPageFlip's internal transforms on page
    // blocks compose with this outer transform, so flipping still renders
    // correctly when scale === 1. We disable flip gestures while zoomed by
    // activating a transparent panner overlay that intercepts pointer events.
    var MIN_ZOOM = 1, MAX_ZOOM = 4, ZOOM_STEP = 1.3;
    var zState = { scale: 1, x: 0, y: 0 };
    var panner = el('div', 'texon-fb-panner');
    stage.appendChild(panner);

    function clampPan(){
      if (zState.scale <= 1){ zState.x = 0; zState.y = 0; return; }
      // Allow panning roughly up to the overflow region in each direction.
      var rect = bookEl.getBoundingClientRect();
      var baseW = rect.width  / zState.scale;
      var baseH = rect.height / zState.scale;
      var maxX = Math.max(0, (baseW * zState.scale - baseW) / 2);
      var maxY = Math.max(0, (baseH * zState.scale - baseH) / 2);
      if (zState.x >  maxX) zState.x =  maxX;
      if (zState.x < -maxX) zState.x = -maxX;
      if (zState.y >  maxY) zState.y =  maxY;
      if (zState.y < -maxY) zState.y = -maxY;
    }
    function applyZoom(){
      clampPan();
      bookEl.style.transformOrigin = '50% 50%';
      bookEl.style.transform = 'translate(' + zState.x + 'px,' + zState.y + 'px) scale(' + zState.scale + ')';
      bookEl.style.transition = 'transform .12s';
      var active = zState.scale > 1.001;
      panner.classList.toggle('on', active);
      btnZoomOut.disabled = zState.scale <= MIN_ZOOM + 0.001;
      btnZoomIn.disabled  = zState.scale >= MAX_ZOOM - 0.001;
      setTimeout(function(){ bookEl.style.transition = ''; }, 140);
    }
    function zoomBy(factor, cx, cy){
      var prev = zState.scale;
      var next = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, prev * factor));
      if (next === prev) return;
      // Keep the cursor/pinch focal point stable
      if (cx != null && cy != null){
        var rect = stage.getBoundingClientRect();
        var sx = cx - rect.left - rect.width / 2;
        var sy = cy - rect.top  - rect.height / 2;
        var k = next / prev;
        zState.x = sx - (sx - zState.x) * k;
        zState.y = sy - (sy - zState.y) * k;
      }
      zState.scale = next;
      applyZoom();
    }
    function resetZoom(){ zState.scale = 1; zState.x = 0; zState.y = 0; applyZoom(); }
    btnZoomIn.addEventListener('click',  function(){ var r = stage.getBoundingClientRect(); zoomBy(ZOOM_STEP, r.left + r.width/2, r.top + r.height/2); });
    btnZoomOut.addEventListener('click', function(){ var r = stage.getBoundingClientRect(); zoomBy(1/ZOOM_STEP, r.left + r.width/2, r.top + r.height/2); });

    // Pointer drag to pan (mouse + single-finger touch) when zoomed
    var drag = null;
    panner.addEventListener('pointerdown', function(e){
      if (zState.scale <= 1 || e.isPrimary === false) return;
      if (e.pointerType === 'touch' && pinch.active) return;
      panner.setPointerCapture(e.pointerId);
      drag = { id: e.pointerId, startX: e.clientX, startY: e.clientY, ox: zState.x, oy: zState.y };
    });
    panner.addEventListener('pointermove', function(e){
      if (!drag || e.pointerId !== drag.id) return;
      zState.x = drag.ox + (e.clientX - drag.startX);
      zState.y = drag.oy + (e.clientY - drag.startY);
      applyZoom();
    });
    function endDrag(e){
      if (drag && e.pointerId === drag.id){
        try { panner.releasePointerCapture(drag.id); } catch(_){}
        drag = null;
      }
    }
    panner.addEventListener('pointerup', endDrag);
    panner.addEventListener('pointercancel', endDrag);

    // Pinch-to-zoom (touch) — handled on the stage so it works at scale 1 too
    var pinch = { active: false, start: 0, scale0: 1, cx: 0, cy: 0 };
    stage.addEventListener('touchstart', function(e){
      if (e.touches.length === 2){
        var a = e.touches[0], b = e.touches[1];
        pinch.active = true;
        pinch.start = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
        pinch.scale0 = zState.scale;
        pinch.cx = (a.clientX + b.clientX) / 2;
        pinch.cy = (a.clientY + b.clientY) / 2;
      }
    }, { passive: true });
    stage.addEventListener('touchmove', function(e){
      if (!pinch.active || e.touches.length !== 2) return;
      e.preventDefault();
      var a = e.touches[0], b = e.touches[1];
      var dist = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
      var cx = (a.clientX + b.clientX) / 2;
      var cy = (a.clientY + b.clientY) / 2;
      var next = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, pinch.scale0 * (dist / pinch.start)));
      // Apply centred on the gesture midpoint
      var rect = stage.getBoundingClientRect();
      var sx = cx - rect.left - rect.width / 2;
      var sy = cy - rect.top  - rect.height / 2;
      var k = next / zState.scale;
      zState.x = sx - (sx - zState.x) * k;
      zState.y = sy - (sy - zState.y) * k;
      zState.scale = next;
      applyZoom();
    }, { passive: false });
    stage.addEventListener('touchend', function(e){
      if (e.touches.length < 2) pinch.active = false;
    });

    // Double-click/tap to reset
    bookEl.addEventListener('dblclick', function(e){ if (zState.scale > 1){ e.preventDefault(); resetZoom(); } });

    applyZoom();

    // === Download ===
    btnDl.addEventListener('click', function(){
      if (!cfg.pdfUrl) return;
      var a = document.createElement('a');
      a.href = cfg.pdfUrl;
      a.download = '';
      a.rel = 'noopener';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    });

    // === Print (opens PDF in new tab; browser's PDF viewer prints natively) ===
    btnPrint.addEventListener('click', function(){
      if (!cfg.pdfUrl) return;
      var w = window.open(cfg.pdfUrl, '_blank', 'noopener');
      if (w){ try { w.focus(); } catch(e){} }
    });

    // === Share ===
    function currentShareUrl(){
      var base = location.href.split('#')[0];
      var cur = pageFlip.getCurrentPageIndex() + 1;
      return base + '#page-' + cur;
    }
    btnShare.addEventListener('click', function(){
      var url = currentShareUrl();
      var title = cfg.title || document.title;
      if (navigator.share){
        navigator.share({ title: title, url: url }).catch(function(){});
        return;
      }
      if (navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(url).then(function(){
          flashToast('Link copied');
        }, function(){ fallbackCopyPrompt(url); });
      } else {
        fallbackCopyPrompt(url);
      }
    });
    function fallbackCopyPrompt(url){
      try { window.prompt('Copy this link:', url); } catch(e){}
    }
    function flashToast(msg){
      var t = document.createElement('div');
      t.className = 'texon-fb-toast';
      t.textContent = msg;
      (inline || viewer).appendChild(t);
      setTimeout(function(){ t.classList.add('on'); }, 10);
      setTimeout(function(){ t.classList.remove('on'); setTimeout(function(){ t.remove(); }, 300); }, 1500);
    }

    // === Search ===
    var searchIndex = null; // { "1": "text...", ... }
    var searchPanel = null;
    function loadSearchIndex(){
      if (searchIndex) return Promise.resolve(searchIndex);
      if (!cfg.textUrl) return Promise.reject();
      return fetch(cfg.textUrl, { credentials: 'same-origin' })
        .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
        .then(function(j){ searchIndex = j || {}; return searchIndex; });
    }
    function openSearch(){
      if (searchPanel){ searchPanel.classList.add('on'); searchPanel.querySelector('input').focus(); return; }
      searchPanel = el('div', 'texon-fb-search');
      var bar = el('div', 'texon-fb-search-bar');
      var inp = document.createElement('input');
      inp.type = 'search';
      inp.placeholder = 'Search catalog…';
      inp.setAttribute('aria-label', 'Search catalog');
      var close = makeIconBtn('close', 'Close search');
      close.className = 'texon-fb-search-close';
      bar.appendChild(inp); bar.appendChild(close);
      var results = el('div', 'texon-fb-search-results');
      results.setAttribute('role', 'listbox');
      searchPanel.appendChild(bar); searchPanel.appendChild(results);
      inline.appendChild(searchPanel);

      close.addEventListener('click', function(){ searchPanel.classList.remove('on'); });
      searchPanel.addEventListener('keydown', function(e){ if (e.key === 'Escape') searchPanel.classList.remove('on'); });

      var debounce;
      inp.addEventListener('input', function(){
        clearTimeout(debounce);
        debounce = setTimeout(function(){ runSearch(inp.value.trim(), results); }, 120);
      });
      results.addEventListener('click', function(e){
        var r = e.target.closest('[data-page]');
        if (!r) return;
        var p = parseInt(r.getAttribute('data-page'), 10);
        if (isFinite(p)){
          try { pageFlip.flip(p - 1, 'top'); } catch(_){}
          searchPanel.classList.remove('on');
        }
      });

      requestAnimationFrame(function(){ searchPanel.classList.add('on'); inp.focus(); });
    }
    function escHtml(s){ return String(s).replace(/[&<>"']/g, function(c){ return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    function runSearch(q, resultsEl){
      resultsEl.textContent = '';
      if (!q) return;
      loadSearchIndex().then(function(idx){
        var needle = q.toLowerCase();
        var hits = [];
        Object.keys(idx).sort(function(a,b){ return parseInt(a,10)-parseInt(b,10); }).forEach(function(p){
          var txt = (idx[p] || '');
          var lc = txt.toLowerCase();
          var pos = lc.indexOf(needle);
          if (pos === -1) return;
          var start = Math.max(0, pos - 40);
          var end   = Math.min(txt.length, pos + q.length + 60);
          var snippet = (start > 0 ? '…' : '') + txt.slice(start, end) + (end < txt.length ? '…' : '');
          var safe = escHtml(snippet);
          var rx = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
          var highlighted = safe.replace(rx, '<mark>$1</mark>');
          hits.push({ page: parseInt(p, 10), html: highlighted });
        });
        if (!hits.length){
          var none = el('div', 'texon-fb-search-none', 'No results for "' + q + '"');
          resultsEl.appendChild(none);
          return;
        }
        hits.forEach(function(h){
          var row = document.createElement('button');
          row.type = 'button';
          row.className = 'texon-fb-search-result';
          row.setAttribute('data-page', h.page);
          // Use textContent for the page label; innerHTML only for the pre-escaped+marked snippet
          var label = el('span', 'texon-fb-search-page', 'Page ' + h.page);
          var snip  = document.createElement('span');
          snip.className = 'texon-fb-search-snippet';
          snip.innerHTML = h.html;
          row.appendChild(label); row.appendChild(snip);
          resultsEl.appendChild(row);
        });
      }).catch(function(){
        var err = el('div', 'texon-fb-search-none', 'Search index not available');
        resultsEl.appendChild(err);
      });
    }
    btnSearch.addEventListener('click', openSearch);

    // Sync icon + re-layout when the native Fullscreen API state changes
    function onFsChange(){
      var on = !!(document.fullscreenElement || document.webkitFullscreenElement);
      setFsIcon(on);
      setTimeout(nudgeResize, 60);
    }
    document.addEventListener('fullscreenchange', onFsChange);
    document.addEventListener('webkitfullscreenchange', onFsChange);

    // Orientation change / viewport resize in CSS fullscreen
    window.addEventListener('orientationchange', function(){ setTimeout(nudgeResize, 200); });

    function onKey(e){
      if (!viewer.offsetParent) return;
      if (e.key === 'ArrowLeft') pageFlip.flipPrev();
      else if (e.key === 'ArrowRight') pageFlip.flipNext();
      else if (e.key === 'Home') { try { pageFlip.flip(0, 'top'); } catch(_){} }
      else if (e.key === 'End')  { try { pageFlip.flip(cfg.pageCount - 1, 'top'); } catch(_){} }
      else if (e.key && e.key.toLowerCase() === 'f') toggleFullscreen();
      else if (e.key === '/' && cfg.textUrl && !e.ctrlKey && !e.metaKey){
        var tag = (e.target && e.target.tagName) || '';
        if (tag !== 'INPUT' && tag !== 'TEXTAREA'){ e.preventDefault(); openSearch(); }
      }
      else if (e.key === 'Escape' && cssFs) exitCssFs();
    }
    document.addEventListener('keydown', onKey);

    window.addEventListener('hashchange', function(){
      if (suppressHash) return;
      var h = readHash();
      if (h > 0){
        var cur = pageFlip.getCurrentPageIndex() + 1;
        if (h !== cur) { try { pageFlip.flip(h - 1, 'top'); } catch(_){} }
      }
    });

    if (typeof ResizeObserver !== 'undefined'){
      var ro = new ResizeObserver(function(){
        var s = computeSize();
        try { pageFlip.update({ width: s.w, height: s.h }); } catch(e){}
      });
      ro.observe(stage);
    } else {
      window.addEventListener('resize', function(){
        var s = computeSize();
        try { pageFlip.update({ width: s.w, height: s.h }); } catch(e){}
      });
    }
  }

  function initAll(root){
    (root || document).querySelectorAll('.texon-fb-viewer').forEach(function(v){
      if (v.dataset.texonInited) return;
      var modal = v.closest('.texon-fb-modal');
      if (modal && modal.style.display === 'none') return;
      v.dataset.texonInited = '1';
      init(v);
    });
  }

  function wireModals(){
    document.querySelectorAll('[data-texon-fb-open]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var modal = document.getElementById(btn.getAttribute('data-texon-fb-open'));
        if (!modal) return;
        modal.style.display = 'flex';
        document.body.classList.add('texon-fb-open');
        initAll(modal);
      });
    });
    document.addEventListener('click', function(e){
      var targ = e.target.closest ? e.target.closest('[data-texon-fb-close]') : null;
      if (!targ) return;
      var modal = targ.closest('.texon-fb-modal');
      if (modal){
        modal.style.display = 'none';
        document.body.classList.remove('texon-fb-open');
      }
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape'){
        document.querySelectorAll('.texon-fb-modal').forEach(function(m){
          if (m.style.display !== 'none'){
            m.style.display = 'none';
            document.body.classList.remove('texon-fb-open');
          }
        });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ wireModals(); initAll(); });
  } else { wireModals(); initAll(); }
})();
