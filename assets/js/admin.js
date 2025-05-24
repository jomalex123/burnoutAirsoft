const registros = JSON.parse(localStorage.getItem('registrosAirsoft') || '[]');
const tbody = document.querySelector('#tablaInscritos tbody');
registros.forEach(persona => {
  const fila = document.createElement('tr');
  fila.innerHTML = 
  `<td>${persona.correo}</td>
  <td>${persona.nombre}</td>
  <td>${persona.apellidos}</td>
  <td>${persona.dni}</td>
  <td>${persona.telefono}</td>`;
  tbody.appendChild(fila);
});
function verificarLogin() {
  const user = document.getElementById('user').value;
  const pass = document.getElementById('pass').value;
  // Usuario y contraseña hardcodeados
  if (user === 'burnoutairsoft' && pass === 'elsergioesmastontoqueeljoma') {
    document.getElementById('login-container').style.display = 'none';
    document.getElementById('main').style.display = 'block';
  } else {
    alert('Usuario o contraseña incorrectos');
  }
}

function clearLogin() {
  document.getElementById('user').value = '';
  document.getElementById('pass').value = '';
}