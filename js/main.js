
// Элементы меню
const menuToggle = document.getElementById('menuToggle');
const menuOverlay = document.getElementById('menuOverlay');
const menuSidebar = document.getElementById('menuSidebar');
const menuClose = document.getElementById('menuClose');

// Открытие меню
menuToggle.addEventListener('click', () => {
    menuSidebar.classList.add('active');
    menuOverlay.classList.add('active');
    menuToggle.classList.add('active');
    document.body.style.overflow = 'hidden';
});

// Закрытие меню
const closeMenu = () => {
    menuSidebar.classList.remove('active');
    menuOverlay.classList.remove('active');
    menuToggle.classList.remove('active');
    document.body.style.overflow = 'auto';
};

// Закрытие по кнопке в меню
menuClose.addEventListener('click', closeMenu);

// Закрытие по клику на оверлей
menuOverlay.addEventListener('click', closeMenu);

// Закрытие по клавише Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeMenu();
    }
});

// Активация пунктов меню
document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', function() {
        document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        closeMenu();
    });
});