window.initAdminPartidasPage = function () {
  var events = Array.isArray(window.BurnoutAdminEvents) ? window.BurnoutAdminEvents : [];

  var currentDate = new Date(2026, 4, 1);
  var monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  var grid = document.getElementById('calendarGrid');
  var title = document.getElementById('calendarTitle');
  var previous = document.getElementById('prevMonth');
  var next = document.getElementById('nextMonth');

  if (!grid || !title || !previous || !next) {
    return;
  }

  previous.addEventListener('click', function () {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar();
  });

  next.addEventListener('click', function () {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
  });

  document.querySelectorAll('[data-partidas-modal-open]').forEach(function (button) {
    button.addEventListener('click', function () {
      openModal(button.getAttribute('data-partidas-modal-open') === 'delete' ? 'deleteEventModal' : 'createEventModal');
    });
  });

  document.querySelectorAll('[data-partidas-modal-close]').forEach(function (button) {
    button.addEventListener('click', closeModals);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModals();
    }
  });

  renderCalendar();

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
        eventButton.addEventListener('click', function () {
          openEditModal(event);
        });
        stack.appendChild(eventButton);
      });

      cell.appendChild(stack);
    }

    return cell;
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

    if (value === 'm' || value === 'mañana') {
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
    return [year, String(month + 1).padStart(2, '0'), String(day).padStart(2, '0')].join('-');
  }

  function openModal(id) {
    var modal = document.getElementById(id);

    if (!modal) {
      return;
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('admin-gallery-modal-open');
  }

  function openEditModal(event) {
    var id = document.getElementById('editEventId');
    var date = document.getElementById('editDate');
    var title = document.getElementById('editTitle');
    var time = normalizeTime(event.timeSlot || event.time);

    if (id) {
      id.value = event.id || '';
    }

    if (date) {
      date.value = event.date || '';
    }

    if (title) {
      title.value = event.title || '';
    }

    document.querySelectorAll('#editEventModal input[name="time"]').forEach(function (input) {
      input.checked = normalizeTime(input.value) === time;
    });

    if (!document.querySelector('#editEventModal input[name="time"]:checked')) {
      var defaultTime = document.querySelector('#editEventModal input[name="time"][value="M"]');

      if (defaultTime) {
        defaultTime.checked = true;
      }
    }

    openModal('editEventModal');
  }

  function closeModals() {
    document.querySelectorAll('.admin-gallery-modal.is-open').forEach(function (modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    });
    document.body.classList.remove('admin-gallery-modal-open');
  }
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initAdminPartidasPage);
}
