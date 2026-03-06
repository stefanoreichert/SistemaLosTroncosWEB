<?php
require_once 'config.php'; // Archivo de configuración común para toda la aplicación que se usa para manejar la conexión a la base de datos y la autenticación de usuarios
header('Content-Type: application/json'); // Establece el tipo de contenido a JSON para que el frontend pueda interpretar las respuestas correctamente

// Obtener datos de la petición JSON (si es una petición POST con JSON) o de $_GET/$_POST (para otras peticiones)
$input = file_get_contents('php://input'); // Lee el cuerpo de la petición, que se espera que sea JSON
$data = json_decode($input, true); // Decodifica el JSON en un array asociativo de PHP. Si la decodificación falla, $data será null

// Si no hay datos JSON, usar $_GET y $_POST para obtener los parámetros de la petición (esto permite manejar tanto peticiones AJAX con JSON como peticiones tradicionales)
if (!$data) { // Si $data es null, significa que no se recibió un JSON válido, por lo que se intentará obtener los datos de $_GET y $_POST
    $data = array_merge($_GET, $_POST); // Combina los arrays $_GET y $_POST en uno solo, dando prioridad a los datos de $_POST en caso de conflicto. Esto permite manejar tanto parámetros enviados por URL (GET) como por formulario (POST)
}

$action = $data['action'] ?? $_GET['action'] ?? ''; // Obtiene el parámetro action. Busca primero en $data, luego en $_GET. Si no existe ninguno, asigna vacío ''.

