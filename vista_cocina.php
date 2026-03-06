<?php
require_once 'config.php';
requireAuth();

$nivelActual = getNivelUsuario();
if ($nivelActual !== 'cocina' && $nivelActual !== 'admin') {
    die('Acceso denegado. Nivel requerido: cocina o admin.');
}

requireNivel('cocina');

// ──────────────────────────────────────────────────────────────────
//  Obtiene TODOS los pedidos activos de TODAS las mesas,
//  excluyendo bebidas. Retorna array agrupado por mesa.
// ──────────────────────────────────────────────────────────────────
function obtenerTodosLosPedidosCocina() {
    $conn = getConnection();
    $sql = "SELECT `mp`.`mesa`,
                   `p`.`nombre`,
                   `mp`.`cantidad`,
                   `mp`.`fecha_hora`,
                   COALESCE(`u`.`nombre`, 'Desconocido')  AS mozo,
                   COALESCE(`tp`.`Nombre`, 'Otros')        AS tipo_producto,
                   COALESCE(`t`.`Nombre`, '')              AS tipo_principal
            FROM `mesa pedido` AS `mp`
            JOIN `productos`       AS `p`  ON `mp`.`producto_id`      = `p`.`id`
            LEFT JOIN `usuario`    AS `u`  ON `mp`.`id_mozo`          = `u`.`id_usuario`
            LEFT JOIN `tipo producto` AS `tp` ON `p`.`Id_tipo_producto` = `tp`.`Id_tipo_producto`
            LEFT JOIN `tipo`       AS `t`  ON `p`.`id_tipo`            = `t`.`Id_tipo`
            WHERE LOWER(COALESCE(`t`.`Nombre`, '')) NOT LIKE '%bebid%'
            ORDER BY `mp`.`mesa` ASC, `mp`.`fecha_hora` ASC";
    $stmt = $conn->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $porMesa = [];
    foreach ($rows as $r) {
        $m = $r['mesa'];
        if (!isset($porMesa[$m])) {
            $porMesa[$m] = [
                'mesa'  => $m,
                'mozo'  => $r['mozo'],
                'hora'  => $r['fecha_hora'],
                'items' => [],
                'tipos' => [],
            ];
        }
        $porMesa[$m]['items'][] = $r;
        $porMesa[$m]['tipos'][] = $r['tipo_producto'];
    }
    foreach ($porMesa as &$d) {
        $d['tipos'] = array_values(array_unique($d['tipos']));
        sort($d['tipos']);
    }
    unset($d);
    ksort($porMesa);
    return $porMesa;
}

function obtenerNotasPorMesa(array $mesas) {
    if (empty($mesas)) return [];
    $conn = getConnection();
    $placeholders = implode(',', array_fill(0, count($mesas), '?'));
    $sql  = "SELECT `mesa`, COALESCE(MAX(`notas`), '') AS notas
             FROM `mesa pedido`
             WHERE `mesa` IN ($placeholders)
             GROUP BY `mesa`";
    $stmt = $conn->prepare($sql);
    $stmt->execute($mesas);
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $result[$r['mesa']] = $r['notas'];
    }
    return $result;
}

$porMesa = obtenerTodosLosPedidosCocina();
$notas   = obtenerNotasPorMesa(array_keys($porMesa));

