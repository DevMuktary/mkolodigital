document.addEventListener('DOMContentLoaded', () => {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const userMenu = document.getElementById('userMenu');
    const userMenuDropdown = document.getElementById('userMenuDropdown');

    // Toggle sidebar for mobile
    if (mobileMenuBtn && sidebar) {
        mobileMenuBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            sidebar.classList.toggle('active');
        });
    }

    // Toggle user menu dropdown
    if (userMenu && userMenuDropdown) {
        userMenu.addEventListener('click', (event) => {
            event.stopPropagation();
            userMenuDropdown.classList.toggle('active');
        });
    }
    
    // Close menus if clicked outside
    document.addEventListener('click', (event) => {
        if (userMenuDropdown && userMenuDropdown.classList.contains('active') && !userMenu.contains(event.target)) {
            userMenuDropdown.classList.remove('active');
        }
        if (sidebar && sidebar.classList.contains('active') && !sidebar.contains(event.target) && !event.target.closest('#mobile-menu-btn')) {
            sidebar.classList.remove('active');
        }
    });
});