try { // Inicia un bloque try-catch para capturar errores. getConnection() viene de config.php y conecta a la BD.
    $conn = getConnection();
    // Abre un switch. Según el valor de $action, ejecutará diferentes bloques de código (casos).
    switch ($action) { // obtiene el valor dewl action en este cado obtener_productos.
        // Si $action es 'obtener_productos', ejecuta este bloque de código para obtener la lista de productos desde la base de datos.
        case 'obtener_productos': 
            $sql = "SELECT `p`.`id`, `p`.`nombre`, `p`.`precio`, `p`.`stock`,
                           `t`.`Nombre` as tipo, `tp`.`Nombre` as tipo_producto
                    FROM `productos` AS `p`
                    LEFT JOIN `tipo` AS `t` ON `p`.`id_tipo` = `t`.`Id_tipo`
                    LEFT JOIN `tipo producto` AS `tp` ON `p`.`Id_tipo_producto` = `tp`.`Id_tipo_producto`
                    ORDER BY `p`.`nombre`";
            $stmt = $conn->query($sql); // ejecuta la consuta SQL y devuelve un objeto PDOStatement.
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC); // obtiene los resultados del array asociativo (JSON).
            echo json_encode(['success' => true, 'productos' => $productos]); //  Devuelve respuesta JSON con los productos obtenidos. 
            break;
        // Si $action es 'obtener_pedido', ejecuta este bloque de código para obtener los detalles del pedido de una mesa específica.   
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
            echo json_encode(['success' => true, 'message' => 'Producto agregado']); // Devuelve respuesta JSON indicando que el producto fue agregado exitosamente.
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
            echo json_encode(['success' => true, 'message' => 'Producto actualizado']); // Devuelve respuesta JSON indicando que el producto fue actualizado exitosamente.
            break;
            
        case 'eliminar_producto':
            $sql = "DELETE FROM `productos` WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['id']]);
            echo json_encode(['success' => true, 'message' => 'Producto eliminado']); // Devuelve respuesta JSON indicando que el producto fue eliminado exitosamente.
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
                $idMozo = $_SESSION['usuario_id'] ?? null;
                
                $stmt->execute([
                    $data['mesa'],
                    $data['producto_id'],
                    $data['cantidad'],
                    $precio,
                    $idMozo
                ]);
            }
            // Al agregar nuevos productos, se resetea el estado "listo" de la mesa
            $mesaNueva = intval($data['mesa']);
            $sqlReset = "UPDATE `notificaciones` SET `leido` = 1 WHERE `mesa` = ? AND `tipo` = 'pedido_listo' AND `leido` = 0";
            $stmtReset = $conn->prepare($sqlReset);
            $stmtReset->execute([$mesaNueva]);
            echo json_encode(['success' => true, 'message' => 'Producto agregado al pedido']);
            break;
            
        case 'actualizar_cantidad':
            $sql = "UPDATE `mesa pedido` SET `cantidad` = ? WHERE `mesa` = ? AND `producto_id` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['cantidad'], $data['mesa'], $data['producto_id']]);
            echo json_encode(['success' => true, 'message' => 'Cantidad actualizada']); // Devuelve respuesta JSON indicando que la cantidad fue actualizada exitosamente.
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
            // Limpiar estado "listo" al borrar el pedido
            $sqlReset = "UPDATE `notificaciones` SET `leido` = 1 WHERE `mesa` = ? AND `tipo` = 'pedido_listo' AND `leido` = 0";
            $stmtReset = $conn->prepare($sqlReset);
            $stmtReset->execute([intval($data['mesa'])]);
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
            // Limpiar estado "listo" al cerrar la mesa
            $sqlReset = "UPDATE `notificaciones` SET `leido` = 1 WHERE `mesa` = ? AND `tipo` = 'pedido_listo' AND `leido` = 0";
            $stmtReset = $conn->prepare($sqlReset);
            $stmtReset->execute([intval($data['mesa'])]);
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

        case 'guardar_notas':
            $mesa  = intval($data['mesa'] ?? 0);
            $notas = $data['notas'] ?? '';
            $sql   = "UPDATE `mesa pedido` SET `notas` = ? WHERE `mesa` = ?";
            $stmt  = $conn->prepare($sql);
            $stmt->execute([$notas, $mesa]);
            echo json_encode(['success' => true, 'message' => 'Notas guardadas']);
            break;

        case 'obtener_notas':
            $mesa = intval($data['mesa'] ?? $_GET['mesa'] ?? 0);
            $sql  = "SELECT COALESCE(MAX(`notas`), '') as notas FROM `mesa pedido` WHERE `mesa` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$mesa]);
            $row  = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'notas' => $row['notas'] ?? '']);
            break;

        case 'notificar_pedido_listo':
            // Guardar notificación para el mozo (marcar anteriores como leídas para no duplicar)
            $mesa = intval($data['mesa'] ?? 0);
            $sqlOld = "UPDATE `notificaciones` SET `leido` = 1 WHERE `mesa` = ? AND `tipo` = 'pedido_listo' AND `leido` = 0";
            $stmtOld = $conn->prepare($sqlOld);
            $stmtOld->execute([$mesa]);
            // Obtener nombre del mozo dueño de la mesa
            $sqlMozo = "SELECT COALESCE(`u`.`nombre`, 'Sin mozo') as mozo
                        FROM `mesa pedido` AS `mp`
                        LEFT JOIN `usuario` AS `u` ON `mp`.`id_mozo` = `u`.`id_usuario`
                        WHERE `mp`.`mesa` = ? AND `mp`.`id_mozo` IS NOT NULL
                        LIMIT 1";
            $stmtMozo = $conn->prepare($sqlMozo);
            $stmtMozo->execute([$mesa]);
            $rowMozo = $stmtMozo->fetch(PDO::FETCH_ASSOC);
            $nombreMozo = $rowMozo['mozo'] ?? 'Sin mozo';
            $mensaje = 'Mesa ' . $mesa . ' lista — Mozo: ' . $nombreMozo;
            $sql = "INSERT INTO `notificaciones` (`tipo`, `mensaje`, `mesa`, `fecha_hora`, `leido`) 
                    VALUES ('pedido_listo', ?, ?, NOW(), 0)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$mensaje, $mesa]);
            echo json_encode(['success' => true, 'message' => 'Mozo notificado', 'mozo' => $nombreMozo]);
            break;

        case 'obtener_mesas_listas':
            // Devuelve lista de mesas cuyo pedido está listo (notificación no leída) con nombre del mozo
            $sql = "SELECT DISTINCT `mesa`, `mensaje` FROM `notificaciones` WHERE `tipo` = 'pedido_listo' AND `leido` = 0";
            $stmt = $conn->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $mesasListas = array_column($rows, 'mesa');
            $mensajes = [];
            foreach ($rows as $r) { $mensajes[$r['mesa']] = $r['mensaje']; }
            echo json_encode(['success' => true, 'mesas' => $mesasListas, 'mensajes' => $mensajes]);
            break;

        case 'marcar_recogido':
            // El mozo recogió el pedido: marcar notificación como leída
            $mesa = intval($data['mesa'] ?? 0);
            $sql = "UPDATE `notificaciones` SET `leido` = 1 WHERE `mesa` = ? AND `tipo` = 'pedido_listo' AND `leido` = 0";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$mesa]);
            echo json_encode(['success' => true]);
            break;

        case 'obtener_estadisticas':
            // Mesas ocupadas
            $stmtOc = $conn->query("SELECT `mesa` FROM `mesa pedido` GROUP BY `mesa`");
            $mesasOcupadas = array_column($stmtOc->fetchAll(PDO::FETCH_ASSOC), 'mesa');
            $totalOcupadas = count($mesasOcupadas);
            $totalLibres   = 40 - $totalOcupadas;

            // Mesas listas (notificaciones no leídas)
            $stmtLis = $conn->query("SELECT DISTINCT `mesa`, `mensaje` FROM `notificaciones` WHERE `tipo` = 'pedido_listo' AND `leido` = 0");
            $rowsListas = $stmtLis->fetchAll(PDO::FETCH_ASSOC);
            $mesasListas = array_column($rowsListas, 'mesa');
            $mensajes    = [];
            foreach ($rowsListas as $r) { $mensajes[$r['mesa']] = $r['mensaje']; }

            // Mozos activos (con al menos una mesa abierta)
            $stmtMoz = $conn->query("SELECT COUNT(DISTINCT `id_mozo`) as total FROM `mesa pedido` WHERE `id_mozo` IS NOT NULL");
            $mozosActivos = intval($stmtMoz->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            echo json_encode([
                'success'       => true,
                'libres'        => $totalLibres,
                'ocupadas'      => $totalOcupadas,
                'listas'        => count($mesasListas),
                'mozos_activos' => $mozosActivos,
                'mesas_ocupadas'=> $mesasOcupadas,
                'mesas_listas'  => $mesasListas,
                'mensajes'      => $mensajes,
            ]);
            break;

        // ===== CERRAR DÍA (solo admin) =====
        // Guarda en resumenes_diarios todas las mesas con pedido activo y las vacía
        case 'cerrar_dia':
            if (!esAdmin()) { echo json_encode(['success' => false, 'message' => 'Acceso denegado']); break; }

            // Obtener todas las mesas con pedidos activos
            $sqlMesas = "SELECT `mp`.`mesa`,
                                SUM(`mp`.`cantidad` * `mp`.`precio_unitario`) AS total,
                                GROUP_CONCAT(`p`.`nombre` ORDER BY `p`.`nombre` SEPARATOR ', ') AS productos
                         FROM `mesa pedido` AS `mp`
                         JOIN `productos` AS `p` ON `mp`.`producto_id` = `p`.`id`
                         GROUP BY `mp`.`mesa`";
            $stmtMesas = $conn->query($sqlMesas);
            $mesasAbiertas = $stmtMesas->fetchAll(PDO::FETCH_ASSOC);

            if (count($mesasAbiertas) === 0) {
                echo json_encode(['success' => false, 'message' => 'No hay mesas abiertas para cerrar']);
                break;
            }

            $fecha = date('Y-m-d');
            $hora  = date('H:i:s');
            $sqlIns = "INSERT INTO `resumenes_diarios` (`fecha`, `hora`, `mesa`, `total`, `productos`)
                       VALUES (?, ?, ?, ?, ?)";
            $stmtIns = $conn->prepare($sqlIns);

            foreach ($mesasAbiertas as $m) {
                if (floatval($m['total']) > 0) {
                    $stmtIns->execute([$fecha, $hora, $m['mesa'], $m['total'], $m['productos']]);
                }
            }

            // Limpiar mesa pedido
            $conn->exec("DELETE FROM `mesa pedido`");
            // Marcar notificaciones como leídas
            $conn->exec("UPDATE `notificaciones` SET `leido` = 1 WHERE `tipo` = 'pedido_listo' AND `leido` = 0");

            $qty = count($mesasAbiertas);
            echo json_encode(['success' => true, 'message' => "Día cerrado correctamente. $qty mesa(s) guardadas."]);
            break;

        // ===== CRUD DE USUARIOS (solo admin) =====
        case 'obtener_usuarios':            if (!esAdmin()) { echo json_encode(['success' => false, 'message' => 'Acceso denegado']); break; }
            $sql = "SELECT `id_usuario`, `nombre`, `nivel` FROM `usuario` ORDER BY `nombre`";
            $stmt = $conn->query($sql);
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'usuarios' => $usuarios]);
            break;

        case 'agregar_usuario':
            if (!esAdmin()) { echo json_encode(['success' => false, 'message' => 'Acceso denegado']); break; }
            $nombre    = trim($data['nombre'] ?? '');
            $password  = trim($data['password'] ?? '');
            $nivel     = trim($data['nivel'] ?? 'mozo');
            if ($nombre === '' || $password === '') {
                echo json_encode(['success' => false, 'message' => 'Nombre y contraseña son obligatorios']);
                break;
            }
            // Verificar que no exista el nombre
            $check = $conn->prepare("SELECT COUNT(*) FROM `usuario` WHERE LOWER(`nombre`) = LOWER(?)");
            $check->execute([$nombre]);
            if ($check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe un usuario con ese nombre']);
                break;
            }
            $sql  = "INSERT INTO `usuario` (`nombre`, `contraseña`, `nivel`) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nombre, $password, $nivel]);
            echo json_encode(['success' => true, 'message' => 'Usuario creado correctamente', 'id' => $conn->lastInsertId()]);
            break;

        case 'actualizar_usuario':
            if (!esAdmin()) { echo json_encode(['success' => false, 'message' => 'Acceso denegado']); break; }
            $id       = intval($data['id_usuario'] ?? 0);
            $nombre   = trim($data['nombre'] ?? '');
            $nivel    = trim($data['nivel'] ?? 'mozo');
            $password = trim($data['password'] ?? '');
            if ($id === 0 || $nombre === '') {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                break;
            }
            // Verificar nombre duplicado (excluyendo el propio usuario)
            $check = $conn->prepare("SELECT COUNT(*) FROM `usuario` WHERE LOWER(`nombre`) = LOWER(?) AND `id_usuario` != ?");
            $check->execute([$nombre, $id]);
            if ($check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe otro usuario con ese nombre']);
                break;
            }
            if ($password !== '') {
                $sql  = "UPDATE `usuario` SET `nombre` = ?, `contraseña` = ?, `nivel` = ? WHERE `id_usuario` = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nombre, $password, $nivel, $id]);
            } else {
                $sql  = "UPDATE `usuario` SET `nombre` = ?, `nivel` = ? WHERE `id_usuario` = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nombre, $nivel, $id]);
            }
            echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
            break;

        case 'eliminar_usuario':
            if (!esAdmin()) { echo json_encode(['success' => false, 'message' => 'Acceso denegado']); break; }
            $id = intval($data['id_usuario'] ?? 0);
            // No permitir eliminar al usuario actualmente autenticado
            if ($id === intval($_SESSION['usuario_id'] ?? 0)) {
                echo json_encode(['success' => false, 'message' => 'No puedes eliminar tu propia cuenta']);
                break;
            }
            $sql  = "DELETE FROM `usuario` WHERE `id_usuario` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
