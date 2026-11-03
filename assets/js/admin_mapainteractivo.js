(function () {
    'use strict';
    window.initAdminMapaInteractivoPage = function () {
        if (window.initAdminMapaInteractivoPage.cleanup) window.initAdminMapaInteractivoPage.cleanup();
        var root = document.querySelector('.admin-map-page');
        if (!root || root.dataset.mapReady || !root.querySelector('canvas')) return;
        root.dataset.mapReady = 'true';
        var canvas = root.querySelector('canvas'), ctx = canvas.getContext('2d');
        var background = root.querySelector('.admin-map-background');
        var tool = 'pen', drawing = false, start, snapshot, history = [], future = [], busy = false;
        var status = root.querySelector('[data-map-status]');
        var mapPanel = root.querySelector('[data-map-panel="mapa"]');
        var fullscreenButton = root.querySelector('[data-map-action="fullscreen"]');
        var viewport = root.querySelector('.admin-map-viewport');
        var stage = root.querySelector('.admin-map-canvas-stage');
        var zoom = 1;
        var panStart = null, activePointer = null;
        var textModal = root.querySelector('[data-map-text-modal]');
        var textInput = root.querySelector('[data-map-text-input]');
        var textPosition = null, textFocus = null;
        var mapId = 0, mapVersion = 0, savedVersion = 0, saveQueue = Promise.resolve(true), loadVersion = 0, retryTimer, saveTimer, saving = false;
        var saveStatus = root.querySelector('[data-map-save-status]');
        busy = true;
        async function mapRequest(action, id, annotations) {
            var data = new URLSearchParams({ action: action, operation_id: id, view: 'admin' });
            var post = action === 'map_save';
            if (post) { data.set('csrf_token', root.querySelector('[data-hq-console]').dataset.hqCsrf); data.set('annotations', annotations); }
            var response = await fetch('hq_api.php' + (post ? '' : '?' + data), {
                method: post ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store',
                headers: post ? { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' } : {},
                body: post ? data.toString() : undefined
            });
            var result = await response.json();
            if (!response.ok) throw new Error(result.error || 'No se ha podido guardar o cargar el mapa.');
            return result;
        }
        function saveMap() {
            window.clearTimeout(retryTimer); retryTimer = null; window.clearTimeout(saveTimer);
            if (saving) return saveQueue;
            if (!mapId || savedVersion === mapVersion) return Promise.resolve(true);
            var id = mapId, version = mapVersion, image = canvas.toDataURL('image/png');
            saving = true; saveStatus.textContent = 'Guardando mapa...';
            saveQueue = (async function () {
                try {
                    await mapRequest('map_save', id, image);
                    if (id === mapId) { savedVersion = version; saveStatus.textContent = savedVersion === mapVersion ? 'Mapa guardado.' : 'Guardando mapa...'; }
                    return true;
                } catch (error) {
                    if (id === mapId) {
                        saveStatus.textContent = 'No se ha guardado el mapa. Se reintentará automáticamente.';
                        retryTimer = window.setTimeout(function () { if (root.isConnected && id === mapId) saveMap(); }, 5000);
                    }
                    return false;
                } finally {
                    saving = false;
                    if (root.isConnected && id === mapId && savedVersion !== mapVersion && !retryTimer) {
                        window.clearTimeout(saveTimer);
                        saveTimer = window.setTimeout(saveMap, 300);
                    }
                }
            })();
            return saveQueue;
        }
        function mapChanged() {
            mapVersion++; window.clearTimeout(saveTimer);
            saveTimer = window.setTimeout(saveMap, 300);
        }
        root.hqMap = {
            flush: async function () {
                if (drawing || textPosition) { saveStatus.textContent = 'Termina el dibujo antes de cambiar de partida.'; return false; }
                var wasBusy = busy; busy = true; buttons();
                try {
                    window.clearTimeout(saveTimer);
                    await saveQueue;
                    while (savedVersion !== mapVersion) { if (!await saveMap()) return false; }
                    return true;
                } catch (error) {
                    saveStatus.textContent = 'No se ha podido guardar el mapa. Conserva esta página abierta para reintentar.';
                    return false;
                } finally { busy = wasBusy; buttons(); }
            },
            load: async function (id) {
                window.clearTimeout(retryTimer); retryTimer = null; window.clearTimeout(saveTimer);
                var request = ++loadVersion;
                mapId = id; busy = true; history = []; future = []; mapVersion = savedVersion = 0; blank(); buttons();
                closeTextModal();
                if (!id) { saveStatus.textContent = ''; return; }
                saveStatus.textContent = 'Cargando mapa...';
                try {
                    var data = await mapRequest('map', id);
                    if (request !== loadVersion || !root.isConnected) return;
                    if (data.annotations) {
                        var image = new Image();
                        image.src = data.annotations;
                        await image.decode();
                        if (request !== loadVersion || !root.isConnected) return;
                        ctx.globalCompositeOperation = 'source-over';
                        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
                    }
                    busy = false; buttons(); saveStatus.textContent = '';
                } catch (error) { if (request === loadVersion) saveStatus.textContent = 'No se ha podido cargar el mapa. Vuelve a seleccionar la partida.'; }
            }
        };
        function beforeUnload(event) {
            if (root.isConnected && (drawing || textPosition || savedVersion !== mapVersion)) { event.preventDefault(); event.returnValue = ''; }
        }
        window.addEventListener('beforeunload', beforeUnload);
        window.initAdminMapaInteractivoPage.cleanup = function () {
            if (!root.isConnected) {
                loadVersion++; window.clearTimeout(retryTimer); window.clearTimeout(saveTimer);
                window.removeEventListener('beforeunload', beforeUnload);
            }
        };
        function closeTextModal() {
            textModal.classList.remove('is-open');
            textModal.setAttribute('aria-hidden', 'true');
            textPosition = null;
            if (textFocus && textFocus.isConnected) textFocus.focus();
        }
        textModal.querySelectorAll('[data-map-text-close]').forEach(function (button) {
            button.addEventListener('click', closeTextModal);
        });
        textModal.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeTextModal(); }
            if (event.key === 'Tab') {
                var first = textModal.querySelector('button'), last = textModal.querySelector('[type="submit"]');
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
        root.querySelector('[data-map-text-form]').addEventListener('submit', function (event) {
            event.preventDefault();
            if (!textPosition || !textInput.value.trim()) { textInput.focus(); return; }
            remember();
            ctx.globalCompositeOperation = 'source-over';
            ctx.fillStyle = root.querySelector('[data-map-color]').value;
            ctx.font = Math.max(16, Number(root.querySelector('[data-map-size]').value) * 4) + 'px Yantramanav, sans-serif';
            ctx.textBaseline = 'top';
            ctx.fillText(textInput.value, textPosition.x, textPosition.y);
            closeTextModal();
            mapChanged();
            say('Texto añadido.');
        });
        function changeZoom(delta) {
            var next = Math.max(1, Math.min(3, Math.round((zoom + delta) * 100) / 100));
            var centerX = (viewport.scrollLeft + viewport.clientWidth / 2 - stage.offsetLeft) / stage.offsetWidth;
            var centerY = (viewport.scrollTop + viewport.clientHeight / 2 - stage.offsetTop) / stage.offsetHeight;
            zoom = next;
            stage.style.width = (zoom * 100) + '%';
            viewport.scrollLeft = stage.offsetLeft + centerX * stage.offsetWidth - viewport.clientWidth / 2;
            viewport.scrollTop = stage.offsetTop + centerY * stage.offsetHeight - viewport.clientHeight / 2;
            root.querySelector('[data-map-action="zoom-in"]').disabled = zoom >= 3;
            root.querySelector('[data-map-action="zoom-out"]').disabled = zoom <= 1;
        }
        function syncFullscreen() {
            var active = document.fullscreenElement === mapPanel;
            var label = active ? 'Salir de pantalla completa' : 'Pantalla completa';
            fullscreenButton.title = label;
            fullscreenButton.setAttribute('aria-label', label);
            fullscreenButton.setAttribute('aria-pressed', String(active));
            fullscreenButton.querySelector('[data-map-fullscreen-icon]').setAttribute('d', active
                ? 'M3 8h5V3M16 3v5h5M21 16h-5v5M8 21v-5H3'
                : 'M8 3H3v5M16 3h5v5M21 16v5h-5M8 21H3v-5');
        }
        mapPanel.addEventListener('fullscreenchange', syncFullscreen);
        fullscreenButton.disabled = !document.fullscreenEnabled || !mapPanel.requestFullscreen;
        async function toggleFullscreen() {
            try {
                if (document.fullscreenElement === mapPanel) await document.exitFullscreen();
                else await mapPanel.requestFullscreen();
            } catch (error) {
                window.alert('No se ha podido cambiar a pantalla completa. Vuelve a intentarlo.');
            }
            syncFullscreen();
        }
        function say(message) { if (status) status.textContent = message; }
        function buttons() {
            root.querySelector('[data-map-action="undo"]').disabled = !history.length || busy;
            root.querySelector('[data-map-action="redo"]').disabled = !future.length || busy;
        }
        function remember() {
            history.push(ctx.getImageData(0, 0, canvas.width, canvas.height));
            if (history.length > 15) history.shift();
            future = []; buttons();
        }
        function blank() { ctx.clearRect(0, 0, canvas.width, canvas.height); }
        function section(name) {
            root.querySelectorAll('[data-map-section]').forEach(function (button) {
                var active = button.dataset.mapSection === name;
                button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', String(active));
            });
            root.querySelectorAll('[data-map-panel]').forEach(function (panel) { panel.hidden = panel.dataset.mapPanel !== name; });
            var console = root.querySelector('[data-hq-console]');
            if (console) {
                console.hidden = name === 'mapa';
                console.dispatchEvent(new CustomEvent('burnout:hq-section', { detail: name }));
            }
        }
        function point(event) {
            var rect = canvas.getBoundingClientRect();
            return { x: (event.clientX - rect.left) * canvas.width / rect.width, y: (event.clientY - rect.top) * canvas.height / rect.height };
        }
        function stroke(p) {
            ctx.globalCompositeOperation = tool === 'eraser' ? 'destination-out' : 'source-over';
            ctx.strokeStyle = root.querySelector('[data-map-color]').value;
            ctx.lineWidth = Number(root.querySelector('[data-map-size]').value);
            ctx.lineCap = 'round'; ctx.lineJoin = 'round';
            if (tool === 'pen' || tool === 'eraser') { ctx.lineTo(p.x, p.y); ctx.stroke(); return; }
            ctx.putImageData(snapshot, 0, 0); ctx.beginPath();
            if (tool === 'line' || tool === 'arrow') { ctx.moveTo(start.x, start.y); ctx.lineTo(p.x, p.y); }
            if (tool === 'arrow') {
                var angle = Math.atan2(p.y - start.y, p.x - start.x);
                var length = Math.min(Math.max(14, ctx.lineWidth * 3), Math.hypot(p.x - start.x, p.y - start.y) * .5);
                ctx.moveTo(p.x - length * Math.cos(angle - Math.PI / 6), p.y - length * Math.sin(angle - Math.PI / 6));
                ctx.lineTo(p.x, p.y);
                ctx.lineTo(p.x - length * Math.cos(angle + Math.PI / 6), p.y - length * Math.sin(angle + Math.PI / 6));
            }
            if (tool === 'rectangle') ctx.rect(start.x, start.y, p.x - start.x, p.y - start.y);
            if (tool === 'circle') ctx.ellipse((start.x + p.x) / 2, (start.y + p.y) / 2, Math.abs(p.x - start.x) / 2, Math.abs(p.y - start.y) / 2, 0, 0, Math.PI * 2);
            ctx.stroke();
        }
        canvas.addEventListener('pointerdown', function (event) {
            if (busy || textPosition || (event.pointerType === 'mouse' && event.button !== 0) || drawing || panStart) return;
            if (tool === 'pan') {
                event.preventDefault();
                activePointer = event.pointerId;
                panStart = { x: event.clientX, y: event.clientY, left: viewport.scrollLeft, top: viewport.scrollTop };
                canvas.classList.add('is-panning');
                canvas.setPointerCapture(event.pointerId);
                return;
            }
            if (tool === 'text') {
                event.preventDefault();
                textPosition = point(event);
                textFocus = root.querySelector('[data-map-tool="text"]');
                textInput.value = '';
                textModal.classList.add('is-open');
                textModal.setAttribute('aria-hidden', 'false');
                textInput.focus();
                return;
            }
            event.preventDefault(); remember(); snapshot = ctx.getImageData(0, 0, canvas.width, canvas.height);
            drawing = true; activePointer = event.pointerId; start = point(event); canvas.setPointerCapture(event.pointerId);
            ctx.beginPath(); ctx.moveTo(start.x, start.y); stroke({ x: start.x + .01, y: start.y });
        });
        canvas.addEventListener('pointermove', function (event) {
            if (event.pointerId !== activePointer) return;
            if (panStart) {
                viewport.scrollLeft = panStart.left + panStart.x - event.clientX;
                viewport.scrollTop = panStart.top + panStart.y - event.clientY;
            } else if (drawing) stroke(point(event));
        });
        function finish(event) {
            if (event.pointerId !== activePointer) return;
            if (drawing) { drawing = false; snapshot = null; ctx.globalCompositeOperation = 'source-over'; say('Mapa modificado.'); mapChanged(); }
            panStart = null;
            activePointer = null;
            canvas.classList.remove('is-panning');
            if (canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId);
        }
        canvas.addEventListener('pointerup', finish);
        canvas.addEventListener('pointercancel', finish);
        canvas.addEventListener('lostpointercapture', finish);
        root.querySelectorAll('[data-map-section]').forEach(function (button) { button.addEventListener('click', function () { section(button.dataset.mapSection); }); });
        root.querySelectorAll('[data-map-tool]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (drawing || panStart) return;
                tool = button.dataset.mapTool;
                canvas.classList.toggle('is-pan-tool', tool === 'pan');

                root.querySelectorAll('[data-map-tool]').forEach(function (item) { var active = item === button; item.classList.toggle('is-active', active); item.setAttribute('aria-pressed', String(active)); });
            });
        });
        root.querySelector('[data-map-size]').addEventListener('input', function (event) { root.querySelector('[data-map-size-label]').textContent = event.target.value + ' px'; });
        root.querySelectorAll('[data-map-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.dataset.mapAction;
                if (busy || drawing || panStart) return;
                if (action === 'zoom-in' || action === 'zoom-out') { changeZoom(action === 'zoom-in' ? .25 : -.25); return; }
                if (action === 'fullscreen') { toggleFullscreen(); return; }
                if (action === 'back') section('mapa');
                if (action === 'undo' && history.length) { future.push(ctx.getImageData(0, 0, canvas.width, canvas.height)); ctx.putImageData(history.pop(), 0, 0); say('Último cambio deshecho.'); }
                if (action === 'redo' && future.length) { history.push(ctx.getImageData(0, 0, canvas.width, canvas.height)); ctx.putImageData(future.pop(), 0, 0); say('Cambio rehecho.'); }
                if (action === 'clear' && window.confirm('¿Eliminar todas las anotaciones? El plano se conservará y podrás deshacer esta acción.')) { remember(); blank(); say('Anotaciones eliminadas.'); }
                if (action === 'export') {
                    if (!background.complete || !background.naturalWidth) { say('Espera a que se cargue el plano para exportar.'); return; }
                    var exported = document.createElement('canvas');
                    exported.width = canvas.width; exported.height = canvas.height;
                    var exportedContext = exported.getContext('2d');
                    exportedContext.drawImage(background, 0, 0, canvas.width, canvas.height);
                    exportedContext.drawImage(canvas, 0, 0);
                    var link = document.createElement('a'); link.download = 'burnout-mapa.png'; link.href = exported.toDataURL('image/png'); link.click(); say('Mapa exportado como PNG.');
                }
                if (action === 'undo' || action === 'redo' || action === 'clear') mapChanged();
                buttons();
            });
        });
        blank();
        function backgroundState() {
            root.querySelector('.admin-map-load-error').hidden = Boolean(background.naturalWidth);
        }
        background.addEventListener('load', backgroundState);
        background.addEventListener('error', backgroundState);
        if (background.complete) backgroundState();

    };
    if (!window.__burnoutLoadingPageScript) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window.initAdminMapaInteractivoPage);
        else window.initAdminMapaInteractivoPage();
    }
})();
