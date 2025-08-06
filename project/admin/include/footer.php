<?php
$baseUrl = 'https://inievan.my.id/project/admin';
?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const mainContent = document.getElementById('mainContent');

    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    } else {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
    }
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.querySelector('.navbar-toggler');

    if (window.innerWidth <= 768 &&
        sidebar.classList.contains('show') &&
        !sidebar.contains(event.target) &&
        !toggleBtn.contains(event.target)) {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const mainContent = document.getElementById('mainContent');

    if (window.innerWidth > 768) {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');

        if (sidebar.classList.contains('collapsed')) {
            mainContent.classList.add('expanded');
        } else {
            mainContent.classList.remove('expanded');
        }
    } else {
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('expanded');
    }
});
</script>

</body>

</html>