/**
 * Bangla QR — Client-side QR Image Decoder
 * 
 * Watches the QR image upload field on the gateway edit form.
 * When an image is selected, decodes it client-side using jsQR
 * and populates the raw_payload textarea automatically.
 * 
 * Requires: jsQR library (loaded via CDN in the admin edit page).
 * 
 * Usage: Include this script on the gateway edit page.
 *        It auto-initializes when the DOM is ready.
 */
(function () {
  'use strict';

  /** Decode a QR image file and return the TLV payload string */
  function decodeQRImage(file, callback) {
    var reader = new FileReader();
    reader.onload = function (e) {
      var img = new Image();
      img.onload = function () {
        // Draw image to canvas at natural size
        var canvas = document.createElement('canvas');
        canvas.width  = img.naturalWidth;
        canvas.height = img.naturalHeight;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);

        var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

        // jsQR is loaded from CDN (see edit.php script tag)
        if (typeof jsQR === 'undefined') {
          callback('ERROR: jsQR library not loaded');
          return;
        }

        var code = jsQR(imageData.data, canvas.width, canvas.height, {
          inversionAttempts: 'dontInvert'
        });

        if (code && code.data) {
          callback(null, code.data);
        } else {
          callback('Could not decode QR image. Try a clearer image.');
        }
      };
      img.onerror = function () {
        callback('Failed to load image');
      };
      img.src = e.target.result;
    };
    reader.onerror = function () {
      callback('Failed to read file');
    };
    reader.readAsDataURL(file);
  }

  /** Check if payload starts with EMVCo static or dynamic QR header */
  function isValidTlvPayload(payload) {
    return payload && (payload.indexOf('000201') === 0 || payload.indexOf('000202') === 0);
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

    // Insert after the QR image upload field
    var qrField = document.querySelector('input[name="qr_code"]');
    if (qrField && qrField.parentElement) {
      qrField.parentElement.appendChild(el);
    }

    // Auto-remove after 8 seconds
    setTimeout(function () { if (el.parentNode) el.remove(); }, 8000);
  }

  /** Set the raw_payload textarea value */
  function setPayload(payload) {
    var ta = document.querySelector('textarea[name="raw_payload"]');
    if (ta) {
      ta.value = payload;
      // Trigger change event so any JS listening for changes picks it up
      ta.dispatchEvent(new Event('change', { bubbles: true }));
      ta.dispatchEvent(new Event('input',  { bubbles: true }));
    }
  }

/** Handle QR file selection */
function onQRFileSelected(e) {
  var file = e.target.files && e.target.files[0];
  if (!file) return;

  // Only process image files
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
        
        // Format for display: 60-char lines
        var formatted = payload.match(/.{1,60}/g);
        var msg = 'QR decoded successfully!';
        msg += ' Payload: ' + (formatted ? formatted[0] + '...' : payload.substring(0, 60) + '...');
        showStatus(msg, false);
      } else {
      showStatus('Decoded content is not a valid Bangla QR TLV payload (starts with ' + payload.substring(0, 10) + '...)', true);
    }
  });
}

  /** Initialize: attach listener to QR image input */
  function init() {
    var qrInput = document.querySelector('input[name="qr_code"]');
    if (qrInput) {
      qrInput.addEventListener('change', onQRFileSelected);
    }
  }

  // Run when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
