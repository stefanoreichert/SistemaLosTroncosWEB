<?php
require_once 'config.php';
requireAuth();

$numeroMesa = isset($_GET['mesa']) ? (int)$_GET['mesa'] : 1;
$nivel = getNivelUsuario();

// Si es cocina, redirigir a la vista especializada de cocina
if ($nivel === 'cocina') {
    header('Location: vista_cocina.php?mesa=' . $numeroMesa);
    exit;
}

// Obtener pedido de la mesa
function obtenerPedidoMesa($mesa) {
    $conn = getConnection();
    $sql = "SELECT `mp`.`mesa`, `mp`.`producto_id`, `p`.`nombre`, `mp`.`cantidad`, `mp`.`precio_unitario`, 
                   (`mp`.`cantidad` * `mp`.`precio_unitario`) as subtotal
            FROM `mesa pedido` AS `mp`
            JOIN `productos` AS `p` ON `mp`.`producto_id` = `p`.`id`
            WHERE `mp`.`mesa` = ?
            ORDER BY `mp`.`fecha_hora`";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$mesa]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pedido = obtenerPedidoMesa($numeroMesa);
$total = array_sum(array_column($pedido, 'subtotal'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesa <?php echo $numeroMesa; ?> - Gestión de Pedido</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="ventana-pedido">
        <div class="pedido-header">
            <h1>MESA <?php echo $numeroMesa; ?> - BÚSQUEDA DE PRODUCTOS</h1>
            <button class="btn btn-secondary" onclick="volverMenu()">← Volver al Menú</button>
        </div>

        <!-- Panel de búsqueda y filtros -->
        <div class="filtros-panel">
            <div class="form-row">
                <div class="form-group flex-grow">
                    <label>Buscar producto:</label>
                    <input type="text" id="busquedaRapida" class="form-control" 
                           placeholder="Escriba el nombre..." 
                           onkeyup="filtrarProductos()">
                </div>

                <div class="form-group">
                    <label>Cantidad:</label>
                    <input type="number" id="cantidad" class="form-control" 
                           value="1" min="1" style="width: 80px;">
                </div>
            </div>

            <div class="form-row">
                <button class="btn btn-success" onclick="agregarProductoSeleccionado()">
                    ➕ Agregar Producto
                </button>
            </div>
        </div>

        <!-- Contenedor principal con dos paneles -->
        <div class="paneles-container">
            <!-- Panel izquierdo: Productos disponibles -->
            <div class="panel-izquierdo">
                <h3>Productos Disponibles</h3>
                <div class="tabla-container">
                    <table id="tablaProductos" class="tabla-productos">
                        <thead>
                            <tr>
                                <th width="50px">ID</th>
                                <th>Nombre</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th width="120px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="productosBody">
                            <!-- Cargado por JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Panel derecho: Pedido actual -->
            <div class="panel-derecho">
                <h3>Pedido Mesa <?php echo $numeroMesa; ?></h3>
                <div class="tabla-container">
                    <table id="tablaPedido" class="tabla-pedido">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th width="80px">Cant.</th>
                                <th width="100px">Precio</th>
                                <th width="100px">Subtotal</th>
                                <th width="80px">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="pedidoBody">
                            <?php foreach ($pedido as $item): ?>
                                <tr data-mesa="<?php echo $item['mesa']; ?>" data-producto="<?php echo $item['producto_id']; ?>">
                                    <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                    <td style="text-align: center;">
                                        <input type="number" class="input-cantidad" 
                                               value="<?php echo $item['cantidad']; ?>"
                                               min="1"
                                               onchange="actualizarCantidad(<?php echo $item['mesa']; ?>, <?php echo $item['producto_id']; ?>, this.value)">
                                    </td>
                                    <td>$<?php echo number_format($item['precio_unitario'], 2); ?></td>
                                    <td class="subtotal">$<?php echo number_format($item['subtotal'], 2); ?></td>
                                    <td>
                                        <button class="btn-icon btn-danger" 
                                                onclick="eliminarItemPedido(<?php echo $item['mesa']; ?>, <?php echo $item['producto_id']; ?>)">
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="total-panel">
                    <h2>TOTAL: <span id="totalPedido">$<?php echo number_format($total, 2); ?></span></h2>
                </div>

                <div class="acciones-panel">
                    <?php if ($nivel === 'admin'): ?>
                        <button class="btn btn-primary btn-lg" onclick="imprimirTicket()">
                            🖨️ Imprimir Ticket
                        </button>
                        <button class="btn btn-danger btn-lg" onclick="cerrarMesa()">
                            ✅ Cerrar Mesa
                        </button>
                        <button class="btn btn-warning btn-lg" onclick="borrarPedido()">
                            🗑️ Borrar Pedido
                        </button>
                    <?php elseif ($nivel === 'mozo'): ?>
                        <button class="btn btn-primary btn-lg" onclick="imprimirTicket()">
                            🖨️ Ver Pedido
                        </button>
                        <button class="btn btn-warning btn-lg" onclick="borrarPedido()">
                            🗑️ Borrar Pedido
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const numeroMesa = <?php echo $numeroMesa; ?>;
        let productos = [];
        let productoSeleccionado = null;

        // Cargar productos al iniciar
        document.addEventListener('DOMContentLoaded', () => {
            cargarProductos();
        });

        function volverMenu() {
            window.location.href = 'menu_principal.php';
        }

        function cargarProductos() {
            fetch('api.php?action=obtener_productos')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        productos = data.productos;
                        filtrarProductos();
                    } else {
                        console.error('Error al cargar productos:', data);
                    }
                })
                .catch(error => {
                    console.error('Error en fetch:', error);
                });
        }

        function filtrarProductos() {
            const busqueda = document.getElementById('busquedaRapida').value.toLowerCase();

            const productosFiltrados = productos.filter(p => {
                return !busqueda || p.nombre.toLowerCase().includes(busqueda);
            });

            mostrarProductos(productosFiltrados);
        }

        function mostrarProductos(prods) {
            const tbody = document.getElementById('productosBody');
            tbody.innerHTML = '';

            prods.forEach(p => {
                const tr = document.createElement('tr');
                tr.className = 'producto-row';
                tr.onclick = () => seleccionarProducto(p);
                tr.ondblclick = () => agregarProductoDirecto(p.id);
                tr.innerHTML = `
                    <td>${p.id}</td>
                    <td>${p.nombre}</td>
                    <td>$${parseFloat(p.precio).toFixed(2)}</td>
                    <td>${p.stock}</td>
                    <td>
                        <button class="btn-icon" onclick="event.stopPropagation()">✏️</button>
                        <button class="btn-icon btn-danger" onclick="event.stopPropagation()">🗑️</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function seleccionarProducto(producto) {
            document.querySelectorAll('.producto-row').forEach(row => {
                row.classList.remove('seleccionado');
            });
            event.currentTarget.classList.add('seleccionado');
            productoSeleccionado = producto;
        }

        function agregarProductoSeleccionado() {
            if (!productoSeleccionado) {
                alert('Seleccione un producto primero');
                return;
            }
            agregarProductoDirecto(productoSeleccionado.id);
        }

        function agregarProductoDirecto(productoId) {
            const cantidad = document.getElementById('cantidad').value;

            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'agregar_pedido',
                    mesa: numeroMesa,
                    producto_id: productoId,
                    cantidad: cantidad
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }

        function actualizarCantidad(mesa, productoId, cantidad) {
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'actualizar_cantidad',
                    mesa: mesa,
                    producto_id: productoId,
                    cantidad: cantidad
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }

        function eliminarItemPedido(mesa, productoId) {
            if (!confirm('¿Eliminar este producto del pedido?')) return;

            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'eliminar_item',
                    mesa: mesa,
                    producto_id: productoId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }

        function borrarPedido() {
            if (!confirm('¿Borrar todo el pedido de la mesa?')) return;

            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'borrar_pedido',
                    mesa: numeroMesa
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function cerrarMesa() {
            if (!confirm('¿Cerrar la mesa e imprimir ticket?')) return;
            imprimirTicket();
        }

        function imprimirTicket() {
            window.open('imprimir_ticket.php?mesa=' + numeroMesa, '_blank');
        }
    </script>
</body>
</html>
