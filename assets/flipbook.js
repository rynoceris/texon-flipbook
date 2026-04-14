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

    var zoom = el('div', 'texon-fb-zoom');
    var btnFs = el('button', null, '\u26F6');
    btnFs.type = 'button';
    btnFs.setAttribute('aria-label', 'Fullscreen');
    btnFs.title = 'Fullscreen';
    zoom.appendChild(btnFs);

    stage.appendChild(topbar);
    stage.appendChild(zoom);
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

    function toggleFullscreen(){
      var doc = document;
      var inFs = doc.fullscreenElement || doc.webkitFullscreenElement;
      if (!inFs){
        var req = inline.requestFullscreen || inline.webkitRequestFullscreen;
        if (req) req.call(inline);
        else inline.classList.add('texon-fb-is-fullscreen');
      } else {
        var ex = doc.exitFullscreen || doc.webkitExitFullscreen;
        if (ex) ex.call(doc);
        else inline.classList.remove('texon-fb-is-fullscreen');
      }
    }
    btnFs.addEventListener('click', toggleFullscreen);

    function onKey(e){
      if (!viewer.offsetParent) return;
      if (e.key === 'ArrowLeft') pageFlip.flipPrev();
      else if (e.key === 'ArrowRight') pageFlip.flipNext();
      else if (e.key === 'Home') { try { pageFlip.flip(0, 'top'); } catch(_){} }
      else if (e.key === 'End')  { try { pageFlip.flip(cfg.pageCount - 1, 'top'); } catch(_){} }
      else if (e.key && e.key.toLowerCase() === 'f') toggleFullscreen();
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
