(function () {
    'use strict';
    window.initAdminMapaInteractivoPage = function () {
        var root = document.querySelector('.admin-map-page');
        if (!root || root.dataset.mapReady || !root.querySelector('canvas')) return;
        root.dataset.mapReady = 'true';
        var canvas = root.querySelector('canvas'), ctx = canvas.getContext('2d');
        var background = root.querySelector('.admin-map-background');
        var tool = 'pen', drawing = false, start, snapshot, history = [], future = [], busy = false;
        var status = root.querySelector('[data-map-status]');
        var mapPanel = root.querySelector('[data-map-panel="mapa"]');
        var fullscreenButton = root.querySelector('[data-map-action="fullscreen"]');
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
                if (active) root.querySelector('[data-map-maintenance-title]').textContent = button.textContent;
            });
            root.querySelector('[data-map-panel="mapa"]').hidden = name !== 'mapa';
            root.querySelector('[data-map-panel="maintenance"]').hidden = name === 'mapa';
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
            if (busy || (event.pointerType === 'mouse' && event.button !== 0) || drawing) return;
            if (tool === 'text') {
                event.preventDefault();
                var position = point(event);
                var text = window.prompt('Escribe el texto que quieres añadir al mapa:');
                if (text === null || text.trim() === '') return;
                remember();
                ctx.globalCompositeOperation = 'source-over';
                ctx.fillStyle = root.querySelector('[data-map-color]').value;
                ctx.font = Math.max(16, Number(root.querySelector('[data-map-size]').value) * 4) + 'px Yantramanav, sans-serif';
                ctx.textBaseline = 'top';
                ctx.fillText(text, position.x, position.y);
                say('Texto añadido.');
                return;
            }
            event.preventDefault(); remember(); snapshot = ctx.getImageData(0, 0, canvas.width, canvas.height);
            drawing = true; start = point(event); canvas.setPointerCapture(event.pointerId);
            ctx.beginPath(); ctx.moveTo(start.x, start.y); stroke({ x: start.x + .01, y: start.y });
        });
        canvas.addEventListener('pointermove', function (event) { if (drawing) stroke(point(event)); });
        function finish() { if (drawing) { drawing = false; snapshot = null; ctx.globalCompositeOperation = 'source-over'; say('Mapa modificado.'); } }
        canvas.addEventListener('pointerup', finish);
        canvas.addEventListener('pointercancel', finish);
        canvas.addEventListener('lostpointercapture', finish);
        root.querySelectorAll('[data-map-section]').forEach(function (button) { button.addEventListener('click', function () { section(button.dataset.mapSection); }); });
        root.querySelectorAll('[data-map-tool]').forEach(function (button) {
            button.addEventListener('click', function () {
                tool = button.dataset.mapTool;

                root.querySelectorAll('[data-map-tool]').forEach(function (item) { var active = item === button; item.classList.toggle('is-active', active); item.setAttribute('aria-pressed', String(active)); });
            });
        });
        root.querySelector('[data-map-size]').addEventListener('input', function (event) { root.querySelector('[data-map-size-label]').textContent = event.target.value + ' px'; });
        root.querySelectorAll('[data-map-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.dataset.mapAction;
                if (busy || drawing) return;
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
