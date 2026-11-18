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
        var rosterSignature = '';
        var teamMapSignature = '', teamMapVersion = 0;
        function clearTeamMissionMap() {
            teamMapVersion++; teamMapSignature = '';
            var map = root.querySelector('#hq-team-map');
            if (map && map.burnoutMap) map.burnoutMap.setAnnotations(null);
        }
        async function updateTeamMissionMap(current, requestGeneration) {
            if (!teamView) return;
            var map = root.querySelector('#hq-team-map');
            if (!map) return;
            var component = window.burnoutCreateMap(map);
            var key = current ? operationId + ':' + current.mission_id + ':' + current.version : 'empty';
            if (key === teamMapSignature) return;
            var version = ++teamMapVersion;
            await component.setAnnotations(null);
            teamMapSignature = '';
            if (current && current.version) {
                var response = await api('team_map');
                if (!root.isConnected || version !== teamMapVersion || requestGeneration !== generation) return;
                var saved = response.mission_map;
                if (!saved || saved.mission_id !== current.mission_id || saved.version !== current.version) return;
                await component.setAnnotations(saved.annotations);
            }
            if (root.isConnected && version === teamMapVersion && requestGeneration === generation) teamMapSignature = key;
        }
        var rosterBusy = false;
        var teamRoster = [], rolePlayer = null, roleOpener = null;
        var rolePopup = root.querySelector('[data-hq-role-popup]');
        var playerGroups = [], selectedPlayerGroup = 0, playersSignature = '', playersBusy = false, playersGeneration = 0;
        var statusLabels = { draft: 'Pendiente', active: 'En curso', completed: 'Completada', failed: 'Fallida', deleted: 'Eliminada' };
        var feedback = root.querySelector('[data-hq-feedback]');
        var signal = root.querySelector('[data-hq-signal]');
        var catalog = [], catalogCategories = [], catalogGeneration = 0, importTarget = null, importOpener = null;
        var importPopup = root.querySelector('[data-hq-import-popup]');
        var filterPopup = root.querySelector('[data-hq-catalog-filter-popup]');
        var filterOpener = null;
        var catalogEditor = root.querySelector('[data-hq-catalog-editor-popup]'), catalogEditorOpener = null, catalogEditorVersion = 0;
        var catalogDelete = root.querySelector('[data-hq-catalog-delete-popup]'), catalogDeleteId = 0, catalogDeleteOpener = null, catalogDeleting = false;
        var accessPopup = root.querySelector('[data-hq-access-popup]'), accessResolve = null, accessOpener = null;
        function closeAccessConfirm(accepted) {
            if (!accessResolve) return;
            var resolve = accessResolve; accessResolve = null;
            accessPopup.classList.remove('is-open'); accessPopup.setAttribute('aria-hidden', 'true');
            if (!root.querySelector('.burnout-popup.is-open')) document.body.classList.remove('burnout-popup-open');
            if (accessOpener && accessOpener.isConnected) accessOpener.focus();
            resolve(accepted === true);
        }
        function confirmAccess(button) {
            if (accessResolve) return Promise.resolve(false);
            accessOpener = button;
            accessPopup.querySelector('[data-hq-access-confirm-team]').textContent = button.dataset.hqAccess === 'alpha' ? 'Alpha' : 'Bravo';
            accessPopup.classList.add('is-open'); accessPopup.setAttribute('aria-hidden', 'false');
            document.body.classList.add('burnout-popup-open');
            accessPopup.querySelector('.burnout-popup__actions [data-hq-access-close]').focus();
            return new Promise(function (resolve) { accessResolve = resolve; });
        }
        var missionMapPopup = root.querySelector('[data-hq-mission-map-popup]');
        var missionMapParent = null, missionMapOpener = null, missionMapId = 0, missionMapApi = null;
        var missionMapGeneration = 0, missionMapBusy = false, missionMapReady = false, missionMapDraft = null;
        function mapIcon(button) {
            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', 'm10 5-2-1-6 3v14l6-3 8 3 6-3v-5M8 4v14M16 16v5M22 7c0 3-5 7-5 7s-5-4-5-7a5 5 0 0 1 10 0ZM19 7a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z');
            svg.append(path); button.append(svg);
        }
        async function closeMissionMap() {
            if (missionMapBusy) return;
            missionMapGeneration++;
            if (document.fullscreenElement && missionMapPopup.contains(document.fullscreenElement)) await document.exitFullscreen();
            if (missionMapApi) missionMapApi.closePopups();
            missionMapPopup.classList.remove('is-open'); missionMapPopup.setAttribute('aria-hidden', 'true');
            if (missionMapParent) {
                missionMapParent.classList.add('is-open'); missionMapParent.setAttribute('aria-hidden', 'false');
                document.body.classList.add('burnout-popup-open');
            }
            if (missionMapOpener && missionMapOpener.isConnected) missionMapOpener.focus();
        }
        async function openMissionMap(id, editable, opener) {
            if (missionMapBusy) return;
            var request = ++missionMapGeneration;
            missionMapId = Number(id); missionMapOpener = opener;
            missionMapParent = editable ? catalogEditor : importPopup;
            missionMapParent.classList.remove('is-open'); missionMapParent.setAttribute('aria-hidden', 'true');
            missionMapPopup.classList.add('is-open'); missionMapPopup.setAttribute('aria-hidden', 'false');
            var edit = missionMapPopup.querySelector('[data-hq-mission-map-editable]');
            var preview = missionMapPopup.querySelector('[data-hq-mission-map-preview]');
            edit.hidden = !editable; preview.hidden = editable;
            var save = missionMapPopup.querySelector('[data-hq-mission-map-save]');
            save.hidden = !editable; save.disabled = true; missionMapReady = false;
            var message = missionMapPopup.querySelector('[data-hq-mission-map-feedback]');
            message.textContent = 'Cargando mapa…'; message.classList.remove('is-error');
            missionMapPopup.querySelector('button[data-hq-mission-map-close]').focus();
            missionMapApi = window.burnoutCreateMap((editable ? edit : preview).querySelector('[data-burnout-map]'));
            // Ocultar el dibujo anterior mientras se recupera la misión elegida.
            (editable ? edit : preview).style.visibility = 'hidden';
            try {
                var data = missionMapId ? await api('catalog_map', { catalog_id: missionMapId }) : { annotations: missionMapDraft };
                if (!root.isConnected || request !== missionMapGeneration) return;
                await missionMapApi.setAnnotations(data.annotations);
                if (!root.isConnected || request !== missionMapGeneration) return;
                missionMapApi.changeZoom(-3); missionMapReady = true;
                save.disabled = false; message.textContent = '';
                (editable ? edit : preview).style.visibility = '';
            } catch (error) {
                if (request === missionMapGeneration) { message.textContent = error.message; message.classList.add('is-error'); }
            }
        }
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
                } else {
                    var mapCell = node('td', 'hq-mission-map-column');
                    var mapButton = node('button', 'hq-mission-map-button'); mapButton.type = 'button';
                    mapButton.dataset.hqMissionMapPreview = item.id;
                    mapButton.title = 'Ver mapa'; mapButton.setAttribute('aria-label', 'Ver mapa: ' + item.title);
                    mapIcon(mapButton); mapCell.append(mapButton); row.append(mapCell);
                }
                body.append(row);
            });
            if (!items.length) { var row = node('tr'), cell = node('td', 'hq-muted', 'No hay misiones con estos filtros.'); cell.colSpan = 3; row.append(cell); body.append(row); }
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
            missionMapDraft = null;
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
            accessPopup.querySelectorAll('[data-hq-access-close]').forEach(function (button) { button.addEventListener('click', function () { closeAccessConfirm(false); }); });
            accessPopup.querySelector('[data-hq-access-confirm]').addEventListener('click', function () { closeAccessConfirm(true); });
            accessPopup.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeAccessConfirm(false); return; }
                trapPopupFocus(event, accessPopup);
            });
            missionMapPopup.querySelectorAll('[data-hq-mission-map-close]').forEach(function (button) { button.addEventListener('click', closeMissionMap); });
            missionMapPopup.addEventListener('keydown', function (event) {
                if (event.target.closest('[data-map-clear-modal].is-open, [data-map-text-modal].is-open')) return;
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeMissionMap(); return; }
                // Los diálogos internos del componente gestionan su propio foco.
                if (!missionMapPopup.querySelector('.burnout-map .burnout-popup.is-open')) trapPopupFocus(event, missionMapPopup);
            });
            missionMapPopup.querySelector('[data-hq-mission-map-save]').addEventListener('click', async function (event) {
                if (missionMapBusy || !missionMapReady || !await missionMapApi.flush()) return;
                var saveButton = missionMapPopup.querySelector('[data-hq-mission-map-save]');
                missionMapBusy = true; saveButton.disabled = true;
                try {
                    var annotations = missionMapApi.exportAnnotations();
                    if (missionMapId) await api('catalog_map_save', { catalog_id: missionMapId, annotations: annotations }, true);
                    else missionMapDraft = annotations;
                    missionMapBusy = false;
                    if (root.isConnected) await closeMissionMap();
                } catch (error) {
                    var message = missionMapPopup.querySelector('[data-hq-mission-map-feedback]');
                    message.textContent = error.message; message.classList.add('is-error');
                } finally { missionMapBusy = false; saveButton.disabled = false; }
            });
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
                    if (event.target.closest('[data-hq-catalog-action], [data-hq-mission-map-preview]')) return;
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
        function playerRoleIcon(role) {
            var paths = {
                TL: 'm5 9 7-5 7 5M5 14l7-5 7 5M5 19l7-5 7 5',
                Ingeniero: 'm14 6 4 4 4-4a6 6 0 0 1-8 8L6 22l-4-4 8-8a6 6 0 0 1 8-8Z',
                'Radio Operator': 'M7 9h10v13H7ZM9 9V2m1 11h4m-4 4h4M14 2a6 6 0 0 1 6 6M14 5a3 3 0 0 1 3 3',
                'Segundo TL': 'm5 11 7-5 7 5M5 18l7-5 7 5',
                Medico: 'M9 3h6v6h6v6h-6v6H9v-6H3V9h6Z'
            };
            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', paths[role] || ''); svg.append(path);
            return svg;
        }
        function syncRolePopup() {
            if (!rolePopup || !rolePlayer) return;
            rolePopup.querySelector('#hqPlayerRoleTitle').textContent = rolePlayer.name;
            rolePopup.querySelectorAll('[data-hq-role-choice]').forEach(function (button) {
                var active = button.dataset.hqRoleChoice === rolePlayer.tactical_role;
                button.setAttribute('aria-pressed', String(active)); button.classList.toggle('is-active', active);
                button.disabled = rosterBusy || !operation || operation.status !== 'active';
            });
        }
        function closeRolePopup(force) {
            if (!rolePopup || (rosterBusy && !force)) return;
            rolePopup.classList.remove('is-open'); rolePopup.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('burnout-popup-open');
            var current = rolePlayer && root.querySelector('[data-hq-player-role="' + rolePlayer.id + '"]');
            if (current) current.focus();
            else if (roleOpener?.isConnected) roleOpener.focus();
            else root.querySelector('.hq-team-roster summary')?.focus();
            rolePlayer = null; roleOpener = null;
        }
        function openRolePopup(button) {
            if (!rolePopup || rosterBusy) return;
            rolePlayer = teamRoster.find(function (player) { return Number(player.id) === Number(button.dataset.hqPlayerRole); });
            if (!rolePlayer) return;
            roleOpener = button;
            rolePopup.querySelector('[data-hq-role-feedback]').textContent = '';
            syncRolePopup();
            rolePopup.classList.add('is-open'); rolePopup.setAttribute('aria-hidden', 'false');
            document.body.classList.add('burnout-popup-open');
            (rolePopup.querySelector('[data-hq-role-choice][aria-pressed="true"]') || rolePopup.querySelector('[data-hq-role-choice]')).focus();
        }
        function renderTeamPlayers(roster) {
            if (!teamView) return;
            teamRoster = roster ? roster.players : [];
            if (!roster || (rolePlayer && !teamRoster.some(function (player) { return player.id === rolePlayer.id; }))) closeRolePopup(true);
            var nextSignature = JSON.stringify([roster, operation?.status]);
            if (nextSignature === rosterSignature) return;
            rosterSignature = nextSignature;
            root.querySelector('[data-hq-roster-team]').textContent = roster ? (roster.team === 'alpha' ? 'Equipo Alpha' : 'Equipo Bravo') : 'Equipo';
            root.querySelector('[data-hq-roster-count]').textContent = teamRoster.length + (teamRoster.length === 1 ? ' jugador' : ' jugadores');
            var list = root.querySelector('[data-hq-roster-list]');
            var focusedPlayer = document.activeElement?.dataset?.hqPlayerRole;
            list.replaceChildren();
            teamRoster.forEach(function (player) {
                var row = node('li', 'hq-team-player'), info = node('div', 'hq-team-player-info');
                var name = node('button', 'hq-team-player-name', player.name);
                name.type = 'button'; name.dataset.hqPlayerRole = player.id;
                name.setAttribute('aria-haspopup', 'dialog');
                name.disabled = !operation || operation.status !== 'active';
                info.append(name, node('span', '', 'Equipo: ' + (player.team_name || 'Sin equipo')), node('span', '', player.role));
                row.append(info);
                if (player.tactical_role) {
                    row.dataset.hqFunction = player.tactical_role;
                    var badge = node('span', 'hq-team-player-function');
                    var label = { TL: 'Team Leader', Medico: 'Médico' }[player.tactical_role] || player.tactical_role;
                    badge.setAttribute('title', label); badge.setAttribute('aria-label', label); badge.setAttribute('role', 'img');
                    badge.append(playerRoleIcon(player.tactical_role)); row.append(badge);
                }
                list.append(row);
            });
            if (!teamRoster.length) list.append(node('li', 'hq-empty', 'Sin jugadores asignados.'));
            if (focusedPlayer) root.querySelector('[data-hq-player-role="' + focusedPlayer + '"]')?.focus();
            if (rolePlayer) { rolePlayer = teamRoster.find(function (player) { return player.id === rolePlayer.id; }); syncRolePopup(); }
        }
        async function savePlayerRole(button) {
            if (!teamView || rosterBusy || !operationId || !rolePlayer) return;
            rosterBusy = true;
            var request = ++generation, selectedOperation = operationId;
            var role = button.dataset.hqRoleChoice === rolePlayer.tactical_role ? '' : button.dataset.hqRoleChoice;
            window.clearTimeout(timer); syncRolePopup();
            var message = rolePopup.querySelector('[data-hq-role-feedback]'); message.textContent = '';
            try {
                var data = await api('player_role', { player_id: rolePlayer.id, tactical_role: role }, true);
                if (request !== generation || selectedOperation !== operationId || !root.isConnected) return;
                rosterSignature = ''; renderTeamPlayers(data.roster); closeRolePopup(true);
            } catch (error) {
                if (request !== generation || !root.isConnected) return;
                message.textContent = error.message; message.classList.add('is-error');
            } finally {
                rosterBusy = false;
                if (root.isConnected) { syncRolePopup(); schedule(); }
            }
        }
        if (rolePopup) {
            rolePopup.addEventListener('click', function (event) {
                if (event.target.closest('[data-hq-role-close]')) { closeRolePopup(); return; }
                var button = event.target.closest('[data-hq-role-choice]');
                if (button && !button.disabled) savePlayerRole(button);
            });
            rolePopup.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { event.preventDefault(); closeRolePopup(); }
                trapPopupFocus(event, rolePopup);
            });
        }
        function playerControls() {
            if (teamView) return;
            var selected = playerGroups.find(function (group) { return group.id === selectedPlayerGroup; });
            var disabled = playersBusy || !operation || operation.status !== 'active';
            root.querySelectorAll('[data-hq-players-move]').forEach(function (button) {
                button.disabled = disabled || !selected || selected.team === button.dataset.hqPlayersMove;
            });
            root.querySelector('[data-hq-players-balance]').disabled = disabled || !playerGroups.length;
            root.querySelectorAll('[data-hq-player-group]').forEach(function (button) {
                button.disabled = disabled;
                var active = Number(button.dataset.hqPlayerGroup) === selectedPlayerGroup;
                button.classList.toggle('is-selected', active);
                button.setAttribute('aria-pressed', String(active));
            });
        }
        function renderPlayers(groups) {
            if (teamView) return;
            playerGroups = groups;
            if (!groups.some(function (group) { return group.id === selectedPlayerGroup; })) selectedPlayerGroup = 0;
            var nextSignature = JSON.stringify(groups);
            if (nextSignature !== playersSignature) {
                playersSignature = nextSignature;
                var focused = document.activeElement;
                var focusedGroup = focused && focused.dataset ? focused.dataset.hqPlayerGroup : null;
                ['alpha', 'bravo'].forEach(function (team) {
                    var list = root.querySelector('[data-hq-players-list="' + team + '"]');
                    list.replaceChildren();
                    var count = 0, points = 0, roles = {};
                    groups.filter(function (group) { return group.team === team; }).forEach(function (group) {
                        var button = node('button', 'hq-players-group');
                        button.type = 'button'; button.dataset.hqPlayerGroup = group.id;
                        button.append(node('strong', '', (group.name || 'Inscripción #' + group.id) + ' · ' + group.players.length + ' jugadores'));
                        group.players.forEach(function (player) {
                            button.append(node('span', '', player.name + ' · ' + player.role));
                            roles[player.role] = (roles[player.role] || 0) + 1;
                            count++;
                        });
                        points += group.points;
                        list.append(button);
                    });
                    if (!list.children.length) list.append(node('p', 'hq-empty', operation ? 'Sin jugadores en este equipo.' : 'Selecciona una partida.'));
                    root.querySelector('[data-hq-players-total="' + team + '"]').textContent = count + ' jugadores · ' + points + ' puntos';
                    root.querySelector('[data-hq-players-roles="' + team + '"]').textContent = ['Fusilero', 'Tirador Selecto', 'Francotirador', 'Apoyo', 'Escudo', 'Sin especificar'].filter(function (role) { return role !== 'Sin especificar' || roles[role] > 0; }).map(function (role) { return role + ': ' + (roles[role] || 0); }).join(' · ');
                });
                if (focusedGroup) root.querySelector('[data-hq-player-group="' + focusedGroup + '"]')?.focus();
            }
            playerControls();
        }
        async function loadPlayers() {
            if (teamView || section !== 'jugadores' || playersBusy) return;
            var request = ++playersGeneration, selectedOperation = operationId;
            var data = await api('players');
            if (request !== playersGeneration || selectedOperation !== operationId || !root.isConnected || section !== 'jugadores') return;
            renderPlayers(data.groups);
        }
        async function changePlayers(button) {
            if (playersBusy || !operationId) return;
            var submittedOperation = operationId;
            var registration = selectedPlayerGroup;
            var balance = button.hasAttribute('data-hq-players-balance');
            var request = ++playersGeneration;
            playersBusy = true;
            window.clearTimeout(timer);
            playerControls();
            try {
                var data = await api(balance ? 'players_balance' : 'players_move', { registration_id: registration, team: button.dataset.hqPlayersMove || '' }, true);
                if (request !== playersGeneration || submittedOperation !== operationId || !root.isConnected) return;
                renderPlayers(data.groups);
            } catch (error) {
                if (submittedOperation === operationId && root.isConnected) report(error.message, true);
            } finally {
                playersBusy = false;
                if (root.isConnected) { playerControls(); schedule(); }
            }
        }
        function resetChannel() {
            if (teamView) clearTeamMissionMap();
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
                canales: ['Canales', 'CANALES'],
                jugadores: ['Jugadores', 'JUGADORES'],
                ajustes: ['Control de operación', 'AJUSTES']
            };
            var heading = !teamView && headings[section];
            root.querySelector('[data-hq-operation-heading]').textContent = heading ? 'Centro de Operaciones / ' + heading[0] : 'Centro de operaciones';
            var operationTitle = root.querySelector('[data-hq-operation-title]');
            if (operationTitle) operationTitle.textContent = operation ? eventLabel(operation) : 'Selecciona una partida';
            signal.hidden = teamView || Boolean(heading);
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
            if (operationId && connected && !document.hidden && (teamView || section === 'misiones' || section === 'canales' || section === 'ajustes' || section === 'jugadores')) timer = window.setTimeout(refresh, 5000);
        }
        async function refresh() {
            window.clearTimeout(timer);
            if (teamView && rosterBusy) return;
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
                renderTeamPlayers(data.roster || null);
                await updateTeamMissionMap(data.mission_map || null, requestGeneration);
                await loadPlayers();
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
                    renderTeamPlayers(null);
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
            playersGeneration++;
            selectedPlayerGroup = 0;
            playersSignature = '';
            renderPlayers([]);
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
            if (action === 'catalog_save' && missionMapDraft !== null) payload.annotations = missionMapDraft;
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
            if (button.hasAttribute('data-hq-mission-map-edit')) {
                openMissionMap(root.querySelector('[data-hq-form="catalog_save"]').elements.catalog_id.value, true, button); return;
            }
            if (button.hasAttribute('data-hq-mission-map-preview')) {
                openMissionMap(button.dataset.hqMissionMapPreview, false, button); return;
            }
            if (button.hasAttribute('data-hq-player-role')) { openRolePopup(button); return; }
            if (button.hasAttribute('data-hq-player-group')) {
                selectedPlayerGroup = selectedPlayerGroup === Number(button.dataset.hqPlayerGroup) ? 0 : Number(button.dataset.hqPlayerGroup);
                playerControls();
                return;
            }
            if (button.hasAttribute('data-hq-players-move') || button.hasAttribute('data-hq-players-balance')) {
                await changePlayers(button);
                return;
            }
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
            if (button.dataset.hqAccess) {
                var accessOperation = operationId;
                if (!await confirmAccess(button) || accessOperation !== operationId || !root.isConnected) return;
            }
            button.disabled = true;
            var selectedOperation = operationId;
            try {
                if (button.hasAttribute('data-hq-logout')) {
                    missionMapGeneration++;
                generation++;
                    window.clearTimeout(timer);
                    await api('logout', {}, true);
                    connected = false;
                    resetChannel();
                    window.clearTimeout(timer);
                    root.querySelector('[data-hq-login]').hidden = false;
                    root.querySelector('[data-hq-dashboard]').hidden = true;
                    document.querySelector('[data-hq-team-heading]').hidden = true;
                    renderTeamPlayers(null);
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
                teamMapVersion++;
                closeAccessConfirm(false);
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
