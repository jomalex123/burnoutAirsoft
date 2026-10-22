window.initPartidasPage = function () {
  var events = [];
  var loadFailed = false;
  var today = new Date();
  var currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
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
          throw new Error('No se han podido cargar las partidas.');
        }

        return response.json();
      })
      .then(function (data) {
        if (!Array.isArray(data)) {
          throw new Error('Respuesta de partidas no válida.');
        }
        return data;
      })
      .catch(function () {
        loadFailed = true;
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

    renderEventList();
  }

  function createDayCell(year, month, day) {
    var dateKey = formatDate(year, month, day);
    var dayEvents = getEventsByDate(dateKey);
    var cell = document.createElement('div');
    var number = document.createElement('span');

    cell.className = dayEvents.length ? 'partidas-day has-event' : 'partidas-day';

    if (isToday(year, month, day)) {
      cell.className += ' is-today';
    }

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

  function renderEventList() {
    if (loadFailed) {
      eventList.innerHTML = '';
      var error = document.createElement('p');
      error.className = 'partidas-empty';
      error.setAttribute('role', 'alert');
      error.textContent = 'Error al cargar las partidas. Vuelve a intentarlo. Si el problema persiste, contacte con un administrador.';
      eventList.appendChild(error);
      return;
    }
    var now = new Date();
    var todayKey = formatDate(now.getFullYear(), now.getMonth(), now.getDate());
    var upcomingEvents = events.filter(function (event) {
      return event.date >= todayKey;
    }).sort(compareEvents);

    eventList.innerHTML = '';

    if (!upcomingEvents.length) {
      var empty = document.createElement('p');
      empty.className = 'partidas-empty';
      empty.textContent = 'No hay partidas programadas';
      eventList.appendChild(empty);
      return;
    }

    upcomingEvents.forEach(function (event) {
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

    if (value === 'm' || value === 'mañana' || value === 'manana') {
      return 'manana';
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
      manana: 1,
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

  function isToday(year, month, day) {
    var now = new Date();

    return now.getFullYear() === year && now.getMonth() === month && now.getDate() === day;
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
      'event_id=' + encodeURIComponent(event.id || '')
    ];

    return url + separator + params.join('&');
  }
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initPartidasPage);
}
