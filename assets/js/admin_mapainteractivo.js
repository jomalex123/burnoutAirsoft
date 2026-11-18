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
        var mapPanel = root.querySelector('[data-map-editor]');
        var fullscreenButton = root.querySelector('[data-map-action="fullscreen"]');
        var viewport = root.querySelector('.admin-map-viewport');
        var stage = root.querySelector('.admin-map-canvas-stage');
        var zoom = 1;
        var fillMode = 'none', fillPattern = null, fillPatternColor = '';
        var fillToggle = root.querySelector('[data-map-fill-toggle]');
        var fillMenu = root.querySelector('[data-map-fill-menu]');
        var panStart = null, activePointer = null;
        var textModal = root.querySelector('[data-map-text-modal]');
        var textInput = root.querySelector('[data-map-text-input]');
        var textPosition = null, textFocus = null;
        var clearModal = root.querySelector('[data-map-clear-modal]');
        var clearOpener = null;
        var mapId = 0, localMaps = new Map(), clearMapId = null;
        busy = true;
        root.hqMap = {
            flush: async function () { return !drawing && !textPosition && clearMapId === null; },
            load: async function (id) {
                if (mapId) localMaps.set(mapId, ctx.getImageData(0, 0, canvas.width, canvas.height));
                mapId = id;
                history = []; future = [];
                closeTextModal();
                closeClearModal(false);
                closeFillMenu(false);
                blank();
                if (localMaps.has(id)) ctx.putImageData(localMaps.get(id), 0, 0);
                busy = !id;
                buttons();
            }
        };
        window.initAdminMapaInteractivoPage.cleanup = function () {
            if (!root.isConnected) {
                localMaps.clear();
                closeClearModal(false);
                if (root.hqFillCleanup) root.hqFillCleanup();
            }
        };
        function closeTextModal() {
            textModal.classList.remove('is-open');
            textModal.setAttribute('aria-hidden', 'true');
            textPosition = null;
            if (textFocus && textFocus.isConnected) textFocus.focus();
        }
        function closeClearModal(restoreFocus) {
            if (!clearModal || clearMapId === null) return;
            clearModal.classList.remove('is-open'); clearModal.setAttribute('aria-hidden', 'true');
            clearMapId = null;
            document.body.classList.remove('burnout-popup-open');
            if (restoreFocus && clearOpener && clearOpener.isConnected) clearOpener.focus();
            clearOpener = null;
        }
        function openClearModal(button) {
            if (!clearModal || busy || drawing || panStart || textPosition) return;
            closeFillMenu(false);
            clearMapId = mapId; clearOpener = button;
            clearModal.classList.add('is-open'); clearModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('burnout-popup-open');
            clearModal.querySelector('[data-map-clear-cancel]').focus();
        }
        if (clearModal) {
            clearModal.querySelectorAll('[data-map-clear-close]').forEach(function (button) {
                button.addEventListener('click', function () { closeClearModal(true); });
            });
            clearModal.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeClearModal(true); return; }
                if (event.key !== 'Tab') return;
                var controls = Array.from(clearModal.querySelectorAll('button:not(:disabled)'));
                var first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            });
            clearModal.querySelector('[data-map-clear-confirm]').addEventListener('click', function () {
                var allowed = clearMapId !== null && clearMapId === mapId && !busy && !drawing && !panStart;
                closeClearModal(true);
                if (!allowed) return;
                remember(); blank(); say('Anotaciones eliminadas.'); buttons();
            });
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
            say('Texto añadido.');
        });
        function closeFillMenu(restoreFocus) {
            if (!fillMenu || !fillToggle) return;
            fillMenu.hidden = true;
            fillToggle.setAttribute('aria-expanded', 'false');
            if (restoreFocus) fillToggle.focus();
        }
        function openFillMenu() {
            if (!fillMenu || drawing || panStart) return;
            fillMenu.hidden = false;
            fillToggle.setAttribute('aria-expanded', 'true');
            var anchor = fillToggle.getBoundingClientRect(), panel = mapPanel.getBoundingClientRect();
            fillMenu.style.left = Math.max(0, Math.min(anchor.left - panel.left + mapPanel.scrollLeft, mapPanel.clientWidth - fillMenu.offsetWidth)) + 'px';
            fillMenu.style.top = (anchor.bottom - panel.top + mapPanel.scrollTop + 6) + 'px';
            fillMenu.querySelector('[aria-selected="true"]').focus();
        }
        function fillOutside(event) {
            if (fillMenu && !fillMenu.hidden && !fillMenu.contains(event.target) && !fillToggle.contains(event.target)) closeFillMenu(false);
        }
        if (fillToggle && fillMenu) {
            var fillOptions = Array.from(fillMenu.querySelectorAll('[data-map-fill]'));
            function syncFillColor() { mapPanel.style.setProperty('--admin-map-fill-color', root.querySelector('[data-map-color]').value); }
            syncFillColor();
            root.querySelector('[data-map-color]').addEventListener('input', syncFillColor);
            fillToggle.addEventListener('click', function () { if (fillMenu.hidden) openFillMenu(); else closeFillMenu(true); });
            fillToggle.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); openFillMenu(); }
            });
            fillMenu.addEventListener('click', function (event) {
                var option = event.target.closest('[data-map-fill]');
                if (!option || drawing || panStart) return;
                fillMode = option.dataset.mapFill;
                fillOptions.forEach(function (item) { item.setAttribute('aria-selected', String(item === option)); });
                var icon = option.querySelector('svg').cloneNode(true); icon.setAttribute('data-map-fill-icon', '');
                fillToggle.querySelector('[data-map-fill-icon]').replaceWith(icon);
                var label = 'Relleno: ' + option.getAttribute('aria-label');
                fillToggle.title = label; fillToggle.setAttribute('aria-label', label);
                closeFillMenu(true);
            });
            fillMenu.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' || event.key === 'Tab') {
                    if (event.key === 'Escape') event.preventDefault();
                    event.stopPropagation(); closeFillMenu(true); return;
                }
                var index = fillOptions.indexOf(document.activeElement);
                if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                var next = event.key === 'Home' ? 0 : event.key === 'End' ? fillOptions.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + fillOptions.length) % fillOptions.length;
                fillOptions[next].focus();
            });
            document.addEventListener('pointerdown', fillOutside);
            root.querySelector('.admin-map-toolbar').addEventListener('scroll', function () { closeFillMenu(false); });
            root.hqFillCleanup = function () { document.removeEventListener('pointerdown', fillOutside); };
        }
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
            closeFillMenu(false);
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
            closeClearModal(true);
            closeFillMenu(false);
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
        function shapeFillStyle() {
            if (fillMode === 'none') return null;
            var color = root.querySelector('[data-map-color]').value;
            if (fillMode === 'solid') return color;
            if (!fillPattern || fillPatternColor !== color) {
                var tile = document.createElement('canvas'); tile.width = 16; tile.height = 16;
                var patternContext = tile.getContext('2d');
                patternContext.fillStyle = color; patternContext.globalAlpha = .18;
                patternContext.fillRect(0, 0, 16, 16);
                patternContext.globalAlpha = .65; patternContext.strokeStyle = color; patternContext.lineWidth = 1.5;
                patternContext.beginPath();
                patternContext.moveTo(-8, 8); patternContext.lineTo(8, -8);
                patternContext.moveTo(0, 16); patternContext.lineTo(16, 0);
                patternContext.moveTo(8, 24); patternContext.lineTo(24, 8);
                patternContext.stroke();
                fillPattern = ctx.createPattern(tile, 'repeat'); fillPatternColor = color;
            }
            return fillPattern;
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
            if (tool === 'rectangle' || tool === 'circle') {
                var style = shapeFillStyle();
                if (style !== null) { ctx.fillStyle = style; ctx.fill(); }
            }
            ctx.stroke();
        }
        canvas.addEventListener('pointerdown', function (event) {
            if (busy || textPosition || clearMapId !== null || (event.pointerType === 'mouse' && event.button !== 0) || drawing || panStart) return;
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
            if (drawing) { drawing = false; snapshot = null; ctx.globalCompositeOperation = 'source-over'; say('Mapa modificado.'); }
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
                if (action === 'clear') { openClearModal(button); return; }
                if (action === 'export') {
                    if (!background.complete || !background.naturalWidth) { say('Espera a que se cargue el plano para exportar.'); return; }
                    var exported = document.createElement('canvas');
                    exported.width = canvas.width; exported.height = canvas.height;
                    var exportedContext = exported.getContext('2d');
                    exportedContext.drawImage(background, 0, 0, canvas.width, canvas.height);
                    exportedContext.drawImage(canvas, 0, 0);
                    var link = document.createElement('a'); link.download = 'burnout-mapa.png'; link.href = exported.toDataURL('image/png'); link.click(); say('Mapa exportado como PNG.');
                }
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
