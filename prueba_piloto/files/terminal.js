let attempts = 0;
const correctCode = "ALFA123";

function validateCode() {
	const input = document.getElementById("codeInput").value;
	const status = document.getElementById("status");
	if (input === correctCode) {
		status.textContent = "Código aceptado. Acceso concedido.";
	} else {
		attempts++;
		status.textContent = `Código incorrecto. Intento ${attempts}/3`;
		if (attempts >= 3) {
			triggerDestructionProtocol();
		}
	}
}

function triggerDestructionProtocol() {
	document.body.innerHTML = ""; // limpia todo
	setTimeout(() => {
		showErrorMessage();
	}, 1000);
}

function showErrorMessage() {
	document.body.innerHTML = `
    <div id="error-message">ACCESO DENEGADO</div>
    <div id="countdown"></div>
    <div id="alert-message" class="hidden">ACTIVANDO PROTOCOLO DE DESTRUCCIÓN</div>
    <canvas id="matrix-canvas" class="hidden"></canvas>
    `;
	startCountdown();
}

function startCountdown() {
	const countdownEl = document.getElementById("countdown");
	let seconds = 5;
	const interval = setInterval(() => {
		countdownEl.textContent = `Autodestrucción en ${seconds}...`;
		seconds--;
		if (seconds < 0) {
			clearInterval(interval);
			showAlertMessage();
		}
	}, 1000);
}

function showAlertMessage() {
	document.getElementById("countdown").classList.add("hidden");
	document.getElementById("alert-message").classList.remove("hidden");
	setTimeout(() => {
		startMatrixEffect();
	}, 5000); // después de 5 segundos más
}

function startMatrixEffect() {
	document.getElementById("alert-message").classList.add("hidden");
	const canvas = document.getElementById("matrix-canvas");
	canvas.classList.remove("hidden");
	const ctx = canvas.getContext("2d");
	canvas.width = window.innerWidth;
	canvas.height = window.innerHeight;
	const letters = "01";
	const fontSize = 14;
	const columns = canvas.width / fontSize;
	const drops = Array(Math.floor(columns)).fill(1);

	function draw() {
		ctx.fillStyle = "rgba(0, 0, 0, 0.05)";
		ctx.fillRect(0, 0, canvas.width, canvas.height);
		ctx.fillStyle = "#0F0";
		ctx.font = fontSize + "px monospace";
		for (let i = 0; i < drops.length; i++) {
			const text = letters.charAt(Math.floor(Math.random() * letters.length));
			ctx.fillText(text, i * fontSize, drops[i] * fontSize);
			if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
				drops[i] = 0;
			}
			drops[i]++;
		}
	}
	const interval = setInterval(draw, 33);
	setTimeout(() => {
		clearInterval(interval);
		document.body.innerHTML = "";
		document.body.style.background = "black";
	}, 10000); // Efecto Matrix durante 10 segundos
}