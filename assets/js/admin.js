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
});

function verificarLogin() {
	const user = document.getElementById('user').value;
	const pass = document.getElementById('pass').value;
	// Usuario y contraseña hardcodeados
	if (user === 'burnout' && pass === 'pruebapiloto') {
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

function abrirPartidas() {
	document.getElementById('galeria').style.display = 'none';
	document.getElementById('partidas').style.display = 'block';
}

function abrirGaleria() {
	document.getElementById('partidas').style.display = 'none';
	document.getElementById('galeria').style.display = 'block';
}

// Parte que controla el google sheet para el control de los usuarios registrados
const sheetUrl = 'https://docs.google.com/spreadsheets/d/1Ly_HTSW0fasWxEJY3HkVKWQ76UShOlq5WZY01Gqw9Ms/gviz/tq?tqx=out:json';

async function fetchData() {
	try {
		const response = await fetch(sheetUrl);
		const text = await response.text();
		const json = JSON.parse(text.substring(47).slice(0, -2)); // Limpiar el formato de JSON de Google Sheets

		const rows = json.table.rows;
		const tableBody = document.getElementById('data-table');
		tableBody.innerHTML = '';

		rows.forEach(row => {
			const nombre = row.c[1]?.v || 'N/A';
			const asistentes = row.c[2]?.v || 'N/A';
			const telefono = row.c[3]?.v || 'N/A';
			const email = row.c[4]?.v || 'N/A';
			const equipo = row.c[5]?.v || 'N/A';

			tableBody.innerHTML += `<tr><td>${nombre}</td><td>${asistentes}</td><td>${telefono}</td><td>${email}</td><td>${equipo}</td></tr>`;
		});
	} catch (error) {
		console.error("Error al obtener los datos:", error);
	}
}

fetchData();