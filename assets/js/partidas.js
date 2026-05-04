window.initPartidasPage = function () {
  var events = [];
  var currentDate = new Date(2026, 4, 1);
  var monthNames = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre'
  ];

  var grid = document.getElementById('calendarGrid');
  var title = document.getElementById('calendarTitle');
  var eventList = document.getElementById('eventList');
  var previous = document.getElementById('prevMonth');
  var next = document.getElementById('nextMonth');

  if (!grid || !title || !eventList || !previous || !next) {
    return;
  }

  previous.replaceWith(previous.cloneNode(true));
  next.replaceWith(next.cloneNode(true));
  previous = document.getElementById('prevMonth');
  next = document.getElementById('nextMonth');

  previous.addEventListener('click', function () {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar();
  });

  next.addEventListener('click', function () {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
  });

  loadEvents().then(function (loadedEvents) {
    events = loadedEvents;
    renderCalendar();
  });

  function loadEvents() {
    return fetch('events.php', { cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) {
          return [];
        }

        return response.json();
      })
      .then(function (data) {
        return Array.isArray(data) ? data : [];
      })
      .catch(function () {
        return [];
      });
  }

  function renderCalendar() {
    var year = currentDate.getFullYear();
    var month = currentDate.getMonth();
    var firstDay = new Date(year, month, 1);
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var leadingEmptyDays = (firstDay.getDay() + 6) % 7;

    title.textContent = monthNames[month] + ' ' + year;
    grid.innerHTML = '';

    for (var emptyIndex = 0; emptyIndex < leadingEmptyDays; emptyIndex++) {
      var emptyCell = document.createElement('div');
      emptyCell.className = 'partidas-day is-empty';
      grid.appendChild(emptyCell);
    }

    for (var day = 1; day <= daysInMonth; day++) {
      grid.appendChild(createDayCell(year, month, day));
    }

    renderEventList(year, month);
  }

  function createDayCell(year, month, day) {
    var dateKey = formatDate(year, month, day);
    var dayEvents = getEventsByDate(dateKey);
    var cell = document.createElement('div');
    var number = document.createElement('span');

    cell.className = dayEvents.length ? 'partidas-day has-event' : 'partidas-day';
    number.className = 'partidas-day-number';
    number.textContent = day;
    cell.appendChild(number);

    if (dayEvents.length) {
      var stack = document.createElement('div');
      stack.className = 'partidas-event-stack';

      dayEvents.forEach(function (event) {
        var eventButton = document.createElement('button');
        eventButton.className = 'partidas-event-slice is-' + normalizeTime(event.time);
        eventButton.type = 'button';
        eventButton.textContent = event.title;
        eventButton.setAttribute('aria-label', day + ' de ' + monthNames[month] + ': ' + event.title + '. Abrir registro');
        eventButton.addEventListener('click', function () {
          openEvent(event);
        });
        stack.appendChild(eventButton);
      });

      cell.appendChild(stack);
    }

    return cell;
  }

  function renderEventList(year, month) {
    var monthEvents = events.filter(function (event) {
      var parts = event.date.split('-');
      return Number(parts[0]) === year && Number(parts[1]) === month + 1;
    }).sort(compareEvents);

    eventList.innerHTML = '';

    if (!monthEvents.length) {
      var empty = document.createElement('p');
      empty.className = 'partidas-empty';
      empty.textContent = 'No hay partidas programadas este mes.';
      eventList.appendChild(empty);
      return;
    }

    monthEvents.forEach(function (event) {
      var card = document.createElement('button');
      card.className = 'partidas-event-card';
      card.type = 'button';

      var eventTitle = document.createElement('strong');
      eventTitle.textContent = event.title;

      var time = document.createElement('span');
      time.textContent = formatEventListDate(event.date) + ' - ' + event.time;

      card.appendChild(eventTitle);
      card.appendChild(time);
      card.addEventListener('click', function () {
        openEvent(event);
      });

      eventList.appendChild(card);
    });
  }

  function getEventsByDate(dateKey) {
    return events.filter(function (event) {
      return event.date === dateKey;
    }).sort(compareEvents);
  }

  function compareEvents(first, second) {
    if (first.date !== second.date) {
      return String(first.date || '').localeCompare(String(second.date || ''));
    }

    return timeOrder(first.time) - timeOrder(second.time);
  }

  function normalizeTime(time) {
    var value = String(time || '').toLowerCase();

    if (value === 'm' || value === 'mañana' || value === 'maã±ana') {
      return 'Mañana';
    }

    if (value === 't') {
      return 'tarde';
    }

    if (value === 'n') {
      return 'noche';
    }

    return value;
  }

  function timeOrder(time) {
    return {
      Mañana: 1,
      tarde: 2,
      noche: 3
    }[normalizeTime(time)] || 99;
  }

  function formatDate(year, month, day) {
    return [
      year,
      String(month + 1).padStart(2, '0'),
      String(day).padStart(2, '0')
    ].join('-');
  }

  function formatEventListDate(value) {
    var parts = String(value || '').split('-');

    if (parts.length !== 3) {
      return value || '';
    }

    var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));

    if (isNaN(date.getTime())) {
      return value;
    }

    return date.toLocaleDateString('es-ES', {
      day: 'numeric',
      month: 'long'
    }).replace(' de ', ' ');
  }

  function openEvent(event) {
    var url = buildRegistrationUrl(event);

    if (typeof window.BurnoutNavigate === 'function') {
      window.BurnoutNavigate(url);
      return;
    }

    window.location.href = url;
  }

  function buildRegistrationUrl(event) {
    var url = event.url || 'registro.php';

    if (url === 'registro.html') {
      url = 'registro.php';
    }
    var separator = url.indexOf('?') === -1 ? '?' : '&';
    var params = [
      'event_id=' + encodeURIComponent(event.id || ''),
      'titulo=' + encodeURIComponent(event.title || ''),
      'fecha=' + encodeURIComponent(event.date || ''),
      'turno=' + encodeURIComponent(event.time || '')
    ];

    return url + separator + params.join('&');
  }
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initPartidasPage);
}
