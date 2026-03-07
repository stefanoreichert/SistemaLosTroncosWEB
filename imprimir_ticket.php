<?php
require_once 'config.php';
requireAuth();

$numeroMesa = isset($_GET['mesa']) ? (int)$_GET['mesa'] : 1;
$nivel = getNivelUsuario();

// Obtener pedido de la mesa
$conn = getConnection();
$sql = "SELECT `mp`.`mesa`, `mp`.`producto_id`, `p`.`nombre`, `mp`.`cantidad`, `mp`.`precio_unitario`, 
               (`mp`.`cantidad` * `mp`.`precio_unitario`) as subtotal
        FROM `mesa pedido` AS `mp`
        JOIN `productos` AS `p` ON `mp`.`producto_id` = `p`.`id`
        WHERE `mp`.`mesa` = ?
        ORDER BY `mp`.`fecha_hora`";
$stmt = $conn->prepare($sql);
$stmt->execute([$numeroMesa]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = array_sum(array_column($items, 'subtotal'));

// Detectar si es un pedido Delivery
$esDelivery  = $numeroMesa >= (DELIVERY_BASE + 1);
$mesaLabel   = $esDelivery
    ? 'DELIVERY ' . ($numeroMesa - DELIVERY_BASE)
    : 'MESA ' . str_pad($numeroMesa, 2, '0', STR_PAD_LEFT);
$ticketTipo  = $esDelivery ? 'Pedido Delivery' : 'Pedido de Mesa';

// Solo descontar stock si es admin cerrando la mesa
if ($nivel === 'admin' && isset($_GET['cerrar'])) {
    // Descontar stock
    foreach ($items as $item) {
        $sql = "UPDATE `productos` SET `stock` = `stock` - ? WHERE `id` = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$item['cantidad'], $item['producto_id']]);
    }

    // Guardar en resumenes_diarios (total del día)
    if ($total > 0) {
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');
        $productos = json_encode(array_map(function($item) {
            return $item['nombre'] . ' (x' . $item['cantidad'] . ')';
        }, $items));
        
        $sql = "INSERT INTO `resumenes_diarios` (`fecha`, `hora`, `mesa`, `total`, `productos`) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$fecha, $hora, $numeroMesa, $total, $productos]);
    }

    // Borrar pedido de la mesa
    $sql = "DELETE FROM `mesa pedido` WHERE `mesa` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$numeroMesa]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?php echo $mesaLabel; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @media print {
            body {
                width: 80mm;
                margin: 0;
                padding: 0;
            }
        }
        
        body {
            font-family: 'Courier New', monospace;
            width: 80mm;
            margin: 0 auto;
            padding: 5px;
            font-size: 11px;
            line-height: 1.3;
            background: white;
        }
        
        .ticket {
            width: 100%;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        
        .header h1 {
            font-size: 14px;
            margin: 2px 0;
            font-weight: bold;
        }
        
        .header p {
            margin: 2px 0;
            font-size: 10px;
        }
        
        .mesa-info {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
            padding: 5px;
            border: 1px solid #000;
        }
        
        .items {
            margin: 10px 0;
        }
        
        .item {
            margin-bottom: 6px;
            padding-bottom: 3px;
            border-bottom: 1px dotted #ccc;
        }
        
        .item:last-child {
            border-bottom: none;
        }
        
        .item-nombre {
            font-weight: bold;
            margin-bottom: 2px;
            word-wrap: break-word;
        }
        
        .item-detalle {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
        }
        
        .item-cantidad {
            text-align: left;
        }
        
        .item-precio {
            text-align: right;
        }
        
        .separator {
            border-top: 2px solid #000;
            margin: 8px 0;
        }
        
        .total-section {
            margin: 10px 0;
        }
        
        .total {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: bold;
            padding: 5px 0;
        }
        
        .footer {
            text-align: center;
            margin-top: 10px;
            border-top: 2px dashed #000;
            padding-top: 8px;
            font-size: 10px;
        }
        
        .footer p {
            margin: 3px 0;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <!-- VISTA PARA MOZO: Solo muestra lo que pidió -->
        <?php if ($nivel === 'mozo'): ?>
            <div class="header">
                <h1>LOS TRONCOS</h1>
                <p><?php echo $ticketTipo; ?></p>
                <p><?php echo date('d/m/Y H:i'); ?></p>
            </div>

            <div class="mesa-info">
                <?php echo $mesaLabel; ?>
            </div>

            <div class="items">
                <?php if (count($items) > 0): ?>
                    <p style="text-align: center; font-weight: bold; margin: 5px 0;">PEDIDO CONFIRMADO</p>
                    <?php foreach ($items as $item): ?>
                        <div class="item">
                            <div class="item-nombre"><?php echo htmlspecialchars($item['nombre']); ?></div>
                            <div class="item-cantidad" style="text-align: center; padding: 3px 0;">
                                Cantidad: <strong><?php echo $item['cantidad']; ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="item" style="text-align: center; color: #999;">
                        Sin items
                    </div>
                <?php endif; ?>
            </div>

            <div class="footer">
                <p>Pedido de Mozo</p>
                <p><?php echo date('d/m/Y H:i:s'); ?></p>
            </div>

        <!-- VISTA PARA COCINA: Solo muestra qué cocinar -->
        <?php elseif ($nivel === 'cocina'): ?>
            <div class="header">
                <h1>LOS TRONCOS</h1>
                <p>Orden de Cocina</p>
                <p><?php echo date('d/m/Y H:i'); ?></p>
            </div>

            <div class="mesa-info">
                <?php echo $mesaLabel; ?>
            </div>

            <div class="items">
                <?php if (count($items) > 0): ?>
                    <p style="text-align: center; font-weight: bold; margin: 5px 0;">ÓRDENES A PREPARAR</p>
                    <?php foreach ($items as $item): ?>
                        <div class="item">
                            <div class="item-nombre"><?php echo htmlspecialchars($item['nombre']); ?></div>
                            <div class="item-cantidad" style="text-align: center; padding: 5px 0; border: 1px solid #000;">
                                <strong><?php echo $item['cantidad']; ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="item" style="text-align: center; color: #999;">
                        Sin órdenes
                    </div>
                <?php endif; ?>
            </div>

            <div class="footer">
                <p>Orden de Cocina</p>
                <p><?php echo date('d/m/Y H:i:s'); ?></p>
            </div>

        <!-- VISTA PARA ADMIN: Ticket completo con precios y totales -->
        <?php else: ?>
            <div class="header">
                <h1>LOS TRONCOS</h1>
                <p>Restaurante</p>
                <p><?php echo date('d/m/Y H:i'); ?></p>
            </div>

            <div class="mesa-info">
                <?php echo $mesaLabel; ?>
            </div>

            <div class="items">
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $item): ?>
                        <div class="item">
                            <div class="item-nombre"><?php echo htmlspecialchars(substr($item['nombre'], 0, 30)); ?></div>
                            <div class="item-detalle">
                                <div class="item-cantidad">
                                    <?php echo $item['cantidad']; ?> x $<?php echo number_format($item['precio_unitario'], 2); ?>
                                </div>
                                <div class="item-precio">
                                    $<?php echo number_format($item['subtotal'], 2); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="item" style="text-align: center; color: #999;">
                        Sin items
                    </div>
                <?php endif; ?>
            </div>

            <div class="separator"></div>

            <div class="total-section">
                <div class="total">
                    <span>TOTAL:</span>
                    <span>$<?php echo number_format($total, 2); ?></span>
                </div>
            </div>

            <div class="footer">
                <p>¡Gracias por su visita!</p>
                <p>Vuelva pronto</p>
                <p><?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        window.onload = function() {
            window.print();
            setTimeout(function() {
                window.close();
            }, 500);
        };
    </script>
</body>
</html>
