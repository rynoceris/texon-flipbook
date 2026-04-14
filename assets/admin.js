(function($){
  'use strict';

  // Media picker for PDF
  $(document).on('click', '#texon-pick-pdf', function(e){
    e.preventDefault();
    var frame = wp.media({ title: 'Select PDF', library: { type: 'application/pdf' }, multiple: false });
    frame.on('select', function(){
      var att = frame.state().get('selection').first().toJSON();
      // Convert URL to filesystem path via guess: uploads basedir mapping is unknown in JS, so just fill URL and warn
      $('#pdf_path').val(att.url);
    });
    frame.open();
  });

  // Hotspot editor
  var $canvas = $('#texon-page-canvas');
  if (!$canvas.length) return;

  var bookId    = $canvas.data('book-id');
  var pagesUrl  = $canvas.data('pages-url');
  var pageCount = parseInt($canvas.data('page-count'), 10);
  var hotspots  = $canvas.data('hotspots') || {};
  if (typeof hotspots === 'string') { try { hotspots = JSON.parse(hotspots); } catch(e){ hotspots = {}; } }
  var currentPage = 1;
  var saveTimer = null;

  function pad(n){ return String(n).padStart(2,'0'); }

  function render(){
    $canvas.empty();
    var $img = $('<img class="texon-bg" alt="">').attr('src', pagesUrl + '/page-' + pad(currentPage) + '.jpg');
    $canvas.append($img);
    var spots = hotspots[currentPage] || [];
    spots.forEach(function(s, idx){ $canvas.append(buildSpot(s, idx)); });
  }

  function buildSpot(s, idx){
    var $s = $('<div class="texon-spot"></div>')
      .css({ left:(s.x*100)+'%', top:(s.y*100)+'%', width:(s.w*100)+'%', height:(s.h*100)+'%' })
      .data('idx', idx);
    if (s.url || s.label) $s.append($('<span class="texon-label"></span>').text(s.label || s.url));
    $s.append('<span class="texon-handle"></span>');
    return $s;
  }

  function scheduleSave(){
    $('#texon-save-status').text('Saving…').attr('class','saving');
    clearTimeout(saveTimer);
    saveTimer = setTimeout(save, 500);
  }
  function save(){
    $.post(TexonFlipbookAdmin.ajaxurl, {
      action: 'texon_flipbook_save_hotspots',
      nonce: TexonFlipbookAdmin.nonce,
      id: bookId,
      hotspots: JSON.stringify(hotspots)
    }).done(function(r){
      if (r && r.success) $('#texon-save-status').text('Saved ✓').attr('class','saved');
      else $('#texon-save-status').text('Error saving').attr('class','saving');
    }).fail(function(){ $('#texon-save-status').text('Error saving').attr('class','saving'); });
  }

  function getSpots(){ if (!hotspots[currentPage]) hotspots[currentPage] = []; return hotspots[currentPage]; }

  // Draw new
  var drawing = null;
  $canvas.on('mousedown', function(e){
    if ($(e.target).closest('.texon-spot').length) return;
    var rect = this.getBoundingClientRect();
    drawing = {
      startX: (e.clientX - rect.left) / rect.width,
      startY: (e.clientY - rect.top)  / rect.height,
      rect: rect
    };
    $canvas.addClass('drawing');
    var s = { x: drawing.startX, y: drawing.startY, w:0, h:0, url:'', label:'' };
    getSpots().push(s);
    drawing.idx = getSpots().length - 1;
    render();
  });
  $(document).on('mousemove', function(e){
    if (!drawing) return;
    var rect = drawing.rect;
    var cx = Math.max(0, Math.min(1, (e.clientX - rect.left)/rect.width));
    var cy = Math.max(0, Math.min(1, (e.clientY - rect.top) /rect.height));
    var s = getSpots()[drawing.idx];
    s.x = Math.min(drawing.startX, cx);
    s.y = Math.min(drawing.startY, cy);
    s.w = Math.abs(cx - drawing.startX);
    s.h = Math.abs(cy - drawing.startY);
    render();
  });
  $(document).on('mouseup', function(){
    if (!drawing) return;
    var s = getSpots()[drawing.idx];
    $canvas.removeClass('drawing');
    if (s.w < 0.005 || s.h < 0.005){
      getSpots().pop();
      render();
    } else {
      var url = prompt('Enter destination URL for this hotspot:', '');
      if (url === null) { getSpots().pop(); render(); drawing = null; return; }
      var label = prompt('Label (optional):', '') || '';
      s.url = url; s.label = label;
      render(); scheduleSave();
    }
    drawing = null;
  });

  // Click existing to edit; right-click to delete; drag handle to resize; drag body to move
  $canvas.on('click', '.texon-spot', function(e){
    if (drawing) return;
    e.stopPropagation();
    var idx = $(this).data('idx');
    var s = getSpots()[idx];
    var url = prompt('Edit URL (leave blank to delete):', s.url || '');
    if (url === null) return;
    if (!url){ getSpots().splice(idx,1); render(); scheduleSave(); return; }
    var label = prompt('Label (optional):', s.label || '') || '';
    s.url = url; s.label = label;
    render(); scheduleSave();
  });
  $canvas.on('contextmenu', '.texon-spot', function(e){
    e.preventDefault();
    if (!confirm('Delete this hotspot?')) return;
    var idx = $(this).data('idx');
    getSpots().splice(idx,1);
    render(); scheduleSave();
  });

  // Drag to move / resize
  var drag = null;
  $canvas.on('mousedown', '.texon-spot', function(e){
    e.stopPropagation();
    var rect = $canvas[0].getBoundingClientRect();
    var idx = $(this).data('idx');
    var s = getSpots()[idx];
    var isResize = $(e.target).hasClass('texon-handle');
    drag = {
      idx: idx, rect: rect, resize: isResize,
      offX: (e.clientX - rect.left)/rect.width  - s.x,
      offY: (e.clientY - rect.top) /rect.height - s.y
    };
  });
  $(document).on('mousemove', function(e){
    if (!drag) return;
    var rect = drag.rect;
    var cx = (e.clientX - rect.left)/rect.width;
    var cy = (e.clientY - rect.top) /rect.height;
    var s = getSpots()[drag.idx];
    if (drag.resize){
      s.w = Math.max(0.005, Math.min(1 - s.x, cx - s.x));
      s.h = Math.max(0.005, Math.min(1 - s.y, cy - s.y));
    } else {
      s.x = Math.max(0, Math.min(1 - s.w, cx - drag.offX));
      s.y = Math.max(0, Math.min(1 - s.h, cy - drag.offY));
    }
    render();
  });
  $(document).on('mouseup', function(){
    if (drag){ drag = null; scheduleSave(); }
  });

  $('#texon-page-select').on('change', function(){
    currentPage = parseInt(this.value, 10);
    render();
  });

  render();
})(jQuery);
