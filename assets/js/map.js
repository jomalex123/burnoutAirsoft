(function () {
    'use strict';
    var instances = new Set();
    function createEditor(root, listen) {
        var canvas = root.querySelector('canvas'), ctx = canvas.getContext('2d');
        var background = root.querySelector('.admin-map-background');
        var tool = 'pen', drawing = false, start, snapshot, history = [], future = [], busy = false;
        var status = root.querySelector('[data-map-status]');
        var mapPanel = root;
        var viewport = root.querySelector('.admin-map-viewport');
        var stage = root.querySelector('.admin-map-canvas-stage');
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
        var annotationVersion = 0;
        busy = true;
        root.hqMap = {
            destroy: destroyEditor,
            exportAnnotations: function () { return canvas.toDataURL('image/png'); },
            setAnnotations: async function (data) {
                var version = ++annotationVersion;
                busy = true; buttons();
                try {
                    var loaded = null;
                    if (data) loaded = await new Promise(function (resolve, reject) {
                        var img = new Image(); img.onload = function () { resolve(img); };
                        img.onerror = function () { reject(new Error('No se ha podido cargar el dibujo.')); }; img.src = data;
                    });
                    if (version !== annotationVersion || !root.isConnected) return;
                    blank(); if (loaded) ctx.drawImage(loaded, 0, 0, canvas.width, canvas.height);
                    history = []; future = []; mapId = 'mission';
                } finally { if (version === annotationVersion) { busy = false; buttons(); } }
            },
            closePopups: function () { closeTextModal(); closeClearModal(false); closeFillMenu(false); },
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
        function destroyEditor() {
            annotationVersion++;
            localMaps.clear();
            closeClearModal(false);
            if (root.hqFillCleanup) root.hqFillCleanup();
        }
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
            if (!document.querySelector || !document.querySelector('.burnout-popup.is-open')) document.body.classList.remove('burnout-popup-open');
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
                listen(button, 'click', function () { closeClearModal(true); });
            });
            listen(clearModal, 'keydown', function (event) {
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeClearModal(true); return; }
                if (event.key !== 'Tab') return;
                var controls = Array.from(clearModal.querySelectorAll('button:not(:disabled)'));
                var first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            });
            listen(clearModal.querySelector('[data-map-clear-confirm]'), 'click', function () {
                var allowed = clearMapId !== null && clearMapId === mapId && !busy && !drawing && !panStart;
                closeClearModal(true);
                if (!allowed) return;
                remember(); blank(); say('Anotaciones eliminadas.'); buttons();
            });
        }
        textModal.querySelectorAll('[data-map-text-close]').forEach(function (button) {
            listen(button, 'click', closeTextModal);
        });
        listen(textModal, 'keydown', function (event) {
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeTextModal(); }
            if (event.key === 'Tab') {
                var first = textModal.querySelector('button'), last = textModal.querySelector('[type="submit"]');
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
        listen(root.querySelector('[data-map-text-form]'), 'submit', function (event) {
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
            listen(root.querySelector('[data-map-color]'), 'input', syncFillColor);
            listen(fillToggle, 'click', function () { if (fillMenu.hidden) openFillMenu(); else closeFillMenu(true); });
            listen(fillToggle, 'keydown', function (event) {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); openFillMenu(); }
            });
            listen(fillMenu, 'click', function (event) {
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
            listen(fillMenu, 'keydown', function (event) {
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
            listen(document, 'pointerdown', fillOutside);
            listen(root.querySelector('.admin-map-toolbar'), 'scroll', function () { closeFillMenu(false); });
            root.hqFillCleanup = function () { document.removeEventListener('pointerdown', fillOutside); };
        }
        listen(root, 'fullscreenchange', function () { closeFillMenu(false); });
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
        listen(canvas, 'pointerdown', function (event) {
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
        listen(canvas, 'pointermove', function (event) {
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
        listen(canvas, 'pointerup', finish);
        listen(canvas, 'pointercancel', finish);
        listen(canvas, 'lostpointercapture', finish);
        root.querySelectorAll('[data-map-tool]').forEach(function (button) {
            listen(button, 'click', function () {
                if (drawing || panStart) return;
                tool = button.dataset.mapTool;
                canvas.classList.toggle('is-pan-tool', tool === 'pan');

                root.querySelectorAll('[data-map-tool]').forEach(function (item) { var active = item === button; item.classList.toggle('is-active', active); item.setAttribute('aria-pressed', String(active)); });
            });
        });
        listen(root.querySelector('[data-map-size]'), 'input', function (event) { root.querySelector('[data-map-size-label]').textContent = event.target.value + ' px'; });
        root.querySelectorAll('[data-map-action]').forEach(function (button) {
            listen(button, 'click', function () {
                var action = button.dataset.mapAction;
                if (busy || drawing || panStart) return;
                if (action === 'zoom-in' || action === 'zoom-out' || action === 'fullscreen') return;
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
        return root.hqMap;
    }
    window.burnoutCreateMap = function (root) {
        if (root.burnoutMap) return root.burnoutMap;
        var listeners = [], editor = null, destroyed = false, overlayVersion = 0;
        function listen(element, type, handler) {
            element.addEventListener(type, handler);
            listeners.push([element, type, handler]);
        }
        var viewport = root.querySelector('.admin-map-viewport');
        var stage = root.querySelector('.admin-map-canvas-stage');
        var image = root.querySelector('.admin-map-background');
        var fullscreen = root.querySelector('[data-map-action="fullscreen"]');
        var zoomIn = root.querySelector('[data-map-action="zoom-in"]');
        var zoomOut = root.querySelector('[data-map-action="zoom-out"]');
        var message = root.querySelector('.admin-map-load-error');
        var zoom = 1;
        function changeZoom(delta) {
            var x = stage.offsetWidth ? (viewport.scrollLeft + viewport.clientWidth / 2) / stage.offsetWidth : .5;
            var y = stage.offsetHeight ? (viewport.scrollTop + viewport.clientHeight / 2) / stage.offsetHeight : .5;
            zoom = Math.max(1, Math.min(3, Math.round((zoom + delta) * 100) / 100));
            stage.style.width = (zoom * 100) + '%';
            viewport.scrollLeft = x * stage.offsetWidth - viewport.clientWidth / 2;
            viewport.scrollTop = y * stage.offsetHeight - viewport.clientHeight / 2;
            zoomIn.disabled = zoom >= 3; zoomOut.disabled = zoom <= 1;
        }
        function syncFullscreen() {
            var active = document.fullscreenElement === root;
            var label = active ? 'Salir de pantalla completa' : 'Pantalla completa';
            fullscreen.title = label; fullscreen.setAttribute('aria-label', label);
            fullscreen.setAttribute('aria-pressed', String(active));
            fullscreen.querySelector('[data-map-fullscreen-icon]').setAttribute('d', active ? 'M3 8h5V3M16 3v5h5M21 16h-5v5M8 21v-5H3' : 'M8 3H3v5M16 3h5v5M21 16v5h-5M8 21H3v-5');
        }
        async function toggleFullscreen() {
            try {
                if (document.fullscreenElement === root) await document.exitFullscreen();
                else await root.requestFullscreen();
                syncFullscreen();
            } catch (error) {
                message.textContent = 'No se ha podido cambiar a pantalla completa.'; message.hidden = false;
            }
        }
        function imageState() {
            message.textContent = 'No se ha podido cargar el plano del campo. Recarga la página para volver a intentarlo.';
            message.hidden = Boolean(image.naturalWidth);
        }
        fullscreen.disabled = !document.fullscreenEnabled || !root.requestFullscreen;
        listen(root, 'fullscreenchange', syncFullscreen);
        listen(root, 'click', function (event) {
            var button = event.target.closest('[data-map-action]');
            if (!button || button.disabled) return;
            var action = button.dataset.mapAction;
            if (action === 'zoom-in' || action === 'zoom-out') changeZoom(action === 'zoom-in' ? .25 : -.25);
            else if (action === 'fullscreen') return toggleFullscreen();
        });
        listen(image, 'load', imageState); listen(image, 'error', imageState);
        if (image.complete) imageState();
        syncFullscreen();
        var api = {
            root: root, readonly: root.dataset.mapMode !== 'edit',
            changeZoom: changeZoom,
            exportAnnotations: function () { return editor ? editor.exportAnnotations() : null; },
            setAnnotations: async function (data) {
                if (data && !/^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(data)) throw new Error('El dibujo no es válido.');
                if (editor) return editor.setAnnotations(data);
                var version = ++overlayVersion;
                var overlay = root.querySelector('[data-map-overlay]'); overlay.replaceChildren();
                if (data) {
                    var img = new Image(); img.alt = '';
                    await new Promise(function (resolve, reject) { img.onload = resolve; img.onerror = function () { reject(new Error('No se ha podido cargar el dibujo.')); }; img.src = data; });
                    if (!destroyed && version === overlayVersion) overlay.append(img);
                }
            },
            flush: async function () { return editor ? editor.flush() : true; },
            load: async function (id) { if (editor) await editor.load(id); },
            closePopups: function () { if (editor) editor.closePopups(); },
            destroy: function () {
                if (destroyed) return;
                destroyed = true;
                if (editor) editor.destroy();
                listeners.forEach(function (entry) { entry[0].removeEventListener(entry[1], entry[2]); });
                listeners.length = 0; instances.delete(api);
                delete root.burnoutMap; delete root.hqMap; delete root.hqFillCleanup;
            }
        };
        if (!api.readonly) editor = createEditor(root, listen);
        root.burnoutMap = api; instances.add(api);
        return api;
    };
    window.initBurnoutMapsPage = function () {
        window.initBurnoutMapsPage.cleanup();
        document.querySelectorAll('[data-burnout-map]').forEach(function (root) { window.burnoutCreateMap(root); });
    };
    window.initBurnoutMapsPage.cleanup = function () {
        instances.forEach(function (api) { if (!api.root.isConnected) api.destroy(); });
    };
    if (!window.__burnoutLoadingPageScript) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window.initBurnoutMapsPage);
        else window.initBurnoutMapsPage();
    }
})();
