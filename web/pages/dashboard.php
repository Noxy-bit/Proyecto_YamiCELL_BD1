<?php
/** Dashboard BI — KPIs + Chart.js alimentados por api.php?action=dashboard */
$sucursales = q($pdo, "SELECT * FROM sucursales ORDER BY id_sucursal")->fetchAll();
?>
<div class="card" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
    <div class="switcher" id="sucSwitcher">
        <button data-suc="0" class="active"><i class="fa-solid fa-layer-group"></i> Consolidado Global</button>
        <button data-suc="1"><i class="fa-solid fa-store"></i> Puesto 98 · Tarija</button>
        <button data-suc="2"><i class="fa-solid fa-store"></i> Puesto 121 · Las Américas</button>
    </div>
    <span class="muted"><i class="fa-solid fa-bolt"></i> Datos calculados con agregación SQL en vivo (SUM, AVG, COUNT, GROUP BY, COALESCE)</span>
</div>

<div class="grid grid-5" id="kpis">
    <div class="kpi"><i class="fa-solid fa-sack-dollar"></i><div class="lbl">Ingresos Totales</div><div class="val" id="k-ing">—</div><div class="sub" id="k-hoy">Hoy: —</div></div>
    <div class="kpi"><i class="fa-solid fa-receipt"></i><div class="lbl">Ventas Registradas</div><div class="val" id="k-num">—</div><div class="sub">Recibos emitidos</div></div>
    <div class="kpi"><i class="fa-solid fa-ticket"></i><div class="lbl">Ticket Promedio</div><div class="val" id="k-ticket">—</div><div class="sub">Por recibo</div></div>
    <div class="kpi"><i class="fa-solid fa-arrow-trend-up"></i><div class="lbl">Ganancia Bruta</div><div class="val" id="k-gan">—</div><div class="sub">Ingresos − costos</div></div>
    <div class="kpi"><i class="fa-solid fa-triangle-exclamation"></i><div class="lbl">Alertas de Stock</div><div class="val" id="k-alert">—</div><div class="sub">≤ stock mínimo</div></div>
</div>

<div class="grid grid-2" style="margin-top:20px">
    <div class="card"><h3><i class="fa-solid fa-chart-line"></i> Ventas por Día (Bs.)</h3><canvas id="chDia" height="120"></canvas></div>
    <div class="card"><h3><i class="fa-solid fa-chart-pie"></i> Ingresos por Categoría</h3><canvas id="chCat" height="120"></canvas></div>
    <div class="card"><h3><i class="fa-solid fa-chart-column"></i> Ingresos por Sucursal (Bs.)</h3><canvas id="chSuc" height="120"></canvas></div>
    <div class="card"><h3><i class="fa-solid fa-credit-card"></i> Métodos de Pago</h3><canvas id="chPago" height="120"></canvas></div>
</div>

<div class="grid grid-3">
    <div class="card"><h3><i class="fa-solid fa-fire"></i> Top Productos (unidades)</h3><canvas id="chTop" height="150"></canvas></div>
    <div class="card">
        <h3><i class="fa-solid fa-triangle-exclamation"></i> Stock Crítico por Puesto</h3>
        <div class="table-wrap"><table class="data"><thead><tr><th>SKU</th><th>Producto</th><th>Puesto</th><th class="num">Stock</th><th class="num">Mín</th></tr></thead><tbody id="tblCrit"></tbody></table></div>
    </div>
    <div class="card">
        <h3><i class="fa-solid fa-clock-rotate-left"></i> Últimas Ventas</h3>
        <div class="table-wrap"><table class="data"><thead><tr><th>Recibo</th><th>Fecha</th><th>Puesto</th><th>Cliente</th><th>Pago</th><th class="num">Total</th></tr></thead><tbody id="tblUlt"></tbody></table></div>
    </div>
</div>

