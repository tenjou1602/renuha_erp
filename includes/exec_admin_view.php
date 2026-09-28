<?php
$total_projects = (int) ($stats['total_projects'] ?? 0);
$active_projects = (int) ($stats['ongoing_projects'] ?? 0);
$completed_projects = (int) ($stats['completed_projects'] ?? 0);
$pending_projects = (int) ($stats['pending_projects'] ?? 0);
$delayed_projects = (int) ($stats['delayed_projects'] ?? 0);
$status_counts = [
    'Completed' => $completed_projects,
    'Active' => $active_projects,
    'Pending' => $pending_projects,
    'Delayed' => $delayed_projects,
];
$status_colors = ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444'];
$chart_total = array_sum($status_counts);
$activity_limit = array_slice($recent_department_activity ?? [], 0, 8);
$dept_dot = [
    'engineering' => '#8b5cf6',
    'accounting' => '#10b981',
    'procurement' => '#3b82f6',
    'warehouse' => '#06b6d4',
    'admin' => '#64748b',
];
?>
<style>
.exec-dash { font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif; color: #0f172a; }
.exec-dash .exec-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1.25rem; }
.exec-dash .exec-head h1 { font-size:1.55rem; font-weight:800; letter-spacing:-.03em; margin:0; }
.exec-dash .exec-head p { color:#64748b; margin:.25rem 0 0; font-size:.9rem; }
.exec-dash .exec-date { color:#94a3b8; font-size:.85rem; font-weight:600; white-space:nowrap; }
.exec-kpis { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:.9rem; margin-bottom:1rem; }
.exec-kpi { background:#fff; border-radius:16px; padding:1rem 1.1rem; box-shadow:0 8px 24px rgba(15,23,42,.04); display:flex; align-items:center; gap:.85rem; border:1px solid #eef2f7; }
.exec-kpi .ico { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
.exec-kpi .num { font-size:1.55rem; font-weight:800; line-height:1; }
.exec-kpi .lbl { font-size:.72rem; color:#64748b; font-weight:600; margin-top:.2rem; }
.exec-kpi.blue .ico { background:#dbeafe; color:#2563eb; }
.exec-kpi.green .ico { background:#d1fae5; color:#059669; }
.exec-kpi.purple .ico { background:#ede9fe; color:#7c3aed; }
.exec-kpi.orange .ico { background:#ffedd5; color:#ea580c; }
.exec-kpi.red .ico { background:#fee2e2; color:#dc2626; }
.exec-mid { display:grid; grid-template-columns:1.2fr 1fr 1fr 1.05fr; gap:.9rem; margin-bottom:1rem; }
.exec-card { background:#fff; border-radius:16px; padding:1.05rem 1.15rem; box-shadow:0 8px 24px rgba(15,23,42,.04); border:1px solid #eef2f7; }
.exec-card h3 { font-size:.92rem; font-weight:700; margin:0 0 .85rem; display:flex; align-items:center; gap:.45rem; }
.exec-card h3 i { color:#64748b; }
.exec-fin { display:grid; grid-template-columns:1fr 1fr; gap:.55rem; }
.exec-fin div { border-radius:12px; padding:.7rem .75rem; }
.exec-fin .k { font-size:.68rem; color:#64748b; font-weight:600; }
.exec-fin .v { font-size:.95rem; font-weight:800; margin-top:.15rem; overflow-wrap:anywhere; word-break:break-word; line-height:1.25; }
.exec-fin .c1 { background:#eff6ff; } .exec-fin .c1 .v { color:#2563eb; }
.exec-fin .c2 { background:#ecfdf5; } .exec-fin .c2 .v { color:#059669; }
.exec-fin .c3 { background:#fff7ed; } .exec-fin .c3 .v { color:#ea580c; }
.exec-fin .c4 { background:#f5f3ff; } .exec-fin .c4 .v { color:#7c3aed; }
.exec-rows { display:flex; flex-direction:column; gap:.55rem; }
.exec-row { display:flex; justify-content:space-between; align-items:center; font-size:.8rem; color:#475569; }
.exec-row strong { color:#0f172a; font-size:.95rem; }
.exec-dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:.45rem; }
.exec-attn { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:.55rem; }
.exec-attn li { display:flex; gap:.55rem; align-items:flex-start; font-size:.78rem; color:#475569; }
.exec-attn .n { width:18px; height:18px; border-radius:50%; background:#fee2e2; color:#dc2626; font-size:.65rem; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
.exec-attn strong { color:#0f172a; }
.exec-charts { display:grid; grid-template-columns:1.05fr 1.35fr 1.15fr; gap:.9rem; margin-bottom:1rem; align-items:stretch; }
.exec-cost-wrap { position:relative; height:180px; max-height:180px; overflow:hidden; }
.exec-cost-wrap canvas { display:block !important; max-height:180px !important; }
.exec-donut { display:flex; align-items:center; gap:1rem; }
.exec-donut .chart-box { position:relative; width:150px; height:150px; flex-shrink:0; }
.exec-donut .center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; pointer-events:none; }
.exec-donut .center b { font-size:1.4rem; font-weight:800; line-height:1; }
.exec-donut .center span { font-size:.65rem; color:#94a3b8; }
.exec-legend { display:flex; flex-direction:column; gap:.4rem; font-size:.78rem; color:#475569; }
.exec-legend i { width:9px; height:9px; border-radius:50%; display:inline-block; margin-right:.4rem; }
.exec-activity { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:.7rem; max-height:220px; overflow:auto; }
.exec-activity li { display:flex; gap:.65rem; align-items:flex-start; font-size:.78rem; }
.exec-activity .blob { width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:.7rem; flex-shrink:0; }
.exec-activity .when { color:#94a3b8; font-size:.68rem; }
.exec-bottom { display:grid; grid-template-columns:1.7fr .9fr; gap:.9rem; }
.exec-table { width:100%; border-collapse:collapse; font-size:.78rem; }
.exec-table th { text-align:left; color:#94a3b8; font-weight:600; padding:.45rem .4rem; border-bottom:1px solid #f1f5f9; }
.exec-table td { padding:.7rem .4rem; border-bottom:1px solid #f8fafc; vertical-align:middle; }
.exec-badge { display:inline-flex; align-items:center; padding:.15rem .5rem; border-radius:999px; font-size:.68rem; font-weight:700; }
.exec-badge.active, .exec-badge.ongoing { background:#dbeafe; color:#1d4ed8; }
.exec-badge.completed { background:#d1fae5; color:#047857; }
.exec-badge.planning, .exec-badge.pending { background:#f1f5f9; color:#475569; }
.exec-badge.on_hold { background:#ffedd5; color:#c2410c; }
.exec-pbar { display:flex; align-items:center; gap:.4rem; }
.exec-pbar .track { flex:1; height:6px; background:#e2e8f0; border-radius:99px; overflow:hidden; min-width:70px; }
.exec-pbar .fill { display:block; height:100%; background:#3b82f6; border-radius:99px; }
.exec-pbar .fill.done { background:#22c55e; }
.exec-pbar em { font-style:normal; font-weight:700; color:#64748b; width:2.2rem; }
.exec-qa { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; }
.exec-qa a { display:flex; flex-direction:column; align-items:flex-start; gap:.45rem; padding:.85rem .8rem; border-radius:14px; font-size:.75rem; font-weight:700; color:#0f172a; border:1px solid transparent; }
.exec-qa a i { font-size:1rem; }
.exec-qa .t1 { background:#eff6ff; } .exec-qa .t1 i { color:#2563eb; }
.exec-qa .t2 { background:#ecfdf5; } .exec-qa .t2 i { color:#059669; }
.exec-qa .t3 { background:#f5f3ff; } .exec-qa .t3 i { color:#7c3aed; }
.exec-qa .t4 { background:#fff7ed; } .exec-qa .t4 i { color:#ea580c; }
.exec-qa .t5 { background:#e0f2fe; } .exec-qa .t5 i { color:#0284c7; }
.exec-qa .t6 { background:#f1f5f9; } .exec-qa .t6 i { color:#475569; }
.exec-qa a:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(15,23,42,.06); }
@media (max-width: 1200px) {
  .exec-kpis { grid-template-columns:repeat(3,minmax(0,1fr)); }
  .exec-mid, .exec-charts, .exec-bottom { grid-template-columns:1fr 1fr; }
}
@media (max-width: 800px) {
  .exec-kpis, .exec-mid, .exec-charts, .exec-bottom, .exec-qa { grid-template-columns:1fr; }
}
</style>

<div class="exec-dash">
    <div class="exec-head">
        <div>
            <h1>Executive Admin Dashboard</h1>
            <p>Welcome back, here's your project management at a glance.</p>
        </div>
        <div class="exec-date"><?php echo date('F d, Y'); ?></div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="exec-kpis">
        <div class="exec-kpi blue">
            <div class="ico"><i class="fas fa-folder"></i></div>
            <div><div class="num"><?php echo number_format($total_projects); ?></div><div class="lbl">Total Projects</div></div>
        </div>
        <div class="exec-kpi green">
            <div class="ico"><i class="fas fa-check-circle"></i></div>
            <div><div class="num"><?php echo number_format($active_projects); ?></div><div class="lbl">Active Projects</div></div>
        </div>
        <div class="exec-kpi purple">
            <div class="ico"><i class="fas fa-clipboard-check"></i></div>
            <div><div class="num"><?php echo number_format($completed_projects); ?></div><div class="lbl">Completed Projects</div></div>
        </div>
        <div class="exec-kpi orange">
            <div class="ico"><i class="fas fa-clock"></i></div>
            <div><div class="num"><?php echo number_format($pending_projects); ?></div><div class="lbl">Pending Projects</div></div>
        </div>
        <div class="exec-kpi red">
            <div class="ico"><i class="fas fa-exclamation-triangle"></i></div>
            <div><div class="num"><?php echo number_format($delayed_projects); ?></div><div class="lbl">Delayed Projects</div></div>
        </div>
    </div>

    <div class="exec-mid">
        <div class="exec-card">
            <h3><i class="fas fa-coins"></i> Financial Overview</h3>
            <div class="exec-fin">
                <div class="c1"><div class="k">Total Contract Value</div><div class="v">₱<?php echo number_format((float)($stats['contract_value'] ?? 0), 0); ?></div></div>
                <div class="c2"><div class="k">Total Collected</div><div class="v">₱<?php echo number_format((float)($stats['collected'] ?? 0), 0); ?></div></div>
                <div class="c3"><div class="k">Total Expenses</div><div class="v">₱<?php echo number_format((float)($stats['total_project_expenses'] ?? 0), 0); ?></div></div>
                <div class="c4"><div class="k">Available Funds</div><div class="v">₱<?php echo number_format((float)($stats['available_funds'] ?? 0), 0); ?></div></div>
            </div>
        </div>
        <div class="exec-card">
            <h3><i class="fas fa-shopping-cart"></i> Procurement Overview</h3>
            <div class="exec-rows">
                <div class="exec-row"><span><span class="exec-dot" style="background:#f59e0b"></span>Pending Material Requests</span><strong><?php echo number_format((int)($items_needing_attention['pending_material_requests'] ?? 0)); ?></strong></div>
                <div class="exec-row"><span><span class="exec-dot" style="background:#3b82f6"></span>Pending Purchases</span><strong><?php echo number_format((int)($stats['pending_purchases'] ?? 0)); ?></strong></div>
                <div class="exec-row"><span><span class="exec-dot" style="background:#8b5cf6"></span>Ongoing Purchases</span><strong><?php echo number_format((int)($stats['pending_pos'] ?? 0)); ?></strong></div>
                <div class="exec-row"><span><span class="exec-dot" style="background:#22c55e"></span>Completed Purchases</span><strong><?php echo number_format((int)($stats['completed_purchases'] ?? 0)); ?></strong></div>
            </div>
        </div>
        <div class="exec-card">
            <h3><i class="fas fa-warehouse"></i> Warehouse Overview <a href="<?php echo APP_URL; ?>modules/warehouse/inventory.php" style="margin-left:auto;font-size:.72rem;font-weight:600;color:#2563eb;">View Stock</a></h3>
            <div class="exec-rows">
                <div class="exec-row"><span>Low Stock Items</span><strong><?php echo number_format((int)($stats['low_stock_items'] ?? 0)); ?></strong></div>
                <div class="exec-row"><span>Total Stock Items</span><strong><?php echo number_format((int)($stats['inventory_items'] ?? 0)); ?></strong></div>
                <div class="exec-row"><span>Incoming Deliveries</span><strong><?php echo number_format((int)($stats['incoming_deliveries'] ?? 0)); ?></strong></div>
                <div class="exec-row"><span>Outgoing Requests</span><strong><?php echo number_format((int)($stats['outgoing_requests'] ?? 0)); ?></strong></div>
            </div>
        </div>
        <div class="exec-card">
            <h3><i class="fas fa-bell"></i> Items Needing Attention</h3>
            <ol class="exec-attn">
                <li><span class="n">1</span><div><strong>Unassigned Projects</strong><div><?php echo number_format((int)($items_needing_attention['unassigned_pic'] ?? 0)); ?> project(s) without In-Charge</div></div></li>
                <li><span class="n">2</span><div><strong>Pending Material Requests</strong><div><?php echo number_format((int)($items_needing_attention['pending_material_requests'] ?? 0)); ?> awaiting warehouse</div></div></li>
                <li><span class="n">3</span><div><strong>Delayed Deliveries</strong><div><?php echo number_format((int)($stats['delayed_deliveries'] ?? 0)); ?> purchase order(s) overdue</div></div></li>
                <li><span class="n">4</span><div><strong>Projects with Delayed Progress</strong><div><?php echo number_format((int)($items_needing_attention['overdue_projects'] ?? 0)); ?> past end date</div></div></li>
            </ol>
        </div>
    </div>

    <div class="exec-charts">
        <div class="exec-card">
            <h3>Project Status</h3>
            <div class="exec-donut">
                <div class="chart-box">
                    <canvas id="execStatusChart" width="150" height="150"></canvas>
                    <div class="center"><b><?php echo number_format($total_projects); ?></b><span>Projects</span></div>
                </div>
                <div class="exec-legend">
                    <?php $i = 0; foreach ($status_counts as $label => $count): ?>
                    <div><i style="background:<?php echo $status_colors[$i]; ?>"></i><?php echo htmlspecialchars($label); ?> (<?php echo (int)$count; ?>)</div>
                    <?php $i++; endforeach; ?>
                </div>
            </div>
        </div>
        <div class="exec-card">
            <h3>Project Cost Trend</h3>
            <div class="exec-cost-wrap">
                <canvas id="execCostChart"></canvas>
            </div>
        </div>
        <div class="exec-card">
            <h3>Recent Department Activity</h3>
            <?php if (empty($activity_limit)): ?>
                <p style="color:#94a3b8;font-size:.8rem;">No recent activity yet.</p>
            <?php else: ?>
            <ul class="exec-activity">
                <?php foreach ($activity_limit as $activity):
                    $dept = strtolower($activity['department'] ?? 'admin');
                    $color = $dept_dot[$dept] ?? '#64748b';
                    $initial = strtoupper(substr($activity['department'] ?? 'A', 0, 1));
                ?>
                <li>
                    <div class="blob" style="background:<?php echo $color; ?>"><?php echo htmlspecialchars($initial); ?></div>
                    <div>
                        <div><strong><?php echo htmlspecialchars(ucfirst($dept)); ?></strong> — <?php echo htmlspecialchars($activity['action']); ?></div>
                        <div class="when"><?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="exec-bottom">
        <div class="exec-card">
            <h3>Project List</h3>
            <?php if (empty($project_overview)): ?>
                <p style="color:#94a3b8;font-size:.85rem;">No projects yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="exec-table">
                    <thead>
                        <tr>
                            <th>Project ID</th>
                            <th>Name</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Project In-Charge</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($project_overview as $project):
                            $pct = dashboardTimeProgress($project);
                            $st = $project['status'] ?? 'planning';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars(displayDocumentCode($project['project_code'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($project['name']); ?></td>
                            <td><?php echo htmlspecialchars($project['client'] ?? $project['location'] ?? '—'); ?></td>
                            <td><span class="exec-badge <?php echo htmlspecialchars($st); ?>"><?php echo ucfirst(str_replace('_', ' ', $st)); ?></span></td>
                            <td>
                                <div class="exec-pbar">
                                    <div class="track"><span class="fill <?php echo $st === 'completed' ? 'done' : ''; ?>" style="width:<?php echo number_format($pct, 1); ?>%"></span></div>
                                    <em><?php echo (int) round($pct); ?>%</em>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($project['in_charge_name'] ?? 'Unassigned'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <div class="exec-card">
            <h3>Quick Actions</h3>
            <div class="exec-qa">
                <a class="t1" href="<?php echo APP_URL; ?>modules/projects/projects.php?action=add"><i class="fas fa-plus"></i>Create New Project</a>
                <a class="t2" href="<?php echo APP_URL; ?>modules/projects/projects.php?action=add"><i class="fas fa-user-plus"></i>Add Client</a>
                <a class="t3" href="<?php echo APP_URL; ?>modules/admin/consolidated_reports.php"><i class="fas fa-file-alt"></i>Generate Report</a>
                <a class="t4" href="<?php echo APP_URL; ?>modules/admin/users.php"><i class="fas fa-users-cog"></i>Manage Users</a>
                <a class="t5" href="<?php echo APP_URL; ?>modules/accounting/financial_reports.php"><i class="fas fa-chart-bar"></i>View Reports</a>
                <a class="t6" href="<?php echo APP_URL; ?>modules/admin/settings.php"><i class="fas fa-cog"></i>System Settings</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    const statusEl = document.getElementById('execStatusChart');
    if (statusEl) {
        new Chart(statusEl, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($status_counts)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($status_counts)); ?>,
                    backgroundColor: <?php echo json_encode($status_colors); ?>,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '72%',
                plugins: { legend: { display: false } }
            }
        });
    }
    const costEl = document.getElementById('execCostChart');
    if (costEl) {
        new Chart(costEl, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($cost_trend_labels ?: ['Jan','Feb','Mar','Apr','May','Jun']); ?>,
                datasets: [
                    { label: 'Budget', data: <?php echo json_encode($cost_trend_budget ?: [0,0,0,0,0,0]); ?>, backgroundColor: '#93c5fd', borderRadius: 4, maxBarThickness: 16 },
                    { label: 'Actual', data: <?php echo json_encode($cost_trend_actual ?: [0,0,0,0,0,0]); ?>, backgroundColor: '#3b82f6', borderRadius: 4, maxBarThickness: 16 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
                datasets: { bar: { categoryPercentage: 0.55, barPercentage: 0.7 } },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        suggestedMax: 10,
                        grid: { color: '#f1f5f9' },
                        ticks: { maxTicksLimit: 5, callback: function(v){ return v >= 1000000 ? (v/1000000) + 'M' : (v >= 1000 ? (v/1000) + 'k' : v); } }
                    }
                }
            }
        });
    }
})();
</script>
