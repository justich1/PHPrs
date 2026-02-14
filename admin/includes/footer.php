    </main>
</div>
<div class="footer">
Created by PC-pohotovost
</div>

<!-- MODÁLNÍ OKNO PRO NASTAVENÍ PLUGINŮ -->
<div id="plugin-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 3000; padding: 40px;">
    <div style="background: white; width: 100%; height: 100%; display: flex; flex-direction: column; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
        <div style="padding: 10px 15px; background: #f1f1f1; border-bottom: 1px solid #ccc; text-align: right;">
            <button id="plugin-modal-close" style="background: #dc3545; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; font-weight: bold; cursor: pointer;">&times;</button>
        </div>
        <iframe id="plugin-modal-iframe" style="flex-grow: 1; border: none;"></iframe>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        sidebar.classList.toggle('show');
    }

    // Zavření kliknutím mimo sidebar (volitelné)
    document.addEventListener('click', function (e) {
        const sidebar = document.getElementById('adminSidebar');
        const hamburger = document.querySelector('.hamburger');
        if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });
</script>
</body>
</html>
