(function() {
  'use strict';

  var activeTeam = '';
  var counters = {
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
        setActiveTeam(button.getAttribute('data-team') || '');
      });
    });
  }

  function setActiveTeam(team) {
    if (team !== 'red' && team !== 'blue') {
      return;
    }

    activeTeam = team;
    startCounter();
    updateDisplay();
  }

  function startCounter() {
    if (timerId) {
      return;
    }

    timerId = window.setInterval(function() {
      if (activeTeam === 'red' || activeTeam === 'blue') {
        counters[activeTeam] += 1;
      }

      updateDisplay();
    }, 1000);
  }

  function updateDisplay() {
    var page = document.querySelector('.dominio-page');
    var counter = document.getElementById('dominioCounter');

    if (!page || !counter) {
      return;
    }

    page.classList.remove('is-red', 'is-blue');

    if (activeTeam) {
      page.classList.add('is-' + activeTeam);
    }

    counter.textContent = formatTime(activeTeam ? counters[activeTeam] : 0);
  }

  function formatTime(seconds) {
    var minutes = Math.floor(seconds / 60);
    var remainingSeconds = seconds % 60;

    return String(minutes).padStart(2, '0') + ':' + String(remainingSeconds).padStart(2, '0');
  }
})();
