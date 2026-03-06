<?php
require_once 'config.php';
requireAuth();
requireNivel('admin'); // Solo admin puede ver reportes

$tipo = $_GET['tipo'] ?? 'dia';

// ──────────────────────────────────────────────
//  RESUMEN DEL DÍA
//  Combina mesas ya cerradas (resumenes_diarios)
//  con mesas todavía abiertas (mesa pedido)
// ──────────────────────────────────────────────
function obtenerResumenDia() {
    $conn  = getConnection();
    $fecha = date('Y-m-d');

    // Mesas cerradas hoy (guardadas en resumenes_diarios)
    $sql = "SELECT `mesa`, SUM(`total`) AS total, 'cerrada' AS estado
            FROM `resumenes_diarios`
            WHERE `fecha` = ?
            GROUP BY `mesa`
            ORDER BY `mesa`";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fecha]);
    $cerradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mesas todavía abiertas con consumo en mesa pedido
    $sql2 = "SELECT `mp`.`mesa`,
                    SUM(`mp`.`cantidad` * `mp`.`precio_unitario`) AS total,
                    'abierta' AS estado
             FROM `mesa pedido` AS `mp`
             GROUP BY `mp`.`mesa`
             ORDER BY `mp`.`mesa`";
    $stmt2 = $conn->query($sql2);
    $abiertas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Unir evitando duplicar: si una mesa aparece en abiertas y cerradas, las sumamos
    $mesas = [];
    foreach ($cerradas as $r) {
        $mesas[$r['mesa']] = ['mesa' => $r['mesa'], 'total' => floatval($r['total']), 'estado' => 'cerrada'];
    }
    foreach ($abiertas as $r) {
        if (isset($mesas[$r['mesa']])) {
            $mesas[$r['mesa']]['total'] += floatval($r['total']);
            $mesas[$r['mesa']]['estado'] = 'mixta';
        } else {
            $mesas[$r['mesa']] = ['mesa' => $r['mesa'], 'total' => floatval($r['total']), 'estado' => 'abierta'];
        }
    }

    usort($mesas, fn($a, $b) => $a['mesa'] - $b['mesa']);
    return $mesas;
}

