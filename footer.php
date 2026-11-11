</div> <!-- Tutup flex h-screen dari header.php -->

    <!-- JavaScript Global untuk Toggle Sidebar / Hamburger -->
    <script>
        const btnToggle = document.getElementById('hamburgerToggle');
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const btnClose = document.getElementById('closeSidebar');

        function toggleMenu() {
            if (sidebar) sidebar.classList.toggle('-translate-x-full');
            if (overlay) overlay.classList.toggle('hidden');
        }

        if (btnToggle) btnToggle.addEventListener('click', toggleMenu);
        if (btnClose) btnClose.addEventListener('click', toggleMenu);
        if (overlay) overlay.addEventListener('click', toggleMenu);
    </script>
</body>
</html>