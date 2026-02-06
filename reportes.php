<?php
require_once 'config.php';
requireAuth();
requireNivel('admin'); // Solo admin puede ver reportes

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
</body>
</html>
