
(function(){
  function ready(fn){
    if(document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function(){
    var overlay = document.createElement('div');
    overlay.className = 'bce26-loading-overlay';
    overlay.innerHTML = ''
      + '<div class="bce26-loading-box">'
      + '  <div class="bce26-loading-title">💾 SALVANDO PALPITES<span class="bce26-loading-dots"></span></div>'
      + '  <div class="bce26-loading-subtitle">NÃO DESLIGUE SEU DEVICE</div>'
      + '  <div class="bce26-loading-progress"><span></span></div>'
      + '  <div class="bce26-loading-percent">0%</div>'
      + '</div>';
    document.body.appendChild(overlay);

    var audioCtx = null;
    var bce26LastBeep = 0;

    function beep(freq, duration, type){
      var now = Date.now();
      if(now - bce26LastBeep < 90) return;
      bce26LastBeep = now;

      try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        var osc = audioCtx.createOscillator();
        var gain = audioCtx.createGain();
        osc.type = type || 'square';
        osc.frequency.value = freq;
        gain.gain.value = 0.025;
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        setTimeout(function(){ osc.stop(); }, duration);
      } catch(e) {}
    }

    function startFakeProgress(form){
      var bar = overlay.querySelector('.bce26-loading-progress span');
      var percent = overlay.querySelector('.bce26-loading-percent');
      var progress = 0;

      overlay.classList.add('is-active');
      if(bar) bar.style.width = '0%';
      if(percent) percent.textContent = '0%';

      if (navigator.vibrate) navigator.vibrate(40);

      var interval = setInterval(function(){
        progress += Math.floor(Math.random() * 12) + 5;
        if(progress > 98) progress = 98;

        if(bar) bar.style.width = progress + '%';
        if(percent) percent.textContent = progress + '%';
      }, 260);

      setTimeout(function(){
        clearInterval(interval);
        if(bar) bar.style.width = '100%';
        if(percent) percent.textContent = '100%';

        setTimeout(function(){
          form.submit();
        }, 180);
      }, 4000);
    }

    document.querySelectorAll('.bce26-wrap form').forEach(function(form){
      var isSubmitting = false;

      form.addEventListener('submit', function(e){
        if(isSubmitting) return;

        e.preventDefault();
        isSubmitting = true;

        beep(780, 90, 'square');
        startFakeProgress(form);
      });
    });

    // Auto-avança entre campos de placar no mobile e desktop
    document.querySelectorAll('.bce26-score').forEach(function(input, index, arr){
      input.setAttribute('inputmode', 'numeric');
      input.setAttribute('pattern', '[0-9]*');

      input.addEventListener('input', function(){
        if (this.value.length >= 1 && parseInt(this.value, 10) <= 9) {
          var next = arr[index + 1];
          if (next && !next.disabled) next.focus();
        }
      });
    });

    // Filtro mobile: todos / apenas abertos
    document.querySelectorAll('.bce26-filter-btn').forEach(function(btn){
      btn.addEventListener('click', function(){
        var filter = btn.getAttribute('data-filter');

        document.querySelectorAll('.bce26-filter-btn').forEach(function(b){
          b.classList.remove('is-active');
        });
        btn.classList.add('is-active');

        document.querySelectorAll('.bce26-table tbody tr').forEach(function(row){
          if (filter === 'open' && row.getAttribute('data-open') !== '1') {
            row.classList.add('bce26-hidden-by-filter');
          } else {
            row.classList.remove('bce26-hidden-by-filter');
          }
        });

        beep(620, 50, 'square');
      });
    });

    document.querySelectorAll('.bce26-button, .bce26-table tbody tr, .bce26-score, .bce26-filter-btn').forEach(function(el){
      el.addEventListener('mouseenter', function(){ beep(420, 35, 'square'); }, {passive:true});
    });

    document.querySelectorAll('.bce26-button, .bce26-filter-btn').forEach(function(btn){
      btn.addEventListener('click', function(){ beep(780, 70, 'square'); }, {passive:true});
    });
  });
})();
