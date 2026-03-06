<?php
require_once 'config.php';
requireAuth();
requireNivel('admin');

$tipo = $_GET['tipo'] ?? 'dia';

$conn  = getConnection();
$fecha = date('Y-m-d');
$anio  = date('Y');
$mes   = date('m');

if ($tipo === 'dia') {
    // ── Mesas cerradas hoy ──
    $sql = "SELECT `mesa`, SUM(`total`) AS total, 'cerrada' AS estado
            FROM `resumenes_diarios`
            WHERE `fecha` = ?
            GROUP BY `mesa`
            ORDER BY `mesa`";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fecha]);
    $cerradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Mesas todavía abiertas ──
    $sql2 = "SELECT `mp`.`mesa`,
                    SUM(`mp`.`cantidad` * `mp`.`precio_unitario`) AS total,
                    'abierta' AS estado
             FROM `mesa pedido` AS `mp`
             GROUP BY `mp`.`mesa`
             ORDER BY `mp`.`mesa`";
    $stmt2 = $conn->query($sql2);
    $abiertas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Unir resultados
    $mesas = [];
    foreach ($cerradas as $r) {
        $mesas[$r['mesa']] = ['mesa' => $r['mesa'], 'total' => floatval($r['total']), 'estado' => 'Cerrada'];
    }
    foreach ($abiertas as $r) {
        if (isset($mesas[$r['mesa']])) {
            $mesas[$r['mesa']]['total'] += floatval($r['total']);
            $mesas[$r['mesa']]['estado'] = 'Parcial';
        } else {
            $mesas[$r['mesa']] = ['mesa' => $r['mesa'], 'total' => floatval($r['total']), 'estado' => 'Abierta'];
        }
    }
    usort($mesas, fn($a, $b) => $a['mesa'] - $b['mesa']);

    $items          = $mesas;
    $total_general  = array_sum(array_column($items, 'total'));
    $titulo         = 'Resumen del Día';
    $subtitulo      = date('d/m/Y');
    $columnas       = ['Mesa', 'Estado', 'Total'];

} else {
    // ── Resumen mensual: por día ──
    $sql = "SELECT `fecha`,
                   COUNT(DISTINCT `mesa`) AS mesas_atendidas,
                   SUM(`total`)           AS total_dia
            FROM `resumenes_diarios`
            WHERE YEAR(`fecha`) = ? AND MONTH(`fecha`) = ?
            GROUP BY `fecha`
            ORDER BY `fecha`";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$anio, $mes]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_general = array_sum(array_column($items, 'total_dia'));
    $titulo        = 'Resumen del Mes';
    $subtitulo     = date('m/Y');
    $columnas      = ['Fecha', 'Mesas', 'Total del día'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $titulo; ?> — Los Troncos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @media print {
            body { width: 80mm; }
            .no-print { display: none !important; }
        }

        body {
            font-family: 'Courier New', monospace;
            width: 80mm;
            margin: 0 auto;
            padding: 8px;
            font-size: 11px;
            line-height: 1.4;
            background: white;
        }

        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .header h1 { font-size: 14px; font-weight: bold; }
        .header p  { font-size: 10px; margin-top: 2px; }

        .titulo-reporte {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            margin: 8px 0 4px;
            border: 1px solid #000;
            padding: 4px;
        }

        .fecha-reporte {
            text-align: center;
            font-size: 10px;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        thead tr {
            border-bottom: 1px solid #000;
            border-top: 1px solid #000;
        }

        th, td { padding: 3px 2px; }

        th { font-weight: bold; }

        td.num, th.num { text-align: right; }

        tbody tr { border-bottom: 1px dotted #ccc; }

        tfoot tr {
            border-top: 2px solid #000;
            font-weight: bold;
        }

        .totales-box {
            margin-top: 10px;
            border-top: 2px dashed #000;
            padding-top: 8px;
        }

        .tot-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: bold;
        }

        .tot-row.grande {
            font-size: 14px;
            margin-top: 4px;
            border-top: 1px solid #000;
            padding-top: 4px;
        }

        .footer {
            text-align: center;
            margin-top: 12px;
            border-top: 2px dashed #000;
            padding-top: 8px;
            font-size: 10px;
        }

        .footer p { margin: 2px 0; }

        /* Botón solo en pantalla */
        .no-print {
            text-align: center;
            margin-top: 16px;
        }

        .no-print button {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            padding: 6px 16px;
            cursor: pointer;
            border: 1px solid #000;
            background: #fff;
            border-radius: 4px;
            margin: 0 4px;
        }

        .no-print button:hover { background: #eee; }
    </style>
</head>
<body>

    <div class="header">
        <h1>LOS TRONCOS</h1>
        <p>Restaurante</p>
        <p>Sistema de Gestión</p>
    </div>

    <div class="titulo-reporte"><?php echo strtoupper($titulo); ?></div>
    <div class="fecha-reporte"><?php echo $subtitulo; ?> &mdash; Impreso: <?php echo date('d/m/Y H:i'); ?></div>

    <?php if (count($items) > 0): ?>

        <table>
            <thead>
                <tr>
                    <?php foreach ($columnas as $i => $col): ?>
                        <th <?php echo $i === count($columnas) - 1 ? 'class="num"' : ''; ?>>
                            <?php echo $col; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <?php if ($tipo === 'dia'): ?>
                            <td>Mesa <?php echo $item['mesa']; ?></td>
                            <td><?php echo $item['estado']; ?></td>
                            <td class="num">$<?php echo number_format($item['total'], 0, ',', '.'); ?></td>
                        <?php else: ?>
                            <td><?php echo date('d/m/Y', strtotime($item['fecha'])); ?></td>
                            <td style="text-align:center;"><?php echo $item['mesas_atendidas']; ?></td>
                            <td class="num">$<?php echo number_format($item['total_dia'], 0, ',', '.'); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totales-box">
            <?php if ($tipo === 'dia'): ?>
                <div class="tot-row">
                    <span>Mesas atendidas:</span>
                    <span><?php echo count($items); ?></span>
                </div>
                <div class="tot-row grande">
                    <span>TOTAL DEL DÍA:</span>
                    <span>$<?php echo number_format($total_general, 0, ',', '.'); ?></span>
                </div>
            <?php else: ?>
                <div class="tot-row">
                    <span>Días con ventas:</span>
                    <span><?php echo count($items); ?></span>
                </div>
                <div class="tot-row grande">
                    <span>TOTAL DEL MES:</span>
                    <span>$<?php echo number_format($total_general, 0, ',', '.'); ?></span>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <p style="text-align:center; margin: 20px 0;">Sin datos para este período.</p>
    <?php endif; ?>

    <div class="footer">
        <p>Los Troncos &mdash; Sistema de Gestión</p>
        <p><?php echo date('d/m/Y H:i:s'); ?></p>
    </div>

    <div class="no-print">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

    <script>
        // Abrir directamente el diálogo de impresión
        window.addEventListener('load', function () {
            // Pequeña pausa para que cargue el CSS
            setTimeout(function () { window.print(); }, 300);
        });
    </script>
</body>
</html>
