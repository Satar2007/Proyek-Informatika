document.addEventListener('DOMContentLoaded', () => {
    const links = document.querySelectorAll('.nav a[href^="#"]');
    links.forEach(link => {
        link.addEventListener('click', () => {
            links.forEach(x => x.classList.remove('active'));
            link.classList.add('active');
        });
    });
});