// ──────────────────────────────────────────────
//  RESUMEN DEL MES
//  Agrupa los resumenes_diarios por día del mes
// ──────────────────────────────────────────────
function obtenerResumenMes() {
    $conn = getConnection();
    $anio = date('Y');
    $mes  = date('m');

    $sql = "SELECT `fecha`,
                   COUNT(DISTINCT `mesa`) AS mesas_atendidas,
                   SUM(`total`)           AS total_dia
            FROM `resumenes_diarios`
            WHERE YEAR(`fecha`) = ? AND MONTH(`fecha`) = ?
            GROUP BY `fecha`
            ORDER BY `fecha`";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$anio, $mes]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$titulo    = '';
$subtitulo = '';
$items     = [];

if ($tipo === 'dia') {
    $titulo    = 'Resumen del Día';
    $subtitulo = date('d/m/Y');
    $items     = obtenerResumenDia();
    $total     = array_sum(array_column($items, 'total'));
} else {
    $titulo    = 'Resumen del Mes';
    $subtitulo = strftime('%B %Y') ?: date('m/Y');
    $items     = obtenerResumenMes();
    $total     = array_sum(array_column($items, 'total_dia'));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?> - Los Troncos</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ── Estilos exclusivos de la página de reportes ── */
        .reporte-container {
            max-width: 860px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .reporte-header {
            background: #fff;
            border-radius: 10px;
            padding: 28px 30px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-bottom: none;
            text-align: left;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .reporte-header-info h1 {
            color: #2c3e50;
            font-size: 1.6rem;
            margin-bottom: 4px;
        }

        .reporte-header-info p {
            color: #666;
            font-size: 0.95rem;
        }

        .reporte-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
        }

        .tab-btn {
            padding: 9px 22px;
            border: 2px solid #2c3e50;
            border-radius: 7px;
            background: transparent;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-btn.activo,
        .tab-btn:hover {
            background: #2c3e50;
            color: #fff;
        }

        /* Tabla de resumen */
        .tabla-resumen {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 16px;
        }

        .tabla-resumen thead {
            background: #37474f;
            color: #fff;
        }

        .tabla-resumen th,
        .tabla-resumen td {
            padding: 13px 18px;
            text-align: left;
            font-size: 0.95rem;
        }

        .tabla-resumen tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s;
        }

        .tabla-resumen tbody tr:last-child { border-bottom: none; }
        .tabla-resumen tbody tr:hover { background: #f5f7fa; }

        .tabla-resumen tfoot {
            background: #2c3e50;
            color: #fff;
            font-weight: bold;
        }

        .tabla-resumen tfoot td {
            padding: 15px 18px;
            font-size: 1.05rem;
        }

        /* Badge estado */
        .badge-estado {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .badge-cerrada { background: #e8f5e9; color: #2e7d32; }
        .badge-abierta { background: #fff3e0; color: #e65100; }
        .badge-mixta   { background: #e3eaf6; color: #1565c0; }

        /* Tarjeta de totales */
        .totales-card {
            background: #2c3e50;
            color: #fff;
            border-radius: 10px;
            padding: 20px 28px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .totales-card .dato {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .totales-card .dato .valor {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .totales-card .dato .etiqueta {
            font-size: 0.8rem;
            opacity: 0.75;
            margin-top: 4px;
        }

        /* Acciones */
        .acciones-reporte {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
            justify-content: flex-start;
        }

        .sin-datos {
            text-align: center;
            padding: 50px 20px;
            background: #fff;
            border-radius: 10px;
            color: #888;
            font-size: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 55px;
            right: 25px;
            padding: 12px 22px;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            font-size: 0.93rem;
            z-index: 2000;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s, transform 0.3s;
            pointer-events: none;
        }

        .toast.visible { opacity: 1; transform: translateY(0); }
        .toast.exito   { background: #2e7d32; }
        .toast.error   { background: #c62828; }

        @media print {
            .menu-bar, .reporte-tabs, .acciones-reporte, .footer-global { display: none !important; }
            body { background: white; }
            .reporte-container { margin: 0; padding: 0; max-width: 100%; }
            .totales-card { color: #000; background: #eee; }
        }
    </style>
</head>
<body>
    <!-- Barra de navegación -->
    <div class="menu-bar">
        <div class="menu-left">
            <span class="menu-title"><?php echo $titulo; ?> &mdash; <?php echo $subtitulo; ?></span>
        </div>
        <div class="menu-right">
            <button class="btn btn-sm" onclick="location.href='menu_principal.php'">&#8592; Volver al Menú</button>
            <button class="btn btn-sm btn-secondary" onclick="if(confirm('¿Desea salir?')) location.href='logout.php'">Salir</button>
        </div>
    </div>

    <div class="reporte-container">

        <!-- Tabs Día / Mes -->
        <div class="reporte-tabs">
            <button class="tab-btn <?php echo $tipo==='dia' ? 'activo' : ''; ?>"
                    onclick="location.href='reportes.php?tipo=dia'">Resumen del Día</button>
            <button class="tab-btn <?php echo $tipo==='mes' ? 'activo' : ''; ?>"
                    onclick="location.href='reportes.php?tipo=mes'">Resumen del Mes</button>
        </div>

        <!-- Encabezado -->
        <div class="reporte-header">
            <div class="reporte-header-info">
                <h1><?php echo $titulo; ?></h1>
                <p><?php echo $subtitulo; ?></p>
            </div>
            <div class="acciones-reporte" style="margin-top:0;">
                <button class="btn btn-sm btn-primary"
                        onclick="location.href='imprimir_resumen.php?tipo=<?php echo $tipo; ?>'">
                    Imprimir Resumen
                </button>
                <?php if ($tipo === 'dia'): ?>
                    <button class="btn btn-sm btn-success" onclick="cerrarDia()">
                        Cerrar el Día
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (count($items) > 0): ?>

            <!-- Tarjeta de totales -->
            <div class="totales-card">
                <?php if ($tipo === 'dia'): ?>
                    <div class="dato">
                        <span class="valor"><?php echo count($items); ?></span>
                        <span class="etiqueta">Mesas atendidas</span>
                    </div>
                    <div class="dato">
                        <span class="valor">$<?php echo number_format($total, 0, ',', '.'); ?></span>
                        <span class="etiqueta">Total del día</span>
                    </div>
                <?php else: ?>
                    <div class="dato">
                        <span class="valor"><?php echo count($items); ?></span>
                        <span class="etiqueta">Días con ventas</span>
                    </div>
                    <div class="dato">
                        <span class="valor">$<?php echo number_format($total, 0, ',', '.'); ?></span>
                        <span class="etiqueta">Total del mes</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tabla -->
            <table class="tabla-resumen">
                <thead>
                    <tr>
                        <?php if ($tipo === 'dia'): ?>
                            <th>Mesa</th>
                            <th>Estado</th>
                            <th style="text-align:right;">Total consumido</th>
                        <?php else: ?>
                            <th>Fecha</th>
                            <th style="text-align:center;">Mesas atendidas</th>
                            <th style="text-align:right;">Total del día</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <?php if ($tipo === 'dia'): ?>
                                <td><strong>Mesa <?php echo $item['mesa']; ?></strong></td>
                                <td>
                                    <span class="badge-estado badge-<?php echo $item['estado']; ?>">
                                        <?php
                                            $labels = ['cerrada' => 'Cerrada', 'abierta' => 'Abierta', 'mixta' => 'Parcial'];
                                            echo $labels[$item['estado']] ?? $item['estado'];
                                        ?>
                                    </span>
                                </td>
                                <td style="text-align:right; font-weight:600;">
                                    $<?php echo number_format($item['total'], 0, ',', '.'); ?>
                                </td>
                            <?php else: ?>
                                <td><?php echo date('d/m/Y', strtotime($item['fecha'])); ?></td>
                                <td style="text-align:center;"><?php echo $item['mesas_atendidas']; ?></td>
                                <td style="text-align:right; font-weight:600;">
                                    $<?php echo number_format($item['total_dia'], 0, ',', '.'); ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <?php if ($tipo === 'dia'): ?>
                            <td colspan="2">TOTAL DEL DÍA &mdash; <?php echo count($items); ?> mesa(s)</td>
                            <td style="text-align:right;">$<?php echo number_format($total, 0, ',', '.'); ?></td>
                        <?php else: ?>
                            <td colspan="2">TOTAL DEL MES</td>
                            <td style="text-align:right;">$<?php echo number_format($total, 0, ',', '.'); ?></td>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>

            <!-- Acciones inferiores -->
            <div class="acciones-reporte">
                <button class="btn btn-primary" onclick="location.href='imprimir_resumen.php?tipo=<?php echo $tipo; ?>'">
                    Imprimir Resumen
                </button>
                <?php if ($tipo === 'dia'): ?>
                    <button class="btn btn-success" onclick="cerrarDia()">Cerrar el Día</button>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="location.href='menu_principal.php'">
                    &#8592; Volver
                </button>
            </div>

        <?php else: ?>
            <div class="sin-datos">
                <p>No hay datos registrados para este período.</p>
                <?php if ($tipo === 'dia'): ?>
                    <p style="margin-top:8px; font-size:0.88rem; color:#aaa;">
                        Los registros se guardan automáticamente al cerrar cada mesa o al usar "Cerrar el Día".
                    </p>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="location.href='menu_principal.php'"
                        style="margin-top: 20px;">
                    &#8592; Volver al Menú
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        function cerrarDia() {
            if (!confirm('¿Cerrar el día?\n\nEsto guardará el resumen de todas las mesas actualmente abiertas y las dejará disponibles para el próximo turno.\n\nNOTA: El stock se descuenta individualmente al cerrar cada mesa desde la ventana de pedido.')) return;

            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Guardando...';

            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cerrar_dia' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    mostrarToast(data.message, 'exito');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    mostrarToast(data.message || 'Error al cerrar el día', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Cerrar el Día';
                }
            })
            .catch(() => {
                mostrarToast('Error de conexión', 'error');
                btn.disabled = false;
                btn.textContent = 'Cerrar el Día';
            });
        }

        function mostrarToast(msg, tipo = 'exito') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = `toast ${tipo} visible`;
            setTimeout(() => t.classList.remove('visible'), 3500);
        }
    </script>

    <footer class="footer-global">
        Sistema de Gesti&oacute;n de Restaurante &mdash; Versi&oacute;n 1.0
    </footer>
</body>
</html>


$tipo = $_GET['tipo'] ?? 'dia';

function obtenerResumenDia() {
    $conn = getConnection();
    $fecha = date('Y-m-d');
    $sql = "SELECT `mp`.`mesa`, `p`.`nombre`, `mp`.`cantidad`, `mp`.`precio_unitario`, 
                   (`mp`.`cantidad` * `mp`.`precio_unitario`) as subtotal
            FROM `mesa pedido` AS `mp`
            JOIN `productos` AS `p` ON `mp`.`producto_id` = `p`.`id`
            WHERE DATE(`mp`.`fecha_hora`) = ?
            ORDER BY `mp`.`mesa`, `mp`.`fecha_hora`";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fecha]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerResumenMes() {
    $conn = getConnection();
    $mes = date('Y-m');
    $sql = "SELECT `p`.`nombre`, SUM(`mp`.`cantidad`) as cantidad, AVG(`mp`.`precio_unitario`) as precio, 
                   SUM(`mp`.`cantidad` * `mp`.`precio_unitario`) as subtotal
            FROM `mesa pedido` AS `mp`
            JOIN `productos` AS `p` ON `mp`.`producto_id` = `p`.`id`
            WHERE DATE_FORMAT(`mp`.`fecha_hora`, '%Y-%m') = ?
            GROUP BY `p`.`id`, `p`.`nombre`
            ORDER BY subtotal DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$mes]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$items = [];
$total = 0;

if ($tipo === 'dia') {
    $items = obtenerResumenDia();
    $titulo = 'Resumen del Día';
    $subtitulo = date('d/m/Y');
} else {
    $items = obtenerResumenMes();
    $titulo = 'Resumen del Mes';
    $subtitulo = date('F Y');
}

$total = array_sum(array_column($items, 'subtotal'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?> - Los Troncos</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="reporte-container">
        <div class="reporte-header">
            <h1><?php echo $titulo; ?></h1>
            <h2><?php echo $subtitulo; ?></h2>
        </div>

        <?php if (count($items) > 0): ?>
            <table class="tabla-resumen">
                <thead>
                    <tr>
                        <?php if ($tipo === 'dia'): ?>
                            <th>Mesa</th>
                        <?php endif; ?>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <?php if ($tipo === 'dia'): ?>
                                <td><?php echo $item['mesa']; ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                            <td><?php echo $item['cantidad']; ?></td>
                            <td>$<?php echo number_format($item['precio'] ?? 0, 2); ?></td>
                            <td>$<?php echo number_format($item['subtotal'] ?? 0, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="<?php echo ($tipo === 'dia') ? '4' : '3'; ?>">TOTAL</th>
                        <th>$<?php echo number_format($total, 2); ?></th>
                    </tr>
                </tfoot>
            </table>

            <div class="acciones-reporte">
                <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimir Reporte</button>
                <button class="btn btn-danger" onclick="limpiarReporte()">
                    🗑️ Limpiar y Reiniciar
                </button>
                <button class="btn btn-secondary" onclick="location.href='menu_principal.php'">
                    ← Volver al Menú
                </button>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px;">
                <h3 style="color: #666;">No hay pedidos registrados para este período</h3>
                <button class="btn btn-secondary" 
                        onclick="location.href='menu_principal.php'" 
                        style="margin-top: 20px;">
                    ← Volver al Menú
                </button>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function limpiarReporte() {
            const tipo = '<?php echo $tipo; ?>';
            const mensaje = tipo === 'dia' 
                ? '¿Desea limpiar todos los pedidos del día? Esta acción no se puede deshacer.'
                : '¿Desea limpiar todos los pedidos del mes? Esta acción no se puede deshacer.';
            
            if (!confirm(mensaje)) return;

            const action = tipo === 'dia' ? 'limpiar_dia' : 'limpiar_mes';

            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: action })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reporte limpiado correctamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    </script>

    <footer class="footer-global">
        Sistema de Gesti&oacute;n de Restaurante &mdash; Versi&oacute;n 1.0
    </footer>
</body>
</html>
