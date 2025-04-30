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

function verificarCodigo() {
  const codigoCorrecto = "1234";
  const input = document.getElementById("codigoInput").value.trim();
  const error = document.getElementById("error");

  if (input === codigoCorrecto) {    
    abrirVentana('mapaWindow');
    document.getElementById("codigoInput").value = '';
  } else {
    error.textContent = ">> ERROR: Código inválido.";
  }
}

function verificarLogin() {
  const pass = document.getElementById("loginPass").value.trim();
  const user = "burnout"; // nombre fijo como en Windows

  if (pass === "pruebapiloto") {
    document.getElementById("pantallaLogin").style.display = "none";
  } else {
    document.getElementById("loginError").textContent = "Contraseña incorrecta.";
  }
}