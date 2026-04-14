(function(){
  'use strict';

  function buildPage(pageNum, cfg){
    var wrap = document.createElement('div');
    wrap.className = 'texon-fb-page';
    var img = document.createElement('img');
    img.src = cfg.pagesUrl + '/page-' + String(pageNum).padStart(2,'0') + '.jpg';
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

  function makeBtn(label, act){
    var b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('data-act', act);
    b.textContent = label;
    return b;
  }

  function init(viewer){
    var cfg = JSON.parse(viewer.getAttribute('data-texon-fb-config'));
    while (viewer.firstChild) viewer.removeChild(viewer.firstChild);

    var stage = document.createElement('div');
    stage.className = 'texon-fb-stage';
    var bookEl = document.createElement('div');
    bookEl.className = 'texon-fb-book';
    stage.appendChild(bookEl);

    var controls = document.createElement('div');
    controls.className = 'texon-fb-controls';
    var btnPrev = makeBtn('\u2039 Prev', 'prev');
    var btnNext = makeBtn('Next \u203A', 'next');
    var counter = document.createElement('span');
    counter.setAttribute('data-role', 'counter');
    counter.textContent = '1 / ' + cfg.pageCount;
    controls.appendChild(btnPrev);
    controls.appendChild(counter);
    controls.appendChild(btnNext);

    viewer.appendChild(stage);
    viewer.appendChild(controls);

    function computeSize(){
      var availW = stage.clientWidth  - 20;
      var availH = stage.clientHeight - 20;
      var aspect = cfg.pageWidth / cfg.pageHeight;
      var singleW = Math.min(availW / 2, availH * aspect);
      if (singleW < 200) singleW = 200;
      return { w: Math.floor(singleW), h: Math.floor(singleW / aspect) };
    }

    var sz = computeSize();
    var pageFlip = new St.PageFlip(bookEl, {
      width: sz.w,
      height: sz.h,
      size: 'stretch',
      minWidth: 200, maxWidth: 2000,
      minHeight: 300, maxHeight: 2500,
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

    function updateCtl(){
      var cur = pageFlip.getCurrentPageIndex() + 1;
      counter.textContent = cur + ' / ' + cfg.pageCount;
      btnPrev.disabled = cur <= 1;
      btnNext.disabled = cur >= cfg.pageCount;
    }
    pageFlip.on('flip', updateCtl);
    setTimeout(updateCtl, 50);

    btnPrev.addEventListener('click', function(){ pageFlip.flipPrev(); });
    btnNext.addEventListener('click', function(){ pageFlip.flipNext(); });

    if (typeof ResizeObserver !== 'undefined'){
      var ro = new ResizeObserver(function(){
        var s = computeSize();
        try { pageFlip.update({ width: s.w, height: s.h }); } catch(e){}
      });
      ro.observe(stage);
    }

    document.addEventListener('keydown', function(e){
      if (!viewer.offsetParent) return;
      if (e.key === 'ArrowLeft') pageFlip.flipPrev();
      if (e.key === 'ArrowRight') pageFlip.flipNext();
    });
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
      var t = e.target.closest ? e.target.closest('[data-texon-fb-close]') : null;
      if (!t) return;
      var modal = t.closest('.texon-fb-modal');
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
