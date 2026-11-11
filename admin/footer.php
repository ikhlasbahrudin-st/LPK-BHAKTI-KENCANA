<!-- Footer -->
        <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500 mt-auto">
            &copy; 2026 LPK Bhakti Kencana Cianjur. All rights reserved.
        </footer>
    </div> <!-- Tutup div wrapper utama dari header -->

    <!-- Script JavaScript Toggle Sidebar -->
<!-- Script JavaScript Pendukung Admin -->
    <script>
        // Fungsi untuk Toggle Sidebar (Mobile)
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        // Fungsi untuk Toggle Dropdown Menu User (Wajib ada agar dropdown bisa diklik)
        function toggleUserDropdown() {
            const submenu = document.getElementById('userSubmenu');
            const arrow = document.getElementById('dropdownArrow');
            
            submenu.classList.toggle('hidden');
            arrow.classList.toggle('rotate-180');
        }
    </script>
</body>
</html>
</body>
</html>