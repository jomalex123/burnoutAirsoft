(function () {
    'use strict';
    window.initHqPage = function () {
        if (window.initHqPage.cleanup) window.initHqPage.cleanup();
        var root = document.querySelector('[data-hq-console]');
        if (!root || root.dataset.hqReady) return;
        root.dataset.hqReady = 'true';
        var teamView = root.dataset.hqMode === 'team';
        var operationId = teamView ? Number(new URLSearchParams(window.location.search).get('operacion')) || 0 : 0;
        var missionTeam = 'alpha', missionCards = new Map(), scriptSignature = '';
        var channel = 'alpha', section = teamView ? 'canales' : 'mapa';
        var cursor = 0, generation = 0, signature = '', timer, connected = false, connectionFailed = false, operation, operations = [];
        var selectionGeneration = 0;
        var statusLabels = { draft: 'Pendiente', active: 'En curso', completed: 'Completada', failed: 'Fallida', deleted: 'Eliminada' };
        var feedback = root.querySelector('[data-hq-feedback]');
        var signal = root.querySelector('[data-hq-signal]');
        var catalog = [], catalogCategories = [], catalogGeneration = 0, importTarget = null, importOpener = null;
        var importPopup = root.querySelector('[data-hq-import-popup]');
        var filterPopup = root.querySelector('[data-hq-catalog-filter-popup]');
        var filterOpener = null;
        var catalogEditor = root.querySelector('[data-hq-catalog-editor-popup]'), catalogEditorOpener = null, catalogEditorVersion = 0;
        var catalogDelete = root.querySelector('[data-hq-catalog-delete-popup]'), catalogDeleteId = 0, catalogDeleteOpener = null, catalogDeleting = false;
        function missionSource(form, item) {
            form.elements.catalog_id.value = item ? item.id : '';
            form.elements.title.readOnly = Boolean(item);
            form.elements.briefing.readOnly = Boolean(item);
            if (item) {
                form.elements.title.value = item.title;
                form.elements.briefing.value = item.briefing;
            }
            var note = form.querySelector('[data-hq-source-note]');
            if (note) note.textContent = item ? 'Misión importada. Importa otra misión para sustituir el nombre y el guion.' : 'Importa una misión o escribe una nueva. Las misiones manuales se guardan también en el catálogo.';
        }
        var catalogTables = [];
        function normalizeCatalog(value) { return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es'); }
        function classificationLabel(value) { return value || 'Sin clasificar'; }
        function renderCatalogTable(state) {
            var browser = state.element;
            if (browser.dataset.hqCatalogBrowser === 'import') state.size = 5;
            var search = normalizeCatalog(state.filters.search);
            var items = catalog.filter(function (item) {
                return (!search || normalizeCatalog(item.title + ' ' + item.briefing).includes(search)) &&
                    (!state.filters.category || classificationLabel(item.category) === state.filters.category);
            });
            items.sort(function (first, second) {
                var left = state.sort === 'title' ? first.title : classificationLabel(first[state.sort]);
                var right = state.sort === 'title' ? second.title : classificationLabel(second[state.sort]);
                return state.direction * (left.localeCompare(right, 'es', { sensitivity: 'base' }) || Number(first.id) - Number(second.id));
            });
            var pages = Math.max(1, Math.ceil(items.length / state.size));
            state.page = Math.min(Math.max(state.page, 1), pages);
            var body = browser.querySelector('[data-hq-table-body]'); body.replaceChildren();
            items.slice((state.page - 1) * state.size, state.page * state.size).forEach(function (item) {
                var row = node('tr');
                row.classList.toggle('is-selected', Number(state.selected) === Number(item.id));
                row.dataset.hqCatalogItem = item.id;
                row.tabIndex = 0;
                row.setAttribute('aria-selected', String(Number(state.selected) === Number(item.id)));
                row.setAttribute('aria-label', item.title + ', ' + classificationLabel(item.category));
                row.append(node('td', '', item.title), node('td', '', classificationLabel(item.category)));
                if (browser.dataset.hqCatalogBrowser === 'manage') {
                    var cell = node('td', 'hq-catalog-actions-column'), actions = node('div', 'hq-catalog-row-actions');
                    [['edit', 'Editar misión', 'M15 3l6 6M3 21l2-8L17 1l6 6L11 19z'], ['delete', 'Borrar misión', 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7']].forEach(function (action) {
                        var button = node('button'); button.type = 'button'; button.title = action[1]; button.setAttribute('aria-label', action[1] + ': ' + item.title);
                        button.dataset.hqCatalogAction = action[0]; button.dataset.hqCatalogId = item.id;
                        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
                        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path'); path.setAttribute('d', action[2]); svg.append(path); button.append(svg); actions.append(button);
                    });
                    cell.append(actions); row.append(cell);
                }
                body.append(row);
            });
            if (!items.length) { var row = node('tr'), cell = node('td', 'hq-muted', 'No hay misiones con estos filtros.'); cell.colSpan = browser.dataset.hqCatalogBrowser === 'manage' ? 3 : 2; row.append(cell); body.append(row); }
            browser.querySelector('[data-hq-table-count]').textContent = items.length + ' de ' + catalog.length + ' misiones';
            var pageButtons = browser.querySelector('[data-hq-table-pages]'); pageButtons.replaceChildren();
            browser.querySelector('[data-hq-table-pagination]').hidden = pages <= 1;
            for (var page = 1; page <= pages; page++) {
                var button = node('button', page === state.page ? 'is-active' : '', String(page));
                button.type = 'button'; button.dataset.hqTablePage = page;
                button.setAttribute('aria-label', 'Página ' + page);
                if (page === state.page) button.setAttribute('aria-current', 'page');
                pageButtons.append(button);
            }
            browser.querySelectorAll('[data-hq-table-sort]').forEach(function (button) {
                var active = button.dataset.hqTableSort === state.sort;
                button.closest('th').setAttribute('aria-sort', active ? state.direction === 1 ? 'ascending' : 'descending' : 'none');
                button.classList.toggle('is-asc', active && state.direction === 1); button.classList.toggle('is-desc', active && state.direction === -1);
            });
        }
        function syncCatalogTables() {
            catalogTables.forEach(function (state) {
                ['category'].forEach(function (field) {
                    var select = state.filterRoot.querySelector('[data-hq-filter="' + field + '"]'), selected = select.value;
                    var options = Array.from(new Set(catalogCategories.concat(['Sin clasificar']))).sort(function (a, b) { return a.localeCompare(b, 'es'); });
                    if (selected && !options.includes(selected)) options.push(selected);
                    select.replaceChildren(node('option', '', field === 'category' ? 'Todas' : 'Todos')); select.firstElementChild.value = '';
                    options.forEach(function (value) { var option = node('option', '', value); option.value = value; select.append(option); }); select.value = selected;
                });
                renderCatalogTable(state);
            });
            var categorySelect = root.querySelector('[data-hq-form="catalog_save"]').elements.category;
            var selected = categorySelect.value;
            var categories = catalogCategories.slice().sort(function (a, b) { return a.localeCompare(b, 'es'); });
            if (selected && !categories.includes(selected)) categories.push(selected);
            categorySelect.replaceChildren(node('option', '', 'Sin clasificar')); categorySelect.firstElementChild.value = '';
            categories.forEach(function (category) { var option = node('option', '', category); option.value = category; categorySelect.append(option); });
            categorySelect.value = selected;
        }
        async function loadCatalog() {
            var request = ++catalogGeneration, data = await api('catalog');
            if (!root.isConnected || request !== catalogGeneration) return false;
            catalog = data.missions; catalogCategories = data.categories; syncCatalogTables(); return true;
        }
        function closeCatalogFilters() {
            if (!filterPopup) return;
            filterPopup.classList.remove('is-open'); filterPopup.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('burnout-popup-open');
            if (filterOpener && filterOpener.isConnected) filterOpener.focus();
        }
        function trapPopupFocus(event, popup) {
            if (event.key !== 'Tab') return;
            var elements = Array.from(popup.querySelectorAll('button:not(:disabled), input:not([type="hidden"]):not(:disabled), select:not(:disabled), [tabindex="0"]')).filter(function (element) { return element.getClientRects().length; });
            var first = elements[0], last = elements[elements.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
        function closeImport() {
            if (!importPopup) return;
            importPopup.classList.remove('is-open'); importPopup.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('burnout-popup-open'); importTarget = null;
            if (importOpener && importOpener.isConnected) importOpener.focus();
        }
        function selectImportItem(id) {
            var item = catalog.find(function (entry) { return Number(entry.id) === Number(id); });
            importPopup.querySelector('[data-hq-import-select]').value = item ? item.id : '';
            importPopup.querySelector('[data-hq-import-preview]').textContent = item ? item.title + '\n\n' + item.briefing : 'Selecciona una misión para consultar su guion.';
            importPopup.querySelector('[data-hq-import-accept]').disabled = !item;
            catalogTables.find(function (state) { return state.element.dataset.hqCatalogBrowser === 'import'; }).selected = item ? item.id : 0;
        }
        async function openImport(button) {
            importTarget = button.closest('[data-hq-form]') || root.querySelector('[data-hq-form="mission"]'); importOpener = button;
            importPopup.classList.add('is-open'); importPopup.setAttribute('aria-hidden', 'false'); document.body.classList.add('burnout-popup-open');
            selectImportItem(0);
            var browser = importPopup.querySelector('[data-hq-catalog-browser]'); browser.hidden = true;
            importPopup.querySelector('[data-hq-import-feedback]').textContent = 'Cargando misiones...';
            importPopup.querySelector('[data-hq-import-close][type="button"]').focus();
            try {
                if (!await loadCatalog() || !importTarget) return;
                browser.hidden = false; importPopup.querySelector('[data-hq-import-feedback]').textContent = '';
                browser.querySelector('[data-hq-filter="search"]').focus();
            } catch (error) { importPopup.querySelector('[data-hq-import-feedback]').textContent = error.message; }
        }
        function closeCatalogEditor() {
            if (!catalogEditor) return;
            catalogEditorVersion++;
            catalogEditor.classList.remove('is-open'); catalogEditor.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('burnout-popup-open');
            var opener = catalogEditorOpener;
            if (!opener || !opener.isConnected) {
                opener = Array.from(root.querySelectorAll('[data-hq-catalog-browser="manage"] [data-hq-catalog-item]')).find(function (row) { return row.getAttribute('aria-selected') === 'true'; }) || root.querySelector('[data-hq-catalog-new]');
            }
            if (opener) opener.focus();
        }
        function closeCatalogDelete() {
            if (!catalogDelete || catalogDeleting) return;
            catalogDelete.classList.remove('is-open'); catalogDelete.setAttribute('aria-hidden', 'true'); document.body.classList.remove('burnout-popup-open');
            catalogDeleteId = 0;
            var opener = catalogDeleteOpener && catalogDeleteOpener.isConnected ? catalogDeleteOpener : root.querySelector('[data-hq-catalog-new]');
            if (opener) opener.focus();
        }
        function openCatalogDelete(id, opener) {
            var item = catalog.find(function (entry) { return Number(entry.id) === Number(id); });
            if (!item) return;
            catalogDeleteId = Number(item.id); catalogDeleteOpener = opener;
            catalogDelete.querySelector('[data-hq-catalog-delete-name]').textContent = item.title;
            catalogDelete.querySelector('[data-hq-catalog-delete-feedback]').textContent = '';
            catalogDelete.classList.add('is-open'); catalogDelete.setAttribute('aria-hidden', 'false'); document.body.classList.add('burnout-popup-open');
            catalogDelete.querySelector('button[data-hq-catalog-delete-close]').focus();
        }
        function openCatalogEditor(id, opener) {
            catalogEditorVersion++;
            catalogEditorOpener = opener; selectCatalogItem(id);
            catalogEditor.querySelector('#hqCatalogEditorTitle').textContent = id ? 'Editar misión' : 'Nueva misión';
            catalogEditor.querySelector('[data-hq-catalog-editor-feedback]').textContent = '';
            catalogEditor.querySelector('[data-hq-catalog-editor-feedback]').classList.remove('is-error');
            catalogEditor.classList.add('is-open'); catalogEditor.setAttribute('aria-hidden', 'false');
            document.body.classList.add('burnout-popup-open');
            root.querySelector('[data-hq-form="catalog_save"]').elements.title.focus();
        }
        function selectCatalogItem(id) {
            var form = root.querySelector('[data-hq-form="catalog_save"]');
            var item = catalog.find(function (entry) { return Number(entry.id) === Number(id); });
            form.elements.catalog_id.value = item ? item.id : '';
            ['title', 'briefing', 'category'].forEach(function (field) { form.elements[field].value = item ? item[field] || '' : ''; });
            var state = catalogTables.find(function (state) { return state.element.dataset.hqCatalogBrowser === 'manage'; });
            state.selected = item ? item.id : 0; renderCatalogTable(state);
        }
        if (!teamView) {
            root.querySelectorAll('[data-hq-catalog-browser]').forEach(function (element) {
                var state = { element: element, filters: { search: '', category: '' }, sort: 'title', direction: 1, page: 1, size: 5, selected: 0, filterRoot: element.dataset.hqCatalogBrowser === 'manage' ? filterPopup : element };
                catalogTables.push(state);
                function filter() {
                    state.filterRoot.querySelectorAll('[data-hq-filter]').forEach(function (input) { state.filters[input.dataset.hqFilter] = input.value; });
                    state.page = 1; renderCatalogTable(state);
                }
                if (element.dataset.hqCatalogBrowser === 'import') {
                    element.querySelectorAll('[data-hq-filter]').forEach(function (input) {
                        input.addEventListener(input.tagName === 'SELECT' ? 'change' : 'input', filter);
                        input.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); filter(); } });
                    });
                } else {
                    filterPopup.querySelector('[data-hq-catalog-filter-form]').addEventListener('submit', function (event) { event.preventDefault(); filter(); closeCatalogFilters(); });
                    filterPopup.querySelector('[data-hq-catalog-filter-form]').addEventListener('reset', function (event) {
                        event.preventDefault();
                        state.filterRoot.querySelectorAll('[data-hq-filter]').forEach(function (input) { input.value = ''; }); filter();
                    });
                    element.querySelector('[data-hq-table-size]').addEventListener('change', function (event) { state.size = Number(event.target.value); state.page = 1; renderCatalogTable(state); });
                }
                function selectRow(item) {
                    if (element.dataset.hqCatalogBrowser === 'import') { selectImportItem(item.dataset.hqCatalogItem); renderCatalogTable(state); }
                    else selectCatalogItem(item.dataset.hqCatalogItem);
                    var selectedRow = Array.from(element.querySelectorAll('[data-hq-catalog-item]')).find(function (row) { return Number(row.dataset.hqCatalogItem) === Number(state.selected); });
                    if (selectedRow) selectedRow.focus();
                }
                element.addEventListener('click', function (event) {
                    if (event.target.closest('[data-hq-catalog-action]')) return;
                    var sort = event.target.closest('[data-hq-table-sort]'), item = event.target.closest('[data-hq-catalog-item]'), page = event.target.closest('[data-hq-table-page]');
                    if (sort) { state.direction = state.sort === sort.dataset.hqTableSort ? -state.direction : 1; state.sort = sort.dataset.hqTableSort; state.page = 1; renderCatalogTable(state); }
                    if (page) {
                        state.page = Number(page.dataset.hqTablePage); renderCatalogTable(state);
                        var current = element.querySelector('[aria-current="page"]'); if (current) current.focus();
                    }
                    if (item) selectRow(item);
                });
                element.addEventListener('keydown', function (event) {
                    if (event.target.closest('button')) return;
                    var item = event.target.closest('[data-hq-catalog-item]');
                    if (item && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); selectRow(item); }
                });
            });
            catalogDelete.querySelectorAll('[data-hq-catalog-delete-close]').forEach(function (element) { element.addEventListener('click', closeCatalogDelete); });
            catalogDelete.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeCatalogDelete(); }
                trapPopupFocus(event, catalogDelete);
            });
            catalogDelete.querySelector('[data-hq-catalog-delete-confirm]').addEventListener('click', async function (event) {
                if (!catalogDeleteId || catalogDeleting) return;
                var id = catalogDeleteId, button = event.currentTarget; catalogDeleting = true; button.disabled = true;
                try {
                    await api('catalog_remove', { catalog_id: id }, true);
                    await loadCatalog(); selectCatalogItem(0);
                    catalogDeleting = false; closeCatalogDelete();
                    report('Misión desactivada. Los guiones asignados se conservan.');
                } catch (error) {
                    var message = catalogDelete.querySelector('[data-hq-catalog-delete-feedback]'); message.textContent = error.message; message.classList.add('is-error');
                } finally { catalogDeleting = false; button.disabled = false; }
            });
            catalogEditor.querySelectorAll('[data-hq-catalog-editor-close]').forEach(function (element) { element.addEventListener('click', closeCatalogEditor); });
            catalogEditor.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeCatalogEditor(); }
                trapPopupFocus(event, catalogEditor);
            });
            root.querySelector('[data-hq-catalog-filter-open]').addEventListener('click', function (event) {
                filterOpener = event.currentTarget;
                var state = catalogTables.find(function (entry) { return entry.element.dataset.hqCatalogBrowser === 'manage'; });
                filterPopup.querySelectorAll('[data-hq-filter]').forEach(function (input) { input.value = state.filters[input.dataset.hqFilter] || ''; });
                filterPopup.classList.add('is-open'); filterPopup.setAttribute('aria-hidden', 'false'); document.body.classList.add('burnout-popup-open');
                filterPopup.querySelector('[data-hq-filter="search"]').focus();
            });
            filterPopup.querySelectorAll('[data-hq-catalog-filter-close]').forEach(function (element) { element.addEventListener('click', closeCatalogFilters); });
            filterPopup.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeCatalogFilters(); }
                trapPopupFocus(event, filterPopup);
            });
            importPopup.querySelectorAll('[data-hq-import-close]').forEach(function (element) { element.addEventListener('click', closeImport); });
            importPopup.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeImport(); }
                trapPopupFocus(event, importPopup);
            });
            importPopup.querySelector('[data-hq-import-form]').addEventListener('submit', function (event) {
                event.preventDefault();
                var id = importPopup.querySelector('[data-hq-import-select]').value;
                var item = catalog.find(function (entry) { return Number(entry.id) === Number(id); });
                if (!item || !importTarget || !importTarget.isConnected) return;
                missionSource(importTarget, item); closeImport();
            });
        }
        function node(tag, className, text) {
            var element = document.createElement(tag);
            if (className) element.className = className;
            if (text !== undefined) element.textContent = text;
            return element;
        }
        function report(message, error) {
            feedback.textContent = message;
            feedback.classList.toggle('is-error', Boolean(error));
        }
        async function api(action, data, post) {
            var params = new URLSearchParams(Object.assign({ action: action, operation_id: operationId, view: teamView ? 'team' : 'admin' }, data || {}));
            if (post) params.set('csrf_token', root.dataset.hqCsrf);
            var response = await fetch('hq_api.php' + (post ? '' : '?' + params.toString()), {
                method: post ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store',
                headers: post ? { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' } : {},
                body: post ? params.toString() : undefined
            });
            var result;
            try { result = await response.json(); } catch (error) { throw new Error('HQ no responde. Comprueba la conexión y vuelve a intentarlo.'); }
            if (!response.ok) {
                var failure = new Error(result.error || 'No se ha podido completar la transmisión.');
                failure.status = response.status;
                throw failure;
            }
            return result;
        }
        function setConnection(text, active) {
            signal.textContent = text;
            signal.classList.toggle('is-connected', active);
        }
        function resetChannel() {
            generation++;
            cursor = 0;
            signature = '';
            root.querySelector('[data-hq-messages]').replaceChildren(node('p', 'hq-empty', 'Sin transmisiones. Abre el canal con un mensaje.'));
            root.querySelector('[data-hq-access-code]')?.setAttribute('hidden', '');
        }
        function missionButton(label, id, status) {
            var button = node('button', '', label);
            button.type = 'button';
            button.dataset.hqMission = id;
            button.dataset.hqStatus = status;
            return button;
        }
        function renderScript(missions) {
            var list = root.querySelector('[data-hq-script-list]');
            if (!list) return;
            var nextSignature = operationId + ':' + (operation ? operation.status : '') + JSON.stringify(missions);
            if (nextSignature === scriptSignature) return;
            scriptSignature = nextSignature;
            var focused = list.contains(document.activeElement) ? document.activeElement : null;
            var focusMission = focused && focused.dataset.hqScriptMission, focusAction = focused && focused.dataset.hqScriptAction;
            list.replaceChildren();
            missions = missions.filter(function (mission) { return mission.status !== 'deleted'; });
            if (!missions.length) list.append(node('li', 'hq-script-row hq-muted', 'No hay misiones en esta partida.'));
            missions.forEach(function (mission, index) {
                var row = node('li', 'hq-script-row');
                row.append(node('span', '', mission.title));
                var actions = node('div', 'hq-script-actions');
                [['up', 'Subir misión', 'M12 20V4M5 11l7-7 7 7'], ['down', 'Bajar misión', 'M12 4v16M5 13l7 7 7-7'], ['remove', 'Eliminar misión', 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7']].forEach(function (item) {
                    var button = node('button'); button.type = 'button'; button.title = item[1]; button.setAttribute('aria-label', item[1] + ': ' + mission.title);
                    button.dataset.hqScriptMission = mission.id; button.dataset.hqScriptAction = item[0];
                    button.disabled = !operation || operation.status !== 'active' || (item[0] === 'up' && index === 0) || (item[0] === 'down' && index === missions.length - 1);
                    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
                    var path = document.createElementNS('http://www.w3.org/2000/svg', 'path'); path.setAttribute('d', item[2]); svg.append(path); button.append(svg); actions.append(button);
                });
                row.append(actions); list.append(row);
            });
            if (focusMission) {
                var next = Array.from(list.querySelectorAll('button')).find(function (button) { return button.dataset.hqScriptMission === focusMission && button.dataset.hqScriptAction === focusAction && !button.disabled; });
                if (next) next.focus();
            }
        }
        function renderMissions(missions) {
            var nextSignature = operationId + operation.status + missionTeam + JSON.stringify(missions);
            if (root.querySelector('[data-hq-mission-editor]:not([hidden])')) return;
            if (signature === nextSignature) return;
            signature = nextSignature;
            var list = root.querySelector('[data-hq-missions]');
            list.querySelectorAll('details[data-mission-id]').forEach(function (card) { missionCards.set(card.dataset.missionId, { open: card.open, status: card.dataset.status }); });
            missions = missions.filter(function (mission) { return teamView || mission.team === missionTeam; });
            list.replaceChildren();
            if (!missions.length) list.append(node('p', 'hq-empty', 'Sin misiones transmitidas. Canal en espera.'));
            missions.forEach(function (mission) {
                var card = node('details', 'hq-briefing');
                card.dataset.missionId = operationId + ':' + mission.id;
                card.dataset.status = mission.status;
                var previous = missionCards.get(card.dataset.missionId);
                var terminal = ['completed', 'failed', 'deleted'].includes(mission.status);
                card.open = previous && previous.status === mission.status ? previous.open : !terminal;
                card.dataset.team = mission.team;
                var header = node('summary', 'hq-briefing-header');
                header.append(node('span', 'hq-mission-title', mission.title), node('span', 'hq-mission-state', statusLabels[mission.status]));
                var screen = node('div', 'hq-briefing-screen');
                screen.append(node('span', 'hq-eyebrow', 'TRANSMISIÓN ' + String(mission.id).padStart(3, '0')), node('p', 'hq-briefing-text', mission.briefing));
                var footer = node('div', 'hq-briefing-footer');
                footer.append(node('span', '', mission.status === 'draft' ? 'Pendiente de inicio' : mission.acknowledged_at ? '✓ Recepción confirmada · ' + mission.acknowledged_at : 'Pendiente de recepción'));
                var actions = node('div', 'hq-mission-actions');
                if (teamView && mission.status === 'active' && !mission.acknowledged_at) actions.append(missionButton('Recibido, HQ', mission.id, 'ack'));
                if (!teamView) {
                    if (mission.status === 'draft') actions.append(missionButton('Iniciar', mission.id, 'active'));
                    if (mission.status === 'active') {
                        actions.append(missionButton('Completada', mission.id, 'completed'), missionButton('Fallida', mission.id, 'failed'), missionButton('Eliminar', mission.id, 'delete'));
                    }
                    if (mission.status !== 'draft') actions.append(missionButton('Reiniciar', mission.id, 'reset'));
                }
                actions.querySelectorAll('button').forEach(function (button) { button.disabled = operation.status !== 'active'; });
                footer.append(actions);
                card.append(header, screen, footer);
                list.append(card);
            });
            root.querySelector('[data-hq-call]').hidden = !teamView || !missions.some(function (mission) { return mission.status === 'active' && !mission.acknowledged_at; });
        }
        function renderMessages(messages) {
            if (!messages.length) return;
            var log = root.querySelector('[data-hq-messages]');
            var atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 70;
            if (cursor === 0) log.replaceChildren();
            messages.forEach(function (message) {
                if (Number(message.id) <= cursor) return;
                var item = node('article', 'hq-message' + (message.sender === 'hq' ? ' hq-message--hq' : ''));
                var heading = node('div', 'hq-message-heading');
                heading.append(node('strong', '', message.sender.toUpperCase()), node('time', '', message.created_at));
                item.append(heading, node('p', '', message.body));
                log.append(item);
                cursor = Number(message.id);
            });
            while (log.children.length > 200) log.firstElementChild.remove();
            if (atBottom) log.scrollTop = log.scrollHeight;
        }
        function updateHeading() {
            var headings = {
                misiones: ['Briefing de operaciones', 'MISIONES'],
                canales: ['Comunicaciones', 'CANALES'],
                ajustes: ['Control de operación', 'AJUSTES']
            };
            var heading = !teamView && headings[section];
            root.querySelector('[data-hq-operation-heading]').textContent = heading ? 'Centro de Operaciones / ' + heading[0] : 'Centro de operaciones';
            var operationTitle = root.querySelector('[data-hq-operation-title]');
            if (operationTitle) operationTitle.textContent = operation ? eventLabel(operation) : 'Selecciona una partida';
            signal.hidden = Boolean(heading);
        }
        function updateOperation() {
            updateHeading();
            root.querySelector('[data-hq-channel-title]').textContent = channel === 'alpha' ? 'Equipo Alpha' : 'Equipo Bravo';
            root.querySelector('[data-hq-send]').disabled = operation.status !== 'active';
            setConnection(operation.status === 'active' ? '● Canal disponible' : 'Partida cerrada', operation.status === 'active');
            root.querySelectorAll('[data-hq-needs-operation]').forEach(function (element) { element.hidden = false; });
            if (!teamView) {
                root.querySelector('[data-hq-settings-empty]').hidden = true;
                root.querySelector('[data-hq-team-link]').href = 'hq_equipo.php';
            }
        }
        function schedule() {
            window.clearTimeout(timer);
            if (!root.isConnected) { document.removeEventListener('visibilitychange', visibility); return; }
            if (operationId && connected && !document.hidden && (teamView || section === 'misiones' || section === 'canales' || section === 'ajustes')) timer = window.setTimeout(refresh, 5000);
        }
        async function refresh() {
            window.clearTimeout(timer);
            if (!root.isConnected || (!teamView && !operationId)) return;
            var requestGeneration = ++generation;
            try {
                var data = await api('state', { channel: channel, after: cursor });
                if (!root.isConnected || requestGeneration !== generation) return;
                operation = data.operation;
                operationId = Number(operation.id);
                channel = data.channel;
                connected = true;
                if (connectionFailed) report('Conexión restablecida.');
                connectionFailed = false;
                if (teamView) {
                    root.querySelector('[data-hq-login]').hidden = true;
                    root.querySelector('[data-hq-dashboard]').hidden = false;
                    document.querySelector('[data-hq-team-heading]').hidden = false;
                }
                updateOperation();
                renderScript(data.missions);
                renderMissions(data.missions);
                renderMessages(data.messages);
            } catch (error) {
                if (requestGeneration !== generation || !root.isConnected) return;
                setConnection('Conexión interrumpida', false);
                connectionFailed = true;
                report(error.message, true);
                if ((error.status === 401 || error.status === 410) && teamView) {
                    connected = false;
                    root.querySelector('[data-hq-login]').hidden = false;
                    root.querySelector('[data-hq-dashboard]').hidden = true;
                    document.querySelector('[data-hq-team-heading]').hidden = true;
                    resetChannel();
                } else if (error.status === 410 && !teamView) {
                    clearOperation('La partida ya no está disponible. Selecciona otro evento del calendario.');
                    loadOperations().catch(function (failure) { report(failure.message, true); });
                }
            }
            schedule();
        }
        function eventLabel(item) {
            var slot = { M: 'Mañana', T: 'Tarde', N: 'Noche' }[item.time_slot] || '';
            return item.title + ' · ' + item.operation_date + ' · ' + slot;
        }
        function clearOperation(message) {
            document.querySelector('.admin-map-page')?.hqMap?.load(0);
            operationId = 0;
            operation = undefined;
            connected = false;
            window.clearTimeout(timer);
            resetChannel();
            closeImport();
            closeCatalogFilters();
            var missionForm = root.querySelector('[data-hq-form="mission"]');
            if (missionForm) { missionForm.reset(); missionSource(missionForm, null); }
            updateHeading();
            renderScript([]);
            root.querySelector('[data-hq-missions]').replaceChildren(node('p', 'hq-empty', message || 'Selecciona un evento del calendario.'));
            root.querySelectorAll('[data-hq-needs-operation]').forEach(function (element) { element.hidden = true; });
            root.querySelector('[data-hq-settings-empty]').hidden = false;
            root.querySelector('[data-hq-send]').disabled = true;
            setConnection('Sin partida', false);
        }
        async function selectEvent(eventId) {
            var selection = ++selectionGeneration;
            var map = document.querySelector('.admin-map-page')?.hqMap;
            if (map && !await map.flush()) {
                document.querySelectorAll('[data-hq-operation], [data-hq-map-operation]').forEach(function (select) { select.value = operation ? operation.event_id : ''; });
                return;
            }
            if (selection !== selectionGeneration || !root.isConnected) return;
            clearOperation();
            if (!eventId) return;
            try {
                var result = await api('select', { event_id: eventId }, true);
                if (!root.isConnected || selection !== selectionGeneration) return;
                operationId = Number(result.operation_id);
                if (map) await map.load(operationId);
                if (!root.isConnected || selection !== selectionGeneration) return;
                await refresh();
            } catch (error) {
                if (root.isConnected && selection === selectionGeneration) report(error.message, true);
            }
        }
        async function loadOperations(selected, keepSelection) {
            var selection = selectionGeneration;
            var data = await api('list');
            if (!root.isConnected || selection !== selectionGeneration) return;
            operations = data.operations;
            var select = root.querySelector('[data-hq-operation]');
            select.replaceChildren(node('option', '', operations.length ? 'Selecciona un evento del calendario' : 'No hay eventos de hoy o futuros'));
            select.firstElementChild.value = '';
            operations.forEach(function (item) {
                var option = node('option', '', eventLabel(item) + (item.status === 'closed' ? ' · HQ cerrado' : ''));
                option.value = item.event_id;
                select.append(option);
            });
            var current = operations.find(function (item) { return Number(item.operation_id) === Number(selected || operationId) && Number(item.operation_id) > 0; });
            var next = current || operations[0];
            select.value = next ? next.event_id : '';
            var mapSelect = document.querySelector('[data-hq-map-operation]');
            if (mapSelect) { mapSelect.replaceChildren(...Array.from(select.options).map(function (option) { return option.cloneNode(true); })); mapSelect.value = select.value; }
            if (keepSelection && current && Number(current.operation_id) === operationId) {
                await refresh();
            } else {
                await selectEvent(next ? Number(next.event_id) : 0);
                if (!next) clearOperation('No hay eventos del calendario disponibles para hoy o fechas futuras.');
            }
        }
        root.addEventListener('burnout:hq-section', function (event) {
            section = event.detail;
            updateHeading();
            if (section === 'ajustes') loadCatalog().catch(function (error) { report(error.message, true); });
            if (section === 'mapa') { window.clearTimeout(timer); return; }
            loadOperations(operationId, true).catch(function (error) { report(error.message, true); });
        });
        document.querySelector('[data-hq-map-operation]')?.addEventListener('change', async function (event) {
            root.querySelector('[data-hq-operation]').value = event.target.value;
            await selectEvent(Number(event.target.value));
        });
        root.querySelector('[data-hq-operation]')?.addEventListener('change', async function (event) {
            var mapSelect = document.querySelector('[data-hq-map-operation]');
            if (mapSelect) mapSelect.value = event.target.value;
            await selectEvent(Number(event.target.value));
        });
        root.addEventListener('submit', async function (event) {
            var form = event.target.closest('[data-hq-form]');
            if (!form) return;
            event.preventDefault();
            var action = form.dataset.hqForm;
            var payload = Object.fromEntries(new FormData(form));
            if (action === 'mission') payload.status = event.submitter?.value || 'draft';
            if (action === 'message') payload.team = channel;
            var submitButtons = form.querySelectorAll('[type="submit"]');
            submitButtons.forEach(function (button) { button.disabled = true; });
            var submittedOperation = operationId;
            var submittedEditorVersion = catalogEditorVersion;
            try {
                var result = await api(action, payload, true);
                if (!root.isConnected) return;
                report(action === 'message' ? 'Transmisión enviada.' : 'Cambios guardados.');
                if (action === 'catalog_save') {
                    await loadCatalog();
                    if (submittedEditorVersion === catalogEditorVersion) { selectCatalogItem(result.catalog_id); closeCatalogEditor(); }
                } else if (action === 'login') {
                    operationId = Number(result.operation_id); channel = result.team;
                    resetChannel();
                    await refresh();
                    form.elements.code.value = '';
                } else if (submittedOperation === operationId) {
                    if (action === 'mission_update') { form.hidden = true; signature = ''; }
                    if (action === 'mission' || (action === 'message' && form.elements.body.value === payload.body)) form.reset();
                    if (action === 'mission') missionSource(form, null);
                    if (action === 'settings') await loadOperations(operationId);
                    else await refresh();
                }
            } catch (error) {
                report(error.message, true);
                if (action === 'catalog_save' && submittedEditorVersion === catalogEditorVersion) { var editorFeedback = catalogEditor.querySelector('[data-hq-catalog-editor-feedback]'); editorFeedback.textContent = error.message; editorFeedback.classList.add('is-error'); }
            }
            finally { submitButtons.forEach(function (button) { button.disabled = action === 'message' && (!operation || operation.status !== 'active'); }); }
        });
        root.addEventListener('click', async function (event) {
            var button = event.target.closest('button');
            if (!button) return;
            if (button.dataset.hqCatalogAction) {
                if (button.dataset.hqCatalogAction === 'edit') openCatalogEditor(button.dataset.hqCatalogId, button);
                else openCatalogDelete(button.dataset.hqCatalogId, button);
                return;
            }
            if (button.dataset.hqScriptAction) {
                var scriptAction = button.dataset.hqScriptAction;
                if (scriptAction === 'remove' && !window.confirm('¿Eliminar esta misión del guion? Se conservará en el historial como eliminada.')) return;
                button.disabled = true;
                try {
                    await api(scriptAction === 'remove' ? 'mission_remove' : 'mission_move', { mission_id: button.dataset.hqScriptMission, direction: scriptAction }, true);
                    await refresh();
                } catch (error) { report(error.message, true); button.disabled = false; }
                return;
            }
            if (button.dataset.hqMissionTeam) {
                missionTeam = button.dataset.hqMissionTeam;
                root.querySelectorAll('[data-hq-mission-team]').forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                    item.setAttribute('aria-pressed', String(item === button));
                });
                await refresh(); return;
            }
            if (button.hasAttribute('data-hq-import')) { await openImport(button); return; }
            if (button.hasAttribute('data-hq-catalog-new')) {
                openCatalogEditor(0, button);
                return;
            }
            if (button.hasAttribute('data-hq-cancel-edit')) {
                button.closest('[data-hq-mission-editor]').hidden = true;
                signature = '';
                await refresh();
                return;
            }
            if (button.dataset.hqStatus === 'edit') {
                var editor = button.closest('.hq-briefing').querySelector('[data-hq-mission-editor]');
                editor.hidden = false;
                editor.elements.title.focus();
                return;
            }
            if (button.dataset.hqChannel) {
                channel = button.dataset.hqChannel;
                resetChannel();
                root.querySelectorAll('[data-hq-channel]').forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                    item.setAttribute('aria-pressed', String(item === button));
                });
                refresh();
                return;
            }
            if (!button.hasAttribute('data-hq-access') && !button.hasAttribute('data-hq-mission') && !button.hasAttribute('data-hq-logout')) return;
            if (button.dataset.hqStatus === 'delete' && !window.confirm('¿Marcar esta misión como eliminada? Se conservará tachada en el historial.')) return;
            if (button.dataset.hqAccess && !window.confirm('¿Generar un código para ' + button.dataset.hqAccess.toUpperCase() + '? Se invalidará su código anterior.')) return;
            button.disabled = true;
            var selectedOperation = operationId;
            try {
                if (button.hasAttribute('data-hq-logout')) {
                    await api('logout', {}, true);
                    connected = false;
                    resetChannel();
                    window.clearTimeout(timer);
                    root.querySelector('[data-hq-login]').hidden = false;
                    root.querySelector('[data-hq-dashboard]').hidden = true;
                    document.querySelector('[data-hq-team-heading]').hidden = true;
                    operationId = 0;
                } else if (button.dataset.hqAccess) {
                    var result = await api('access', { team: button.dataset.hqAccess }, true);
                    if (selectedOperation !== operationId || !root.isConnected) return;
                    var output = root.querySelector('[data-hq-access-code]');
                    output.textContent = result.team.toUpperCase() + ' · ' + result.code;
                    output.hidden = false;
                    report('Código generado. Compártelo con el enlace del equipo.');
                } else {
                    await api(button.dataset.hqStatus === 'ack' ? 'ack' : 'mission_status', { mission_id: button.dataset.hqMission, status: button.dataset.hqStatus }, true);
                    report('Misión actualizada.');
                    await refresh();
                }
            } catch (error) { report(error.message, true); }
            finally { button.disabled = false; }
        });
        function visibility() { if (document.hidden) window.clearTimeout(timer); else if (connected && operationId) refresh(); }
        document.addEventListener('visibilitychange', visibility);
        window.initHqPage.cleanup = function () {
            if (!root.isConnected) {
                generation++;
                window.clearTimeout(timer);
                document.removeEventListener('visibilitychange', visibility);
                document.body.classList.remove('burnout-popup-open');
            }
        };
        if (teamView) {
            refresh();
        } else {
            root.querySelector('[data-hq-send]').disabled = true;
            loadOperations().catch(function (error) { report(error.message, true); });
        }
    };
    if (!window.__burnoutLoadingPageScript) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window.initHqPage);
        else window.initHqPage();
    }
})();
