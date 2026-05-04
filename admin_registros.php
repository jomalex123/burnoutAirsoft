<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

$adminUser = null;
$setupError = '';
$error = '';
$registrations = [];

try {
    $adminUser = burnout_current_admin();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $setupError = 'No se ha podido validar la sesion de administracion.';
}

if (!$setupError && !$adminUser) {
    header('Location: admin.php');
    exit;
}

function admin_registros_event_time_to_label(string $timeSlot): string
{
    return [
        'M' => 'Mañana',
        'T' => 'Tarde',
        'N' => 'Noche',
    ][$timeSlot] ?? $timeSlot;
}

function admin_registros_format_date(string $value): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return $value;
    }

    return $date->format('d/m/Y');
}

function admin_registros_read_all(): array
{
    $pdo = burnout_pdo();
    $statement = $pdo->query(
        'SELECT
            r.id,
            r.email,
            r.phone,
            r.team_name,
            r.created_at,
            e.title AS event_title,
            e.event_date,
            e.time_slot,
            COUNT(ra.id) AS attendee_count
         FROM registrations r
         INNER JOIN events e ON e.id = r.event_id
         LEFT JOIN registration_attendees ra ON ra.registration_id = r.id
         GROUP BY r.id, r.email, r.phone, r.team_name, r.created_at, e.title, e.event_date, e.time_slot
         ORDER BY r.created_at DESC, r.id DESC'
    );
    $registrations = $statement->fetchAll();
    $attendeesByRegistration = admin_registros_read_attendees($pdo, array_column($registrations, 'id'));

    return array_map(static function (array $registration) use ($attendeesByRegistration): array {
        $registrationId = (int) $registration['id'];

        return [
            'id' => $registrationId,
            'created_at' => (string) $registration['created_at'],
            'event' => (string) $registration['event_title'],
            'event_date' => (string) $registration['event_date'],
            'date' => admin_registros_format_date((string) $registration['event_date']),
            'turn' => admin_registros_event_time_to_label((string) $registration['time_slot']),
            'time_slot' => (string) $registration['time_slot'],
            'email' => (string) $registration['email'],
            'phone' => (string) $registration['phone'],
            'team' => (string) ($registration['team_name'] ?? ''),
            'attendee_count' => (int) $registration['attendee_count'],
            'attendees' => $attendeesByRegistration[$registrationId] ?? [],
        ];
    }, $registrations);
}

function admin_registros_read_attendees(PDO $pdo, array $registrationIds): array
{
    $ids = array_values(array_unique(array_map('intval', $registrationIds)));

    if (!$ids) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare(
        'SELECT registration_id, full_name, document
         FROM registration_attendees
         WHERE registration_id IN (' . $placeholders . ')
         ORDER BY registration_id ASC, id ASC'
    );
    $statement->execute($ids);
    $attendees = [];

    foreach ($statement->fetchAll() as $attendee) {
        $registrationId = (int) $attendee['registration_id'];
        $attendees[$registrationId][] = [
            'name' => (string) $attendee['full_name'],
            'document' => (string) $attendee['document'],
        ];
    }

    return $attendees;
}

function admin_registros_attendee_documents(array $attendees): string
{
    return implode(' ', array_map(static function (array $attendee): string {
        return $attendee['document'];
    }, $attendees));
}

