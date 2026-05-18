(function() {
  'use strict';

  var totalSeconds = 900;
  var remainingSeconds = totalSeconds;
  var activeTeam = 'neutral';
  var scores = {
    red: 0,
    blue: 0
  };
  var timerId = null;

  document.addEventListener('DOMContentLoaded', function() {
    bindControls();
    updateDisplay();
  });

  function bindControls() {
    document.querySelectorAll('[data-team]').forEach(function(button) {
      button.addEventListener('click', function() {
        activeTeam = button.getAttribute('data-team');
        updateDisplay();
      });
    });

    document.querySelectorAll('[data-round]').forEach(function(button) {
      button.addEventListener('click', function() {
        var action = button.getAttribute('data-round');

        if (action === 'start') {
          startRound();
        }

        if (action === 'reset') {
          resetRound();
        }
      });
    });
  }

  function startRound() {
    if (timerId) {
      return;
    }

    timerId = window.setInterval(tick, 1000);
  }

  function resetRound() {
    stopTimer();
    remainingSeconds = totalSeconds;
    activeTeam = 'neutral';
    scores.red = 0;
    scores.blue = 0;
    updateDisplay();
  }

  function tick() {
    remainingSeconds = Math.max(0, remainingSeconds - 1);

    if (activeTeam === 'red' || activeTeam === 'blue') {
      scores[activeTeam] += 1;
    }

    if (remainingSeconds <= 0) {
      stopTimer();
    }

    updateDisplay();
  }

  function updateDisplay() {
    var page = document.querySelector('.dominio-page');
    var timer = document.getElementById('dominioTimer');
    var status = document.getElementById('dominioStatus');
    var redScore = document.getElementById('redScore');
    var blueScore = document.getElementById('blueScore');

    if (!page || !timer || !status || !redScore || !blueScore) {
      return;
    }

    page.classList.remove('is-red', 'is-blue', 'is-neutral');
    page.classList.add('is-' + activeTeam);
    timer.textContent = formatTime(remainingSeconds);
    redScore.textContent = scores.red;
    blueScore.textContent = scores.blue;
    status.textContent = getStatusText();
  }

  function getStatusText() {
    if (activeTeam === 'red') {
      return 'BANDERA ROJA';
    }

    if (activeTeam === 'blue') {
      return 'BANDERA AZUL';
    }

    return 'SIN CONTROL';
  }

  function formatTime(secondsLeft) {
    var minutes = Math.floor(secondsLeft / 60);
    var seconds = secondsLeft % 60;

    return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
  }

  function stopTimer() {
    if (timerId) {
      window.clearInterval(timerId);
      timerId = null;
    }
  }
})();
