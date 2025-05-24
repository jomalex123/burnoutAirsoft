let attempts = 0;
let codigo = 0;

function goFullscreen() {
  const elem = document.documentElement;

  if (elem.requestFullscreen) {
    elem.requestFullscreen();
  } else if (elem.webkitRequestFullscreen) { /* Safari */
    elem.webkitRequestFullscreen();
  } else if (elem.msRequestFullscreen) { /* IE11 */
    elem.msRequestFullscreen();
  }
}


document.addEventListener("keydown", function (e) {
const blockedKeys = ["Escape", "F1", "F1", "F2", "F3", "F4", "F5", "F6", "F7", "F8", "F9", "F10", "F12"];

if (blockedKeys.includes(e.key)) {
  e.preventDefault();  // Evita la acción por defecto
  return false;
}

// Algunas teclas requieren keyCode adicionalmente por compatibilidad
if (e.keyCode === 111 || e.keyCode === 112 || e.keyCode === 113 || e.keyCode === 114  || e.keyCode === 115  || e.keyCode === 116 || e.keyCode === 117 || e.keyCode === 118 || e.keyCode === 119 || e.keyCode === 120 || e.keyCode === 121 || e.keyCode === 123 || e.keyCode === 27) {
  e.preventDefault();
  return false;
}


if(e.keyCode === 102)
  { codigo += e.keyCode;
    if(codigo>500)
      goFullscreen();   
  }
});

function abrirCmd() {
  document.getElementById('cmdWindow').style.display = 'block';
}

function abrirMiPC() {
  
}

 function abrirVentana(id) {
  const ventana = document.getElementById(id);
  if(ventana.style.display != 'block')
  {
    ventana.style.display = 'block';
    ventana.classList.remove('abrir98'); // Reinicia si ya se aplicó antes
    void ventana.offsetWidth; // Forzar reflow para reiniciar animación
    ventana.classList.add('abrir98');
  }
}

function cerrarVentana(id) {
  document.getElementById(id).style.display = 'none';
}

function verificarLogin() {
  const pass = document.getElementById("clave").value.trim();
  const user = document.getElementById("usuario").value.trim();
  console.log(user);
  console.log(pass);

  if (user === "burnout" && pass === "pruebapiloto") {
    document.getElementById("pantallaLogin").style.display = "none";
  } else {
    document.getElementById("loginError").textContent = "Contraseña incorrecta.";
  }
}

function verificarCodigo() {
  const codigoCorrecto = "1234";
  const input = document.getElementById("codigoInput").value.trim();
  const error = document.getElementById("error");

  if (input === codigoCorrecto) {    
    abrirVentana('mapaWindow');
    document.getElementById("codigoInput").value = '';
  } else {    
    attempts++;
    if (attempts >= 3) {
      triggerDestructionProtocol();
    }
    error.textContent = `>> ERROR: Código inválido. Intento ${attempts}/3`;
    document.getElementById("codigoInput").value = ''
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

const letters = "0 01 00";
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