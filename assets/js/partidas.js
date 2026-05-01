window.initPartidasPage = function () {
  var events = [
    {
      date: '2026-05-16',
      title: 'Curso Iniciación 4ª Edición',
      time: 'Sabado 16 de mayo - tarde',
      url: 'registro.html'
    }
  ];

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

    if (!grid || !title || !eventList) {
      return;
    }

    previous.replaceWith(previous.cloneNode(true));
    next.replaceWith(next.cloneNode(true));
    previous = document.getElementById('prevMonth');
    next = document.getElementById('nextMonth');

    previous.addEventListener('click', function () {
      currentDate.setMonth(currentDate.getMonth() - 1);
      renderCalendar(grid, title, eventList);
    });

    next.addEventListener('click', function () {
      currentDate.setMonth(currentDate.getMonth() + 1);
      renderCalendar(grid, title, eventList);
    });

    renderCalendar(grid, title, eventList);

  function renderCalendar(grid, title, eventList) {
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

    renderEventList(eventList, year, month);
  }

  function createDayCell(year, month, day) {
    var dateKey = formatDate(year, month, day);
    var event = findEvent(dateKey);
    var cell = document.createElement(event ? 'button' : 'div');

    cell.className = event ? 'partidas-day has-event' : 'partidas-day';
    cell.type = event ? 'button' : '';

    var number = document.createElement('span');
    number.textContent = day;
    cell.appendChild(number);

    if (event) {
      var badge = document.createElement('span');
      badge.className = 'partidas-event-badge';
      badge.textContent = 'Inscripcion abierta';

      var eventTitle = document.createElement('span');
      eventTitle.className = 'partidas-event-title';
      eventTitle.textContent = event.title;

      cell.appendChild(badge);
      cell.appendChild(eventTitle);
      cell.setAttribute('aria-label', day + ' de ' + monthNames[month] + ': ' + event.title + '. Abrir registro');
      cell.addEventListener('click', function () {
        openEvent(event.url);
      });
    }

    return cell;
  }

  function renderEventList(eventList, year, month) {
    var monthEvents = events.filter(function (event) {
      var parts = event.date.split('-');
      return Number(parts[0]) === year && Number(parts[1]) === month + 1;
    });

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

      var title = document.createElement('strong');
      title.textContent = event.title;

      var time = document.createElement('span');
      time.textContent = event.time;

      var action = document.createElement('span');
      action.textContent = 'Abrir inscripcion';

      card.appendChild(title);
      card.appendChild(time);
      card.appendChild(action);
      card.addEventListener('click', function () {
        openEvent(event.url);
      });

      eventList.appendChild(card);
    });
  }

  function findEvent(dateKey) {
    return events.find(function (event) {
      return event.date === dateKey;
    });
  }

  function formatDate(year, month, day) {
    return [
      year,
      String(month + 1).padStart(2, '0'),
      String(day).padStart(2, '0')
    ].join('-');
  }

  function openEvent(url) {
    if (typeof window.BurnoutNavigate === 'function') {
      window.BurnoutNavigate(url);
      return;
    }

    window.location.href = url;
  }
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initPartidasPage);
}
