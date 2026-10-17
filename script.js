// script.js
const slider = document.getElementById('promoSlider');
const dots = document.querySelectorAll('#sliderDots .dot');

// Variables para controlar el arrastre con el mouse
let isDown = false;
let startX;
let scrollLeft;

slider.addEventListener('mousedown', (e) => {
    isDown = true;
    slider.style.cursor = 'grabbing';
    startX = e.pageX - slider.offsetLeft;
    scrollLeft = slider.scrollLeft;
});

slider.addEventListener('mouseleave', () => {
    isDown = false;
    slider.style.cursor = 'grab';
});

slider.addEventListener('mouseup', () => {
    isDown = false;
    slider.style.cursor = 'grab';
});

slider.addEventListener('mousemove', (e) => {
    if (!isDown) return;
    e.preventDefault();
    const x = e.pageX - slider.offsetLeft;
    const walk = (x - startX) * 2; // Velocidad de desplazamiento
    slider.scrollLeft = scrollLeft - walk;
});

// Actualizar los puntitos (dots) al deslizar
slider.addEventListener('scroll', () => {
    const index = Math.round(slider.scrollLeft / slider.clientWidth);
    dots.forEach((dot, i) => {
        dot.classList.toggle('active', i === index);
    });
});