$tiposGlobales = [];
foreach ($porMesa as $d) {
    $tiposGlobales = array_merge($tiposGlobales, $d['tipos']);
}
$tiposGlobales = array_values(array_unique($tiposGlobales));
sort($tiposGlobales);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Cocina</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #dde1e7;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            color: #2c3e50;
        }

        /* === HEADER === */
        .header-cocina {
            background: #2c3e50;
            color: #fff;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #1a252f;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,.15);
        }
        .header-cocina h1 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #fff;
            letter-spacing: 1px;
        }
        .reloj {
            font-size: 1.6rem;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
            color: #ecf0f1;
        }
        .btn-volver {
            background: rgba(255,255,255,.12);
            border: 2px solid rgba(255,255,255,.3);
            color: #fff;
            padding: 8px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: .9rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-volver:hover { background: rgba(255,255,255,.22); }

        /* === CONTADORES === */
        .contadores-bar {
            background: #37474f;
            padding: 10px 30px;
            display: flex;
            gap: 28px;
            font-size: .92rem;
            color: #b0bec5;
            border-bottom: 2px solid #263238;
        }
        .contadores-bar span strong { color: #fff; font-size: 1rem; }

        /* === FILTROS === */
        .filtros-bar {
            background: #f8f9fa;
            padding: 12px 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: center;
            border-bottom: 2px solid #dee2e6;
        }
        .filtros-bar label {
            width: 100%;
            text-align: center;
            color: #666;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .btn-filtro {
            background: #fff;
            border: 2px solid #dee2e6;
            color: #2c3e50;
            padding: 7px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
        }
        .btn-filtro:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        .btn-filtro.activo {
            background: #2e7d32;
            border-color: #1b5e20;
            color: #fff;
            box-shadow: 0 2px 8px rgba(46,125,50,.3);
        }

        /* === GRID DE MESAS === */
        .panel-mesas {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
            padding: 28px;
        }

        .mesa-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #dee2e6;
            box-shadow: 0 4px 20px rgba(0,0,0,.1);
            display: flex;
            flex-direction: column;
            transition: transform .2s, box-shadow .2s;
        }
        .mesa-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0,0,0,.15);
        }

        /* Header de la tarjeta */
        .mesa-card-header {
            background: #2e7d32;
            padding: 20px 20px 16px;
            display: flex;
            flex-direction: column;
            gap: 0;
            border-bottom: 3px solid #1b5e20;
        }

        /* Fila superior: número de mesa + botón avisar */
        .mesa-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .mesa-numero {
            font-size: 2.6rem;
            font-weight: bold;
            color: #fff;
            line-height: 1;
            letter-spacing: 2px;
        }
        .btn-avisar-top {
            background: #1565c0;
            color: #fff;
            border: none;
            padding: 11px 22px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,.2);
            transition: all .2s;
            white-space: nowrap;
        }
        .btn-avisar-top:hover {
            background: #0d47a1;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.25);
        }
        .btn-avisar-top:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
        }

        /* Separador */
        .mesa-header-sep {
            border: none;
            border-top: 1px solid rgba(255,255,255,.25);
            margin: 0 0 12px;
        }

        /* Info inferior: mozo, hora, badge */
        .mesa-info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,.95);
            margin-bottom: 8px;
        }
        .mesa-info-row:last-child { margin-bottom: 0; }
        .mesa-info-label {
            color: rgba(255,255,255,.65);
            font-size: .82rem;
            min-width: 60px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .mesa-info-valor {
            font-weight: 600;
            font-size: 1.1rem;
            flex: 1;
        }
        .mesa-info-valor.hora {
            font-size: 1rem;
        }
        .tiempo-badge {
            font-size: .82rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 6px;
            background: rgba(0,0,0,.3);
            color: #fff;
            white-space: nowrap;
        }
        .tiempo-badge.urgente      { background: #c62828; }
        .tiempo-badge.advertencia  { background: #e65100; }

        /* Items */
        .mesa-card-body { padding: 16px 18px; flex: 1; background: white; }

        .item-cocina {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .item-cocina:last-child { border-bottom: none; }

        .item-cantidad {
            background: #2c3e50;
            color: #fff;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .item-nombre { flex: 1; font-size: 1.1rem; font-weight: 600; color: #2c3e50; line-height: 1.3; }
        .tipo-badge {
            font-size: .78rem;
            padding: 4px 10px;
            border-radius: 6px;
            background: #f8f9fa;
            color: #555;
            border: 1px solid #dee2e6;
            white-space: nowrap;
            font-weight: 600;
        }
        .item-hora { font-size: .8rem; color: #666; white-space: nowrap; font-weight: 600; }

        /* Notas */
        .mesa-notas {
            background: #fff9c4;
            color: #3d2b00;
            padding: 12px 18px;
            font-size: .92rem;
            border-top: 3px solid #f9a825;
            line-height: 1.5;
        }
        .mesa-notas strong { display: block; margin-bottom: 4px; color: #7d5a00; font-size: .8rem; text-transform: uppercase; letter-spacing: .5px; }

        /* Sin footer (botón movido al header) */

        /* Sin pedidos */
        .sin-pedidos {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #666;
            font-size: 1.1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }

        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to   { transform: translateX(0);     opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0);     opacity: 1; }
            to   { transform: translateX(400px); opacity: 0; }
        }

        @media (max-width: 768px) {
            .header-cocina { padding: 14px 16px; }
            .header-cocina h1 { font-size: 1.2rem; }
            .reloj { font-size: 1.3rem; letter-spacing: 2px; }
            .panel-mesas { grid-template-columns: 1fr; padding: 14px; }
        }
        @media (max-width: 480px) {
            .header-cocina h1 { font-size: 1rem; }
            .reloj { font-size: 1rem; letter-spacing: 1px; }
            .item-nombre { font-size: .9rem; }
        }
    </style>
</head>
<body>

<!-- ============================
     HEADER
============================= -->
<div class="header-cocina">
    <a href="menu_principal.php" class="btn-volver">← Menú</a>
    <h1>PANEL DE COCINA</h1>
    <div class="reloj" id="reloj">00:00:00</div>
</div>

<!-- CONTADORES -->
<div class="contadores-bar">
    <span>Mesas con pedidos: <strong id="cnt-mesas"><?php echo count($porMesa); ?></strong></span>
    <span>Items para cocinar: <strong id="cnt-items"><?php
        $totalItems = 0;
        foreach ($porMesa as $d) {
            foreach ($d['items'] as $i) $totalItems += $i['cantidad'];
        }
        echo $totalItems;
    ?></strong></span>
</div>

<!-- FILTROS -->
<div class="filtros-bar">
    <label>FILTRAR:</label>
    <button class="btn-filtro activo" onclick="filtrar('todos', this)">Todos</button>
    <?php foreach ($tiposGlobales as $tipo): ?>
        <button class="btn-filtro" onclick="filtrar('<?php echo htmlspecialchars($tipo, ENT_QUOTES); ?>', this)">
            <?php echo htmlspecialchars($tipo); ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- GRID DE MESAS -->
<div class="panel-mesas">
<?php if (empty($porMesa)): ?>
    <div class="sin-pedidos">No hay pedidos de comida pendientes.</div>
<?php else: ?>
    <?php foreach ($porMesa as $d):
        $mesaNum = $d['mesa'];
        $notaCarta = $notas[$mesaNum] ?? ''; ?>
    <div class="mesa-card" data-mesa="<?php echo $mesaNum; ?>">

        <!-- Cabecera de tarjeta -->
        <div class="mesa-card-header">

            <!-- Fila superior: número + botón avisar -->
            <div class="mesa-header-top">
                <div class="mesa-numero">MESA <?php echo str_pad($mesaNum, 2, '0', STR_PAD_LEFT); ?></div>
                <button class="btn-avisar-top" onclick="avisarMozo(<?php echo $mesaNum; ?>, this)">
                    Avisar Mozo
                </button>
            </div>

            <hr class="mesa-header-sep">

            <!-- Mozo -->
            <div class="mesa-info-row">
                <span class="mesa-info-label">Mozo:</span>
                <span class="mesa-info-valor"><?php echo htmlspecialchars($d['mozo']); ?></span>
            </div>

            <!-- Hora de llegada + badge de tiempo -->
            <div class="mesa-info-row">
                <span class="mesa-info-label">Pedido:</span>
                <span class="mesa-info-valor hora"><?php echo date('H:i', strtotime($d['hora'])); ?>h</span>
                <div class="tiempo-badge" data-hora="<?php echo htmlspecialchars($d['hora']); ?>">--:--</div>
            </div>

        </div>

        <!-- Items de comida -->
        <div class="mesa-card-body">
            <?php foreach ($d['items'] as $item): ?>
            <div class="item-cocina" data-tipo="<?php echo htmlspecialchars($item['tipo_producto']); ?>">
                <div class="item-cantidad"><?php echo (int)$item['cantidad']; ?></div>
                <div class="item-nombre"><?php echo htmlspecialchars($item['nombre']); ?></div>
                <div class="tipo-badge"><?php echo htmlspecialchars($item['tipo_producto']); ?></div>
                <div class="item-hora"><?php echo date('H:i', strtotime($item['fecha_hora'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($notaCarta)): ?>
        <div class="mesa-notas">
            <strong>Nota:</strong>
            <?php echo nl2br(htmlspecialchars($notaCarta)); ?>
        </div>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<script>
// ─── Reloj ───────────────────────────────────────────────────────────────────
function actualizarReloj() {
    const a = new Date();
    document.getElementById('reloj').textContent =
        String(a.getHours()).padStart(2,'0') + ':' +
        String(a.getMinutes()).padStart(2,'0') + ':' +
        String(a.getSeconds()).padStart(2,'0');
}
setInterval(actualizarReloj, 1000);
actualizarReloj();

// ─── Temporizadores por mesa ─────────────────────────────────────────────────
function actualizarTiempos() {
    document.querySelectorAll('.tiempo-badge[data-hora]').forEach(badge => {
        const hora = new Date(badge.dataset.hora.replace(' ','T'));
        const min  = Math.floor((Date.now() - hora) / 60000);
        const seg  = Math.floor(((Date.now() - hora) % 60000) / 1000);
        badge.textContent = min > 0 ? `${min}m ${seg}s` : `${seg}s`;
        badge.className = 'tiempo-badge' +
            (min >= 20 ? ' urgente' : min >= 10 ? ' advertencia' : '');
    });
}
setInterval(actualizarTiempos, 1000);
actualizarTiempos();

// ─── Filtro cross-mesa ───────────────────────────────────────────────────────
function filtrar(tipo, btn) {
    document.querySelectorAll('.btn-filtro').forEach(b => b.classList.remove('activo'));
    btn.classList.add('activo');
    sessionStorage.setItem('filtro_cocina', tipo);

    document.querySelectorAll('.mesa-card').forEach(card => {
        if (tipo === 'todos') {
            card.style.display = '';
            card.querySelectorAll('.item-cocina').forEach(i => i.style.display = '');
            return;
        }
        // Mostrar solo items del tipo seleccionado
        let hayItems = false;
        card.querySelectorAll('.item-cocina').forEach(item => {
            const coincide = item.dataset.tipo === tipo;
            item.style.display = coincide ? '' : 'none';
            if (coincide) hayItems = true;
        });
        // Ocultar toda la tarjeta si no tiene items de ese tipo
        card.style.display = hayItems ? '' : 'none';
    });
}

// Restaurar filtro al recargar
const filtroGuardado = sessionStorage.getItem('filtro_cocina');
if (filtroGuardado && filtroGuardado !== 'todos') {
    const btnGuardado = Array.from(document.querySelectorAll('.btn-filtro'))
        .find(b => b.textContent.trim() === filtroGuardado);
    if (btnGuardado) filtrar(filtroGuardado, btnGuardado);
}

// ─── Avisar mozo ─────────────────────────────────────────────────────────────
function avisarMozo(mesa, btn) {
    btn.disabled = true;
    btn.textContent = 'Enviando...';
    const fd = new FormData();
    fd.append('action', 'notificar_pedido_listo');
    fd.append('mesa', mesa);
    fetch('api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.textContent = d.success ? 'Avisado!' : 'Error';
            btn.style.background = d.success ? '#2e7d32' : '#c62828';
            setTimeout(() => {
                btn.textContent = 'Avisar Mozo';
                btn.style.background = '';
                btn.disabled = false;
            }, 3000);
        })
        .catch(() => {
            btn.textContent = 'Error';
            btn.disabled = false;
        });
}

// ─── Auto-recarga cada 8 segundos ────────────────────────────────────────────
setInterval(() => location.reload(), 8000);
</script>
</body>
</html>