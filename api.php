<?php
require_once 'config.php';
header('Content-Type: application/json');

// Obtener datos de la petición
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Si no hay datos JSON, usar $_GET y $_POST
if (!$data) {
    $data = array_merge($_GET, $_POST);
}

$action = $data['action'] ?? $_GET['action'] ?? '';

try {
    $conn = getConnection();
    
    switch ($action) {
        case 'obtener_productos':
            $sql = "SELECT `p`.`id`, `p`.`nombre`, `p`.`precio`, `p`.`stock`,
                           `t`.`Nombre` as tipo, `tp`.`Nombre` as tipo_producto
                    FROM `productos` AS `p`
                    LEFT JOIN `tipo` AS `t` ON `p`.`id_tipo` = `t`.`Id_tipo`
                    LEFT JOIN `tipo producto` AS `tp` ON `p`.`Id_tipo_producto` = `tp`.`Id_tipo_producto`
                    ORDER BY `p`.`nombre`";
            $stmt = $conn->query($sql);
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'productos' => $productos]);
            break;
            
        case 'agregar_producto':
            $sql = "INSERT INTO `productos` (`nombre`, `id_tipo`, `Id_tipo_producto`, `precio`, `stock`) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['nombre'],
                $data['id_tipo'] ?? 1,
                $data['Id_tipo_producto'] ?? 1,
                $data['precio'],
                $data['stock']
            ]);
            echo json_encode(['success' => true, 'message' => 'Producto agregado']);
            break;
            
        case 'actualizar_producto':
            $sql = "UPDATE `productos` 
                    SET `nombre` = ?, `id_tipo` = ?, `Id_tipo_producto` = ?, `precio` = ?, `stock` = ? 
                    WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['nombre'],
                $data['id_tipo'] ?? 1,
                $data['Id_tipo_producto'] ?? 1,
                $data['precio'],
                $data['stock'],
                $data['id']
            ]);
            echo json_encode(['success' => true, 'message' => 'Producto actualizado']);
            break;
            
        case 'eliminar_producto':
            $sql = "DELETE FROM `productos` WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['id']]);
            echo json_encode(['success' => true, 'message' => 'Producto eliminado']);
            break;
            
        case 'agregar_pedido':
            // Verificar si ya existe el producto en el pedido
            $sql = "SELECT `cantidad` FROM `mesa pedido` 
                    WHERE `mesa` = ? AND `producto_id` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['mesa'], $data['producto_id']]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existe) {
                // Actualizar cantidad
                $sql = "UPDATE `mesa pedido` 
                        SET `cantidad` = `cantidad` + ? 
                        WHERE `mesa` = ? AND `producto_id` = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$data['cantidad'], $data['mesa'], $data['producto_id']]);
            } else {
                // Insertar nuevo con id_mozo
                $sql = "INSERT INTO `mesa pedido` (`mesa`, `producto_id`, `cantidad`, `precio_unitario`, `id_mozo`) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                // Obtener el precio del producto
                $precioStmt = $conn->prepare("SELECT `precio` FROM `productos` WHERE `id` = ?");
                $precioStmt->execute([$data['producto_id']]);
                $producto = $precioStmt->fetch(PDO::FETCH_ASSOC);
                $precio = $producto['precio'] ?? 0;
                
                // Obtener id del mozo actual
                $idMozo = $_SESSION['id_usuario'] ?? null;
                
                $stmt->execute([
                    $data['mesa'],
                    $data['producto_id'],
                    $data['cantidad'],
                    $precio,
                    $idMozo
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'Producto agregado al pedido']);
            break;
            
        case 'actualizar_cantidad':
            $sql = "UPDATE `mesa pedido` SET `cantidad` = ? WHERE `mesa` = ? AND `producto_id` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['cantidad'], $data['mesa'], $data['producto_id']]);
            echo json_encode(['success' => true, 'message' => 'Cantidad actualizada']);
            break;
            
        case 'eliminar_item':
            $sql = "DELETE FROM `mesa pedido` WHERE `mesa` = ? AND `producto_id` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['mesa'], $data['producto_id']]);
            echo json_encode(['success' => true, 'message' => 'Item eliminado']);
            break;
            
        case 'borrar_pedido':
            $sql = "DELETE FROM `mesa pedido` WHERE `mesa` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['mesa']]);
            echo json_encode(['success' => true, 'message' => 'Pedido borrado']);
            break;
            
        case 'cerrar_mesa':
            // Descontar stock
            $sql = "SELECT `mp`.`producto_id`, `mp`.`cantidad` 
                    FROM `mesa pedido` AS `mp` 
                    WHERE `mp`.`mesa` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['mesa']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                $sql = "UPDATE `productos` 
                        SET `stock` = `stock` - ? 
                        WHERE `id` = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$item['cantidad'], $item['producto_id']]);
            }
            
            // Borrar pedido
            $sql = "DELETE FROM `mesa pedido` WHERE `mesa` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['mesa']]);
            
            echo json_encode(['success' => true, 'message' => 'Mesa cerrada']);
            break;
            
        case 'ver_pedido_rapido':
            $mesa = $_GET['mesa'] ?? 0;
            
            $sql = "SELECT `p`.`nombre`, `mp`.`cantidad`, `mp`.`precio_unitario`, 
                           (`mp`.`cantidad` * `mp`.`precio_unitario`) as subtotal
                    FROM `mesa pedido` AS `mp`
                    JOIN `productos` AS `p` ON `mp`.`producto_id` = `p`.`id`
                    WHERE `mp`.`mesa` = ?
                    ORDER BY `mp`.`fecha_hora`";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$mesa]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($items) == 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'La mesa no tiene pedidos'
                ]);
                break;
            }
            
            $html = '<div class="pedido-rapido">';
            $html .= '<table class="tabla-resumen">';
            $html .= '<thead><tr><th>Producto</th><th>Cant.</th><th>Precio Unit.</th><th>Subtotal</th></tr></thead>';
            $html .= '<tbody>';
            
            $total = 0;
            foreach ($items as $item) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($item['nombre']) . '</td>';
                $html .= '<td>' . $item['cantidad'] . '</td>';
                $html .= '<td>$' . number_format($item['precio_unitario'], 2) . '</td>';
                $html .= '<td>$' . number_format($item['subtotal'], 2) . '</td>';
                $html .= '</tr>';
                $total += $item['subtotal'];
            }
            
            $html .= '</tbody>';
            $html .= '<tfoot><tr><th colspan="3">TOTAL</th><th>$' . number_format($total, 2) . '</th></tr></tfoot>';
            $html .= '</table>';
            $html .= '</div>';
            
            echo json_encode(['success' => true, 'html' => $html]);
            break;
            
        case 'obtener_resumen_dia':
            $fecha = date('Y-m-d');
            $sql = "SELECT `p`.`nombre`, `mp`.`cantidad`, `mp`.`precio_unitario`, 
                           (`mp`.`cantidad` * `mp`.`precio_unitario`) as subtotal
                    FROM `mesa pedido` AS `mp`
                    JOIN `productos` AS `p` ON `mp`.`producto_id` = `p`.`id`
                    WHERE DATE(`mp`.`fecha_hora`) = ?
                    ORDER BY `mp`.`mesa`, `mp`.`fecha_hora`";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$fecha]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total = array_sum(array_column($items, 'subtotal'));
            
            echo json_encode([
                'success' => true,
                'items' => $items,
                'total' => $total,
                'fecha' => $fecha
            ]);
            break;
            
        case 'obtener_resumen_mes':
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
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total = array_sum(array_column($items, 'subtotal'));
            
            echo json_encode([
                'success' => true,
                'items' => $items,
                'total' => $total,
                'mes' => $mes
            ]);
            break;
            
        case 'limpiar_dia':
            $fecha = date('Y-m-d');
            $sql = "DELETE FROM `mesa pedido` WHERE DATE(`fecha_hora`) = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$fecha]);
            echo json_encode(['success' => true, 'message' => 'Pedidos del día eliminados']);
            break;
            
        case 'limpiar_mes':
            $mes = date('Y-m');
            $sql = "DELETE FROM `mesa pedido` WHERE DATE_FORMAT(`fecha_hora`, '%Y-%m') = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$mes]);
            echo json_encode(['success' => true, 'message' => 'Pedidos del mes eliminados']);
            break;

        case 'notificar_pedido_listo':
            // Guardar notificación para el mozo
            $mesa = $data['mesa'] ?? 0;
            $sql = "INSERT INTO `notificaciones` (`tipo`, `mensaje`, `mesa`, `fecha_hora`, `leido`) 
                    VALUES ('pedido_listo', 'Pedido de mesa " . intval($mesa) . " listo', ?, NOW(), 0)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$mesa]);
            echo json_encode(['success' => true, 'message' => 'Mozo notificado']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
