/**
 * Bangla QR — Client-side QR Image Decoder
 *
 * Watches the QR image upload field on the gateway edit form.
 * When an image is selected, decodes it client-side using jsQR
 * and populates the raw_payload textarea automatically.
 *
 * Also guards form submission: blocks save if raw_payload is
 * non-empty but not a valid EMVCo TLV payload (prevents garbage
 * data from silently reaching the server, since there is no
 * server-side format validation on save).
 *
 * Requires: jsQR library (loaded via CDN in the admin edit page).
 *
 * Usage: Include this script on the gateway edit page.
 *        It auto-initializes when the DOM is ready.
 */
(function () {
  'use strict';

  // Large phone-camera photos/screenshots (3000x4000+) can silently
  // fail on mobile browsers (Safari has a hard canvas pixel cap,
  // low-RAM Android Chrome can OOM on getImageData). jsQR also
  // decodes faster and more reliably on modest resolutions anyway,
  // so we always downscale before decoding.
  var MAX_DECODE_DIMENSION = 1200;

  /** Decode a QR image file and return the TLV payload string */
  function decodeQRImage(file, callback) {
    var reader = new FileReader();

    reader.onload = function (e) {
      var img = new Image();

      img.onload = function () {
        try {
          // Scale down to MAX_DECODE_DIMENSION on the longest side.
          // This avoids mobile canvas/memory limits and speeds up jsQR.
          var scale = Math.min(
            1,
            MAX_DECODE_DIMENSION / Math.max(img.naturalWidth, img.naturalHeight)
          );
          var w = Math.max(1, Math.round(img.naturalWidth * scale));
          var h = Math.max(1, Math.round(img.naturalHeight * scale));

          var canvas = document.createElement('canvas');
          canvas.width = w;
          canvas.height = h;
          var ctx = canvas.getContext('2d');

          if (!ctx) {
            callback('ERROR: Canvas not supported on this browser.');
            return;
          }

          ctx.drawImage(img, 0, 0, w, h);

          var imageData = ctx.getImageData(0, 0, w, h);

          if (typeof jsQR === 'undefined') {
            callback('ERROR: QR decode library failed to load. Check your internet connection and try again, or paste the payload manually.');
            return;
          }

          var code = jsQR(imageData.data, w, h, { inversionAttempts: 'attemptBoth' });

          if (code && code.data) {
            callback(null, code.data);
          } else {
            callback('Could not decode QR image. Try a clearer, well-lit, straight-on photo.');
          }
        } catch (err) {
          // Any decode-time exception (canvas limits, OOM, jsQR internal
          // error, etc.) lands here instead of failing silently.
          callback('Decode failed on this device (' + (err && err.message ? err.message : 'unknown error') + '). Try a smaller/clearer image, or a different device.');
        }
      };

      img.onerror = function () {
        callback('Failed to load image. The file may be corrupted or in an unsupported format.');
      };

      img.src = e.target.result;
    };

    reader.onerror = function () {
      callback('Failed to read file.');
    };

    reader.readAsDataURL(file);
  }

  /** Check if payload starts with EMVCo static or dynamic QR header */
  function isValidTlvPayload(payload) {
    return !!payload && (payload.indexOf('000201') === 0 || payload.indexOf('000202') === 0);
  }

  /** Show a status message near the QR upload field */
  function showStatus(message, isError) {
    var existing = document.getElementById('bnqr-decode-status');
    if (existing) existing.remove();

    var el = document.createElement('div');
    el.id = 'bnqr-decode-status';
    el.style.cssText = 'margin-top:6px;padding:6px 10px;border-radius:4px;font-size:13px;' +
      (isError
        ? 'background:#f8d7da;color:#842024;border:1px solid #f5c2c7;'
        : 'background:#d1e7dd;color:#0f5132;border:1px solid #badbcc;');
    el.textContent = message;

    var qrField = document.querySelector('input[name="qr_code"]');
    if (qrField && qrField.parentElement) {
      qrField.parentElement.appendChild(el);
    }

    setTimeout(function () { if (el.parentNode) el.remove(); }, 8000);
  }

  /** Set the raw_payload textarea value */
  function setPayload(payload) {
    var ta = document.querySelector('textarea[name="raw_payload"]');
    if (ta) {
      ta.value = payload;
      ta.dataset.bnqrValid = 'true';
      ta.dispatchEvent(new Event('change', { bubbles: true }));
      ta.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }

  /** Handle QR file selection */
  function onQRFileSelected(e) {
    var file = e.target.files && e.target.files[0];
    if (!file) return;

    if (!file.type.match(/^image\/(png|jpe?g|gif|webp|bmp)$/i)) {
      showStatus('Please select a QR code image file (PNG, JPG, etc.)', true);
      return;
    }

    showStatus('Decoding QR image...', false);

    decodeQRImage(file, function (err, payload) {
      if (err) {
        showStatus(err, true);
        return;
      }

      if (isValidTlvPayload(payload)) {
        setPayload(payload);

        var formatted = payload.match(/.{1,60}/g);
        var msg = 'QR decoded successfully!';
        msg += ' Payload: ' + (formatted ? formatted[0] + '...' : payload.substring(0, 60) + '...');
        showStatus(msg, false);
      } else {
        showStatus('Decoded content is not a valid Bangla QR TLV payload (starts with ' + payload.substring(0, 10) + '...). Not saved to Decoded QR Payload.', true);
      }
    });
  }

  /**
   * Guard form submission: if raw_payload has content but it doesn't
   * look like a valid EMVCo TLV payload, block save and warn. This is
   * the client-side stand-in for server-side validation (see review
   * note: the generic gateway-save handler persists whatever is
   * posted, with no format check).
   */
  function guardSubmit(e) {
    var ta = document.querySelector('textarea[name="raw_payload"]');
    if (!ta) return;

    var val = (ta.value || '').trim();
    if (val === '') return; // empty is fine — server does lazy-decode fallback at checkout

    if (!isValidTlvPayload(val)) {
      e.preventDefault();
      if (e.stopImmediatePropagation) e.stopImmediatePropagation();
      showStatus('Decoded QR Payload does not look like a valid Bangla QR (must start with 000201/000202). Fix or clear this field before saving.', true);
      ta.focus();
    }
  }

  /** Initialize: attach listeners */
  function init() {
    var qrInput = document.querySelector('input[name="qr_code"]');
    if (qrInput) {
      qrInput.addEventListener('change', onQRFileSelected);
    }

    // Bind on `document` (an ancestor of the form), not on the form
    // itself. Ancestor-capture listeners always fire before any
    // listener bound directly on the target element — capture vs.
    // bubble on the SAME element is decided by registration order,
    // and jQuery's own submit handler is already bound by the time
    // this script runs, so binding directly on the form would fire
    // too late to stop the AJAX save from going out.
    document.addEventListener('submit', function (e) {
      if (e.target && e.target.classList && e.target.classList.contains('form-submit')) {
        guardSubmit(e);
      }
    }, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
