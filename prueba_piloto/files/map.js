document.querySelectorAll('.point').forEach(point => {
	point.addEventListener('mouseover', function() {
		showPopup(this.dataset.message, this);
	});
	point.addEventListener('click', function() {
		showPopup(this.dataset.message, this);
	});
});

function showPopup(message, element) {
	let popup = document.createElement('div');
	popup.style.position = 'absolute';
	popup.style.backgroundColor = 'white';
	popup.style.border = '1px solid black';
	popup.style.padding = '10px';
	popup.style.top = element.style.top;
	popup.style.left = element.style.left;
	popup.innerText = message;
	document.body.appendChild(popup);
	setTimeout(() => {
		popup.remove();
	}, 3000); // El popup desaparecerá después de 3 segundos
}