try {
    if (!$setupError) {
        $registrations = admin_registros_read_all();
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'No se han podido cargar los registros.';
}

$csrfToken = burnout_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Gestion Registros - Burnout Airsoft</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="resources/logoBurnout-3.png" />
    <link rel="stylesheet" href="assets/css/plugins.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
  </head>
  <body>
    <div class="ms-main-container">
      <div class="ms-preloader"></div>
      <header class="ms-header">
        <nav class="ms-nav">
          <div class="ms-logo">
            <a class="logonav" href="./" data-type="page-transition">
              <div class="logo-dark"><img src="resources/logoBurnout-2.png" alt="logo image"></div>
              <div class="logo-light current"><img src="resources/logoBurnout-2.png" alt="logo image"></div>
            </a>
          </div>
          <button class="hamburger" type="button" data-toggle="navigation">
          <span class="hamburger-box">
            <span class="hamburger-label">menu</span>
            <span class="hamburger-inner"></span>
          </span>
          </button>
          <div class="height-full-viewport">
            <ul class="ms-navbar">
              <li class="nav-item">
                <a href="./" data-type="page-transition">
                  <span class="ms-btn">Inicio</span>
                  <span class="nav-item__label">Vuelve a la pagina principal</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="galeria.html" data-type="page-transition">
                  <span class="ms-btn">Galeria</span>
                  <span class="nav-item__label">Ver nuestros momentos</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="burnout.html" data-type="page-transition">
                  <span class="ms-btn">Nosotros</span>
                  <span class="nav-item__label">Conoce al equipo</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="partidas.html" data-type="page-transition">
                  <span class="ms-btn">Partidas</span>
                  <span class="nav-item__label">Calendario de partidas</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="admin.php" data-type="page-transition">
                  <span class="ms-btn">Admin</span>
                  <span class="nav-item__label">Panel de administracion</span>
                </a>
              </li>
            </ul>
          </div>
        </nav>
      </header>
      <main class="ms-container admin-page">
        <div class="ms-section__block">
          <div class="admin-header">
            <div class="admin-header__title">
              <span class="admin-kicker">Burnout Airsoft</span>
              <h1>Gestion Registros</h1>
            </div>
            <?php if ($adminUser): ?>
              <div class="admin-header-actions">
                <form class="admin-logout" method="post" action="admin.php">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="logout">
                  <button type="submit">Cerrar sesion</button>
                </form>
                <a class="admin-back-link" href="admin.php">Volver al panel</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="ms-section__block">
          <?php if ($setupError): ?>
            <div class="admin-login-error" role="alert"><?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8') ?></div>
          <?php elseif ($error): ?>
            <div class="admin-message admin-message--error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
          <?php else: ?>
            <section class="admin-table-wrap admin-table-wrap--full">
              <div class="admin-gallery-toolbar">
                <h2>Todos los registros</h2>
                <?php if ($registrations): ?>
                  <div class="admin-toolbar-actions">
                    <div class="admin-export-menu">
                      <button class="admin-icon-button" type="button" id="registrationExportToggle" aria-label="Exportar asistentes" aria-expanded="false" aria-controls="registrationExportOptions">
                        <svg aria-hidden="true" viewBox="0 0 24 24">
                          <path d="M12 3v12"></path>
                          <path d="m7 10 5 5 5-5"></path>
                          <path d="M5 21h14"></path>
                        </svg>
                      </button>
                      <div class="admin-export-menu__options" id="registrationExportOptions" hidden>
                        <button type="button" id="exportRegistrationsCsv">Exportar CSV</button>
                        <button type="button" id="exportRegistrationsPdf">Exportar PDF</button>
                      </div>
                    </div>
                    <button class="admin-filter-button" type="button" data-registros-modal-open="registrationFiltersModal">Filtrar</button>
                  </div>
                <?php endif; ?>
              </div>
              <?php if (!$registrations): ?>
                <div class="admin-empty">No hay registros guardados.</div>
              <?php else: ?>
                <div class="admin-table-controls">
                  <span class="admin-table-count" id="registrationCount"><?= count($registrations) ?> registros</span>
                </div>
                <div class="admin-table-scroll">
                  <table class="admin-table admin-table--wide" id="registrationsTable">
                    <thead>
                      <tr>
                        <th>Evento</th>
                        <th><button class="admin-sort-button" type="button" data-sort-key="date" aria-label="Ordenar por fecha">Fecha</button></th>
                        <th>Turno</th>
                        <th>Email</th>
                        <th>Telefono</th>
                        <th>Equipo</th>
                        <th>Asistentes</th>
                        <th><button class="admin-sort-button" type="button" data-sort-key="created" aria-label="Ordenar por fecha de registro">Registro</button></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($registrations as $registration): ?>
                        <tr
                          data-event="<?= htmlspecialchars(strtolower($registration['event']), ENT_QUOTES, 'UTF-8') ?>"
                          data-date="<?= htmlspecialchars($registration['event_date'], ENT_QUOTES, 'UTF-8') ?>"
                          data-date-label="<?= htmlspecialchars(strtolower($registration['date']), ENT_QUOTES, 'UTF-8') ?>"
                          data-turn="<?= htmlspecialchars($registration['time_slot'], ENT_QUOTES, 'UTF-8') ?>"
                          data-email="<?= htmlspecialchars(strtolower($registration['email']), ENT_QUOTES, 'UTF-8') ?>"
                          data-phone="<?= htmlspecialchars(strtolower($registration['phone']), ENT_QUOTES, 'UTF-8') ?>"
                          data-team="<?= htmlspecialchars(strtolower($registration['team']), ENT_QUOTES, 'UTF-8') ?>"
                          data-document="<?= htmlspecialchars(strtolower(admin_registros_attendee_documents($registration['attendees'])), ENT_QUOTES, 'UTF-8') ?>"
                          data-created="<?= htmlspecialchars($registration['created_at'], ENT_QUOTES, 'UTF-8') ?>"
                          data-registration-id="<?= (int) $registration['id'] ?>"
                        >
                          <td><?= htmlspecialchars($registration['event'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($registration['date'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($registration['turn'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($registration['email'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($registration['phone'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($registration['team'] !== '' ? $registration['team'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                          <td>
                            <button class="admin-table-action" type="button" data-registros-modal-open="registrationAttendees<?= (int) $registration['id'] ?>">
                              VER
                            </button>
                          </td>
                          <td><?= htmlspecialchars($registration['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php foreach ($registrations as $registration): ?>
                  <div class="admin-gallery-modal" id="registrationAttendees<?= (int) $registration['id'] ?>" aria-hidden="true">
                    <div class="admin-gallery-modal__overlay" data-registros-modal-close></div>
                    <div class="admin-gallery-modal__dialog admin-registrations-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="registrationAttendeesTitle<?= (int) $registration['id'] ?>">
                      <div class="admin-gallery-modal__header">
                        <h2 id="registrationAttendeesTitle<?= (int) $registration['id'] ?>">Asistentes</h2>
                        <button type="button" data-registros-modal-close aria-label="Cerrar ventana">x</button>
                      </div>
                      <div class="admin-registrations-modal__meta">
                        <strong><?= htmlspecialchars($registration['event'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($registration['date'] . ' - ' . $registration['turn'], ENT_QUOTES, 'UTF-8') ?></span>
                      </div>
                      <?php if (!$registration['attendees']): ?>
                        <div class="admin-empty">No hay asistentes guardados para este registro.</div>
                      <?php else: ?>
                        <div class="admin-table-scroll">
                          <table class="admin-table admin-registrations-attendees-table">
                            <thead>
                              <tr>
                                <th>N. asistente</th>
                                <th>Nombre completo</th>
                                <th>Documento</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($registration['attendees'] as $index => $attendee): ?>
                                <tr>
                                  <td><?= $index + 1 ?></td>
                                  <td><?= htmlspecialchars($attendee['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                  <td><?= htmlspecialchars($attendee['document'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
                <div class="admin-gallery-modal" id="registrationFiltersModal" aria-hidden="true">
                  <div class="admin-gallery-modal__overlay" data-registros-modal-close></div>
                  <div class="admin-gallery-modal__dialog admin-registrations-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="registrationFiltersTitle">
                    <div class="admin-gallery-modal__header">
                      <h2 id="registrationFiltersTitle">Filtrar registros</h2>
                      <button type="button" data-registros-modal-close aria-label="Cerrar ventana">x</button>
                    </div>
                    <form class="admin-gallery-form" id="registrationFiltersForm">
                      <div class="admin-login-field">
                        <label for="filterEvent">Evento</label>
                        <input id="filterEvent" name="event" type="text" autocomplete="off">
                      </div>
                      <div class="admin-login-field">
                        <label for="filterDate">Fecha</label>
                        <input id="filterDate" name="date" type="date">
                      </div>
                      <div class="admin-login-field admin-login-field--compact">
                        <label for="filterTurn">Turno</label>
                        <select id="filterTurn" name="turn">
                          <option value="">Todos</option>
                          <option value="M">Ma&ntilde;ana</option>
                          <option value="T">Tarde</option>
                          <option value="N">Noche</option>
                        </select>
                      </div>
                      <div class="admin-login-field">
                        <label for="filterEmail">Email</label>
                        <input id="filterEmail" name="email" type="text" autocomplete="off">
                      </div>
                      <div class="admin-login-field">
                        <label for="filterPhone">Telefono</label>
                        <input id="filterPhone" name="phone" type="text" autocomplete="off">
                      </div>
                      <div class="admin-login-field">
                        <label for="filterTeam">Equipo</label>
                        <input id="filterTeam" name="team" type="text" autocomplete="off">
                      </div>
                      <div class="admin-login-field">
                        <label for="filterDocument">Numero Documento</label>
                        <input id="filterDocument" name="document" type="text" autocomplete="off">
                      </div>
                      <div class="admin-gallery-modal__actions admin-gallery-modal__actions--split">
                        <button class="admin-danger-submit" type="reset">Limpiar filtros</button>
                        <button class="admin-login-submit" type="submit">Aplicar filtros</button>
                      </div>
                    </form>
                  </div>
                </div>
              <?php endif; ?>
            </section>
          <?php endif; ?>
        </div>
      </main>
      <footer>
        <div class="ms-footer">
          <div class="copyright">Copyright &copy; 2025. Design by Alex Serret</div>
          <ul class="socials">
            <li><a href="#" class="socicon-instagram"></a></li>
            <li><a href="#" class="socicon-youtube"></a></li>
          </ul>
        </div>
      </footer>
    </div>
    <script type="text/javascript" src='assets/js/jquery-3.2.1.min.js'></script>
    <script type="text/javascript" src='assets/js/plugins.min.js'></script>
    <script type="text/javascript" src="assets/js/main.js"></script>
    <script>
      window.BurnoutAdminRegistrationData = <?= json_encode($registrations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '[]' ?>;
    </script>
    <script>
      (function () {
        var table = document.getElementById('registrationsTable');
        var filtersForm = document.getElementById('registrationFiltersForm');
        var count = document.getElementById('registrationCount');
        var exportToggle = document.getElementById('registrationExportToggle');
        var exportOptions = document.getElementById('registrationExportOptions');
        var exportCsvButton = document.getElementById('exportRegistrationsCsv');
        var exportPdfButton = document.getElementById('exportRegistrationsPdf');
        var sortButtons = Array.prototype.slice.call(document.querySelectorAll('[data-sort-key]'));
        var rows = table ? Array.prototype.slice.call(table.querySelectorAll('tbody tr')) : [];
        var registrationsData = Array.isArray(window.BurnoutAdminRegistrationData) ? window.BurnoutAdminRegistrationData : [];
        var activeFilters = {
          event: '',
          date: '',
          turn: '',
          email: '',
          phone: '',
          team: '',
          document: ''
        };
        var sortState = {
          key: '',
          direction: 'asc'
        };

        function normalize(value) {
          return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function normalizeDocument(value) {
          return normalize(value).replace(/[\s.-]/g, '');
        }

        function rowMatches(row) {
          return Object.keys(activeFilters).every(function (key) {
            var filterValue = activeFilters[key];

            if (!filterValue) {
              return true;
            }

            if (key === 'date') {
              return row.dataset.date === filterValue;
            }

            if (key === 'turn') {
              return row.dataset.turn === filterValue;
            }

            if (key === 'document') {
              return normalizeDocument(row.dataset.document).indexOf(normalizeDocument(filterValue)) !== -1;
            }

            return normalize(row.dataset[key]).indexOf(filterValue) !== -1;
          });
        }

        function readFilters() {
          if (!filtersForm) {
            return;
          }

          activeFilters = {
            event: normalize(filtersForm.elements.event.value),
            date: filtersForm.elements.date.value,
            turn: filtersForm.elements.turn.value,
            email: normalize(filtersForm.elements.email.value),
            phone: normalize(filtersForm.elements.phone.value),
            team: normalize(filtersForm.elements.team.value),
            document: normalizeDocument(filtersForm.elements.document.value)
          };
        }

        function compareRows(first, second) {
          if (!sortState.key) {
            return 0;
          }

          var firstValue = sortState.key === 'created' ? first.dataset.created : first.dataset.date;
          var secondValue = sortState.key === 'created' ? second.dataset.created : second.dataset.date;
          var result = firstValue.localeCompare(secondValue);

          return sortState.direction === 'asc' ? result : -result;
        }

        function updateSortButtons() {
          sortButtons.forEach(function (button) {
            var isActive = button.getAttribute('data-sort-key') === sortState.key;

            button.classList.toggle('is-asc', isActive && sortState.direction === 'asc');
            button.classList.toggle('is-desc', isActive && sortState.direction === 'desc');
          });
        }

        function renderRows() {
          if (!table) {
            return;
          }

          var tbody = table.querySelector('tbody');
          var visibleCount = 0;

          rows.slice().sort(compareRows).forEach(function (row) {
            var isVisible = rowMatches(row);

            row.style.display = isVisible ? '' : 'none';
            row.setAttribute('aria-hidden', isVisible ? 'false' : 'true');

            if (isVisible) {
              visibleCount++;
            }

            tbody.appendChild(row);
          });

          if (count) {
            count.textContent = visibleCount + (visibleCount === 1 ? ' registro' : ' registros');
          }
        }

        if (filtersForm) {
          filtersForm.addEventListener('submit', function (event) {
            event.preventDefault();
            readFilters();
            renderRows();
            closeModals();
          });

          filtersForm.addEventListener('reset', function () {
            setTimeout(function () {
              readFilters();
              renderRows();
            }, 0);
          });
        }

        sortButtons.forEach(function (button) {
          button.addEventListener('click', function () {
            var key = button.getAttribute('data-sort-key');

            if (sortState.key === key) {
              sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
            } else {
              sortState.key = key;
              sortState.direction = 'asc';
            }

            updateSortButtons();
            renderRows();
          });
        });

        function visibleRegistrationIds() {
          return rows.filter(function (row) {
            return row.style.display !== 'none';
          }).map(function (row) {
            return String(row.dataset.registrationId);
          });
        }

        function exportRows() {
          var ids = visibleRegistrationIds();

          return registrationsData.filter(function (registration) {
            return ids.indexOf(String(registration.id)) !== -1;
          }).reduce(function (items, registration) {
            var attendees = Array.isArray(registration.attendees) ? registration.attendees : [];

            attendees.forEach(function (attendee, index) {
              items.push({
                event: registration.event,
                date: registration.date,
                turn: registration.turn,
                email: registration.email,
                phone: registration.phone,
                team: registration.team || '-',
                attendeeNumber: index + 1,
                name: attendee.name,
                document: attendee.document,
                createdAt: registration.created_at
              });
            });

            return items;
          }, []);
        }

        function csvEscape(value) {
          return '"' + String(value || '').replace(/"/g, '""') + '"';
        }

        function downloadCsv() {
          var rowsToExport = exportRows();

          if (!rowsToExport.length) {
            alert('No hay asistentes para exportar.');
            return;
          }

          var headers = ['Evento', 'Fecha', 'Turno', 'Email', 'Telefono', 'Equipo', 'N. asistente', 'Nombre completo', 'Documento', 'Fecha registro'];
          var lines = [headers.map(csvEscape).join(';')];

          rowsToExport.forEach(function (row) {
            lines.push([
              row.event,
              row.date,
              row.turn,
              row.email,
              row.phone,
              row.team,
              row.attendeeNumber,
              row.name,
              row.document,
              row.createdAt
            ].map(csvEscape).join(';'));
          });

          var blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
          var link = document.createElement('a');

          link.href = URL.createObjectURL(blob);
          link.download = 'asistentes_registros.csv';
          document.body.appendChild(link);
          link.click();
          URL.revokeObjectURL(link.href);
          link.remove();
        }

        function htmlEscape(value) {
          return String(value || '').replace(/[&<>"']/g, function (character) {
            return {
              '&': '&amp;',
              '<': '&lt;',
              '>': '&gt;',
              '"': '&quot;',
              "'": '&#039;'
            }[character];
          });
        }

        function exportPdf() {
          var rowsToExport = exportRows();

          if (!rowsToExport.length) {
            alert('No hay asistentes para exportar.');
            return;
          }

          var tableRows = rowsToExport.map(function (row) {
            return [
              '<tr>',
                '<td>' + htmlEscape(row.event) + '</td>',
                '<td>' + htmlEscape(row.date) + '</td>',
                '<td>' + htmlEscape(row.turn) + '</td>',
                '<td>' + htmlEscape(row.email) + '</td>',
                '<td>' + htmlEscape(row.phone) + '</td>',
                '<td>' + htmlEscape(row.team) + '</td>',
                '<td>' + htmlEscape(row.attendeeNumber) + '</td>',
                '<td>' + htmlEscape(row.name) + '</td>',
                '<td>' + htmlEscape(row.document) + '</td>',
                '<td>' + htmlEscape(row.createdAt) + '</td>',
              '</tr>'
            ].join('');
          }).join('');
          var printWindow = window.open('', '_blank');

          if (!printWindow) {
            alert('El navegador ha bloqueado la ventana de exportacion.');
            return;
          }

          printWindow.document.write([
            '<!DOCTYPE html>',
            '<html lang="es">',
            '<head>',
              '<meta charset="utf-8">',
              '<title>Asistentes registros</title>',
              '<style>',
                'body{font-family:Arial,sans-serif;color:#151515;margin:24px;}',
                'h1{font-size:22px;margin:0 0 18px;}',
                'table{border-collapse:collapse;width:100%;font-size:11px;}',
                'th,td{border:1px solid #ccc;padding:6px;text-align:left;vertical-align:top;}',
                'th{background:#f1f1f1;text-transform:uppercase;}',
                '@media print{@page{size:landscape;margin:10mm;}body{margin:0;}}',
              '</style>',
            '</head>',
            '<body>',
              '<h1>Asistentes registros</h1>',
              '<table>',
                '<thead><tr><th>Evento</th><th>Fecha</th><th>Turno</th><th>Email</th><th>Telefono</th><th>Equipo</th><th>N. asistente</th><th>Nombre completo</th><th>Documento</th><th>Fecha registro</th></tr></thead>',
                '<tbody>' + tableRows + '</tbody>',
              '</table>',
              '<script>window.onload=function(){window.print();};<\/script>',
            '</body>',
            '</html>'
          ].join(''));
          printWindow.document.close();
        }

        function closeExportMenu() {
          if (!exportOptions || !exportToggle) {
            return;
          }

          exportOptions.hidden = true;
          exportToggle.setAttribute('aria-expanded', 'false');
        }

        if (exportToggle && exportOptions) {
          exportToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            exportOptions.hidden = !exportOptions.hidden;
            exportToggle.setAttribute('aria-expanded', exportOptions.hidden ? 'false' : 'true');
          });

          exportOptions.addEventListener('click', function (event) {
            event.stopPropagation();
          });

          document.addEventListener('click', closeExportMenu);
        }

        if (exportCsvButton) {
          exportCsvButton.addEventListener('click', function () {
            downloadCsv();
            closeExportMenu();
          });
        }

        if (exportPdfButton) {
          exportPdfButton.addEventListener('click', function () {
            exportPdf();
            closeExportMenu();
          });
        }

        function closeModals() {
          document.querySelectorAll('.admin-gallery-modal.is-open').forEach(function (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
          });
          document.body.classList.remove('admin-gallery-modal-open');
        }

        document.querySelectorAll('[data-registros-modal-open]').forEach(function (button) {
          button.addEventListener('click', function () {
            var modal = document.getElementById(button.getAttribute('data-registros-modal-open'));

            if (!modal) {
              return;
            }

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('admin-gallery-modal-open');
          });
        });

        document.querySelectorAll('[data-registros-modal-close]').forEach(function (button) {
          button.addEventListener('click', closeModals);
        });

        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') {
            closeModals();
          }
        });

        renderRows();
      }());
    </script>
  </body>
</html>
