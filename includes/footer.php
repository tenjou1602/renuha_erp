<?php
/**
 * Footer include - Common footer for all pages
 */
?>
        </div>
    </div>
    <style>
        body h1, body .page-header h1, body .exec-dash .exec-head h1 { font-size: 1.75rem !important; }
        body .exec-kpi .lbl, body .exec-fin .k, body .exec-legend, body .exec-activity .when,
        body .exec-table th, body .exec-badge, body .dept-kpi .lbl, body .nav-label, body .badge, body small,
        body .exec-donut .center span, body .help-text {
            font-size: 0.8125rem !important;
        }
        body .exec-table td, body .exec-row, body .exec-attn li, body .exec-activity li, body .exec-qa a {
            font-size: 0.9rem !important;
        }
        body .main-content label, body .form-group label { font-size: 0.875rem !important; font-weight: 600; color: #3f4c5e; }
        body .exec-card h3, body .card h3 { font-size: 1.2rem !important; font-weight: 600; }
        body .exec-fin .v, body .exec-kpi .num { white-space: nowrap !important; overflow: hidden; text-overflow: ellipsis; }
        body .exec-attn .n { font-size: 0.8125rem !important; width: 22px; height: 22px; }
        body .exec-activity .blob { font-size: 0.8125rem !important; }
        [style*="font-size:.6"], [style*="font-size:.7"], [style*="font-size:.8"],
        [style*="font-size:0.6"], [style*="font-size:0.7"], [style*="font-size:0.8"] {
            font-size: 0.8125rem !important;
        }
    </style>
    <script src="<?php echo APP_URL; ?>assets/js/script.js"></script>
    <script>
        // Add menu toggle function if not already defined
        if (typeof toggleSidebar !== 'function') {
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('open');
                }
            }
        }
        
        document.querySelectorAll('input[type="date"]').forEach(function (input) {
            const form = input.closest('form');
            if (input.closest('.report-filters') || (form && (form.getAttribute('method') || 'get').toLowerCase() === 'get')) {
                return;
            }
            const today = new Date();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const todayValue = today.getFullYear() + '-' + month + '-' + day;
            if (!input.min || input.min < todayValue) {
                input.min = input.value && input.value < todayValue ? input.value : todayValue;
            }
            input.addEventListener('change', function () {
                if (input.min && input.value && input.value < input.min) {
                    input.value = input.min;
                }
            });
        });

        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
                if (!sidebar.contains(e.target) && !toggle?.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
    </script>
</body>
</html>