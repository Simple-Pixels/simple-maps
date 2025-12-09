(function () {
    if (window.__spMapsInitAttached) return;
    window.__spMapsInitAttached = true;
  
    window.__SP_MAPS__ = window.__SP_MAPS__ || [];
  
    (function injectInfoWindowCSS() {
      if (document.getElementById('sp-map-iw-css')) return;
      var css = `
        .gm-style .gm-style-iw-c{
          border-radius: 4px !important;
          box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
          max-width: 320px !important;
          padding: 0 !important;
        }
        .gm-style .gm-style-iw-d{ overflow: visible !important; }
  
        .gm-ui-hover-effect{
          display: block !important;
          position: absolute !important;
          top: 8px !important;
          right: 8px !important;
          width: 28px !important;
          height: 28px !important;
          background: rgba(255,255,255,0.9) !important;
          border-radius: 50% !important;
          box-shadow: 0 2px 6px rgba(0,0,0,0.2) !important;
          cursor: pointer;
        }
        .gm-ui-hover-effect svg{ display: none !important; }
        .gm-ui-hover-effect span{ display:none !important; }
        .gm-ui-hover-effect::before{
          content: '×';
          position: absolute;
          top: 50%;
          left: 50%;
          transform: translate(-50%,-50%);
          font-size: 18px;
          font-weight: 600;
          color: #000;
          line-height: 1;
        }
  
        .sp-map-infowindow{
          display: flex;
          flex-direction: column;
          align-items: stretch;
          font-size: 15px;
          line-height: 1.5;
        }
  
        .sp-map-infowindow .thumb{
          width: 100%;
          height: 200px;
          object-fit: cover;
          border-radius: 4px 4px 0 0;
          background:#f2f2f2;
          margin: 0 0 12px 0;
          display: block;
        }
  
        .sp-map-infowindow .meta{
          padding: 0 16px 16px;
        }
  
        .sp-map-infowindow .title{
          font-weight: 600;
          margin: 0 0 8px 0;
          font-size: 17px;
        }
  
        .sp-map-infowindow .content{
          font-size: 14px;
          color: #444;
          margin: 0 0 8px 0;
        }
  
        .sp-map-infowindow a.readmore{
          font-size: 14px;
          text-decoration: none;
          border-bottom: 1px solid currentColor;
          color: #92ACA0;
          font-weight: 600;
        }
        .sp-map-infowindow a.readmore:hover{ opacity: .85; }
      `.trim();
  
      var style = document.createElement('style');
      style.id = 'sp-map-iw-css';
      style.textContent = css;
      document.head.appendChild(style);
    })();
  
    function escapeHtml(str) {
      return String(str || '').replace(/[&<>"'`=\/]/g, function (s) {
        return ({
          '&':'&amp;',
          '<':'&lt;',
          '>':'&gt;',
          '"':'&quot;',
          "'":'&#39;',
          '/':'&#x2F;',
          '`':'&#x60;',
          '=':'&#x3D;'
        })[s];
      });
    }
  
    function makePinIcon(color) {
      const path = "M12 2C7.58 2 4 5.58 4 10c0 5.25 6.59 11.39 7.02 11.79a1 1 0 0 0 1.36 0C13.81 21 20 15.25 20 10c0-4.42-3.58-8-8-8zm0 11.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z";
      return {
        path: path,
        fillColor: color,
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeOpacity: 1,
        strokeWeight: 1,
        scale: 1.5,
        anchor: new google.maps.Point(12, 22)
      };
    }
  
    function meterOffsetsToLatLng(latDeg, dxMeters, dyMeters) {
      var latRad = latDeg * Math.PI / 180;
      var dLat = dyMeters / 111111;
      var dLng = dxMeters / (111111 * Math.cos(latRad) || 1);
      return { dLat: dLat, dLng: dLng };
    }
  
    function fanOutPositions(baseLat, baseLng, count) {
      if (count === 1) return [{ lat: baseLat, lng: baseLng }];
      var radiusMeters = Math.min(25, 8 + (count - 2) * 2.5);
      var positions = [];
      for (var i = 0; i < count; i++) {
        var angle = (2 * Math.PI * i) / count;
        var dx = Math.cos(angle) * radiusMeters;
        var dy = Math.sin(angle) * radiusMeters;
        var d  = meterOffsetsToLatLng(baseLat, dx, dy);
        positions.push({ lat: baseLat + d.dLat, lng: baseLng + d.dLng });
      }
      return positions;
    }
  
    function buildInfoHtml(item, cfg) {
      cfg = cfg || {};
      var html = '<div class="sp-map-infowindow">';
  
      if (cfg.showImage && item.thumb) {
        html += '<img class="thumb" src="' + encodeURI(item.thumb) + '" alt="">';
      }
  
      html += '<div class="meta">';
  
      if (cfg.showHeading && item.title) {
        html += '<h4 class="title">' + escapeHtml(item.title) + '</h4>';
      }
  
      if (cfg.showContent && item.content) {
        html += '<div class="content">' + escapeHtml(item.content) + '</div>';
      }
  
      if (cfg.showReadMore && item.url) {
        html += '<a class="readmore" href="' + encodeURI(item.url) + '">Read more →</a>';
      }
  
      html += '</div></div>';
  
      return html;
    }
  
    function initOneMap(entry) {
      var el = document.getElementById(entry.mapId);
      if (!el) return;
  
      var cfg = entry.config || {};
      var pinColor = cfg.pinColor || '#92ACA0';
  
      var map = new google.maps.Map(el, {
        center: { lat: -25.2744, lng: 133.7751 }, 
        zoom: 4,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
        gestureHandling: 'greedy',
        scrollwheel: true
      });
  
      var info   = new google.maps.InfoWindow();
      var bounds = new google.maps.LatLngBounds();
  
      var groups = {};
      (entry.locations || []).forEach(function (item) {
        if (!item || typeof item.lat !== 'number' || typeof item.lng !== 'number') return;
        var key = item.lat.toFixed(6) + ',' + item.lng.toFixed(6);
        (groups[key] = groups[key] || { lat: item.lat, lng: item.lng, items: [] }).items.push(item);
      });
  
      Object.keys(groups).forEach(function (key) {
        var g         = groups[key];
        var positions = fanOutPositions(g.lat, g.lng, g.items.length);
  
        g.items.forEach(function (item, idx) {
          var pos = positions[idx];
          var marker = new google.maps.Marker({
            position: pos,
            map: map,
            title: item.title || '',
            icon: makePinIcon(pinColor)
          });
  
          bounds.extend(pos);
  
          var html = buildInfoHtml(item, cfg);
  
          marker.addListener('click', function () {
            info.setContent(html);
            info.open(map, marker);
  
            google.maps.event.addListenerOnce(info, 'domready', function () {
              var ch = document.querySelector('.gm-style-iw-ch');
              if (ch && ch.parentNode) ch.parentNode.removeChild(ch);
            });
          });
        });
      });
  
      if (!bounds.isEmpty()) {
        map.fitBounds(bounds);
        google.maps.event.addListenerOnce(map, 'bounds_changed', function () {
          if (map.getZoom() > 16) map.setZoom(16);
        });
      }
    }
  
    window.__initSpMaps = function () {
      try {
        (window.__SP_MAPS__ || []).forEach(initOneMap);
      } catch (e) {
        console.error('Simple Maps init error:', e);
      }
    };
  })();
  