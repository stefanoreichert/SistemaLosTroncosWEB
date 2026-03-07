<?php
require_once 'config.php';
requireAuth();

$nivel   = getNivelUsuario();
$idOrden = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idOrden <= 0) {
    header('Location: gestionar_delivery.php');
    exit;
}

// Obtener datos de la orden
$conn     = getConnection();
$sqlOrden = "SELECT `do`.*, COALESCE(`u`.`nombre`, 'Desconocido') AS mozo_nombre
             FROM `delivery_ordenes` AS `do`
             LEFT JOIN `usuario` AS `u` ON `do`.`id_mozo` = `u`.`id_usuario`
             WHERE `do`.`id` = ?";
$stmtO    = $conn->prepare($sqlOrden);
$stmtO->execute([$idOrden]);
$orden    = $stmtO->fetch(PDO::FETCH_ASSOC);

if (!$orden || $orden['cerrado']) {
    header('Location: gestionar_delivery.php');
    exit;
}

// Obtener ítems del pedido
$sqlItems = "SELECT `di`.`id`, `di`.`producto_id`, `p`.`nombre`, `di`.`cantidad`,
                    `di`.`precio_unitario`, (`di`.`cantidad` * `di`.`precio_unitario`) AS subtotal
             FROM `delivery_items` AS `di`
             JOIN `productos` AS `p` ON `di`.`producto_id` = `p`.`id`
             WHERE `di`.`id_orden` = ?
             ORDER BY `di`.`fecha_hora`";
$stmtI    = $conn->prepare($sqlItems);
$stmtI->execute([$idOrden]);
$items    = $stmtI->fetchAll(PDO::FETCH_ASSOC);
$total    = array_sum(array_column($items, 'subtotal'));

$nombreDelivery = 'DELIVERY #' . str_pad($idOrden, 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nombreDelivery; ?> — Los Troncos</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Cabecera azul marino para distinguir delivery de mesas */
        .pedido-header { background: #1a237e !important; padding: 16px 20px !important; }
        .pedido-header h1 { color: #fff !important; }
        .pedido-header .btn { border-color: rgba(255,255,255,.5) !important; color: #fff !important; }
        .pedido-header .btn:hover { background: rgba(255,255,255,.15) !important; }
        /* Mobile */
        @media (max-width: 768px) {
            .pedido-header { flex-direction: column; align-items: stretch; }
            .pedido-header h1 { font-size: 15px; }
            .tabla-productos th:nth-child(1),
            .tabla-productos td:nth-child(1),
            .tabla-productos th:nth-child(5),
            .tabla-productos td:nth-child(5) { display: none; }
            .tabla-pedido th:nth-child(3),
            .tabla-pedido td:nth-child(3) { display: none; }
        }
    </style>
</head>
<body>
    <div class="ventana-pedido">
        <div class="pedido-header">
            <h1>🛵 <?php echo $nombreDelivery; ?> — BÚSQUEDA DE PRODUCTOS</h1>
            <button class="btn btn-secondary" onclick="volverMenu()">← Volver a Delivery</button>
        </div>

        <!-- Panel de búsqueda -->
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

        <!-- Dos paneles -->
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
                            <!-- cargado por JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Panel derecho: Pedido delivery -->
            <div class="panel-derecho">
                <h3><?php echo $nombreDelivery; ?></h3>
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
                            <?php foreach ($items as $item): ?>
                                <tr data-item-id="<?php echo $item['id']; ?>">
                                    <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                    <td style="text-align: center;">
                                        <input type="number" class="input-cantidad"
                                               value="<?php echo $item['cantidad']; ?>"
                                               min="1"
                                               onchange="actualizarCantidad(<?php echo $item['id']; ?>, this.value)">
                                    </td>
                                    <td>$<?php echo number_format($item['precio_unitario'], 2); ?></td>
                                    <td class="subtotal">$<?php echo number_format($item['subtotal'], 2); ?></td>
                                    <td>
                                        <button class="btn-icon btn-danger"
                                                onclick="eliminarItem(<?php echo $item['id']; ?>)">🗑️</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="total-panel">
                    <h2>TOTAL: <span id="totalPedido">$<?php echo number_format($total, 2); ?></span></h2>
                </div>

                <!-- Notas / Observaciones -->
                <div style="margin-top: 20px; background:#f8f9fa; border-radius:10px; padding:20px;">
                    <h3 style="color:#2c3e50; margin-bottom:12px; font-size:18px;">📝 Notas / Observaciones</h3>
                    <textarea id="notasDelivery" rows="4" class="form-control"
                              placeholder="Ej: sin cebolla, empanada al horno, sin sal..."
                              style="resize:vertical; font-size:14px;"><?php echo htmlspecialchars($orden['notas'] ?? ''); ?></textarea>
                    <button class="btn btn-info" style="margin-top:10px; width:100%;" onclick="guardarNotas()">
                        💾 Guardar Notas
                    </button>
                </div>

                <!-- Acciones — idéntico a ventana_pedido -->
                <div class="acciones-panel" style="margin-top:15px;">
                    <?php if ($nivel === 'admin'): ?>
                        <button class="btn btn-danger btn-lg" onclick="cerrarDelivery()">
                            ✅ Cerrar Delivery
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-warning btn-lg" onclick="cancelarDelivery()">
                        🗑️ Cancelar Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const idOrden = <?php echo $idOrden; ?>;
        let productos = [];
        let productoSeleccionado = null;

        // ── Cargar productos ──────────────────────────────────────
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
                .catch(error => console.error('Error en fetch:', error));
        }

        function filtrarProductos() {
            const busqueda = document.getElementById('busquedaRapida').value.toLowerCase();
            const filtrados = productos.filter(p => !busqueda || p.nombre.toLowerCase().includes(busqueda));
            mostrarProductos(filtrados);
        }

        function mostrarProductos(prods) {
            const tbody = document.getElementById('productosBody');
            tbody.innerHTML = '';
            prods.forEach(p => {
                const tr = document.createElement('tr');
                tr.className = 'producto-row';
                tr.onclick  = () => seleccionarProducto(p);
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
            document.querySelectorAll('.producto-row').forEach(row => row.classList.remove('seleccionado'));
            event.currentTarget.classList.add('seleccionado');
            productoSeleccionado = producto;
        }

        function agregarProductoSeleccionado() {
            if (!productoSeleccionado) { alert('Seleccione un producto primero'); return; }
            agregarProductoDirecto(productoSeleccionado.id);
        }

        function agregarProductoDirecto(productoId) {
            const cantidad = document.getElementById('cantidad').value;
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'agregar_item_delivery', id_orden: idOrden, producto_id: productoId, cantidad })
            })
            .then(r => r.json())
            .then(data => { if (data.success) location.reload(); else alert(data.message); });
        }

        function actualizarCantidad(itemId, cantidad) {
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'actualizar_cantidad_delivery', item_id: itemId, cantidad: parseInt(cantidad) })
            })
            .then(r => r.json())
            .then(data => { if (data.success) location.reload(); });
        }

        function eliminarItem(itemId) {
            if (!confirm('¿Eliminar este producto del pedido?')) return;
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'eliminar_item_delivery', item_id: itemId })
            })
            .then(r => r.json())
            .then(data => { if (data.success) location.reload(); else alert(data.message); });
        }

        function guardarNotas() {
            const notas = document.getElementById('notasDelivery').value;
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'guardar_cliente_delivery', id_orden: idOrden, notas })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const btn = document.querySelector('button[onclick="guardarNotas()"]');
                    const orig = btn.textContent;
                    btn.textContent = '✅ Guardado';
                    btn.style.background = '#28a745';
                    setTimeout(() => { btn.textContent = orig; btn.style.background = ''; }, 2000);
                }
            })
            .catch(() => {});
        }

        function cerrarDelivery() {
            const rows = document.getElementById('pedidoBody').querySelectorAll('tr');
            if (rows.length === 0) { alert('El pedido está vacío. Agregue productos antes de cerrar.'); return; }
            if (!confirm('¿Cerrar y cobrar el pedido <?php echo addslashes($nombreDelivery); ?>?')) return;
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cerrar_delivery', id_orden: idOrden })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) { alert('Delivery cerrado correctamente.'); window.location.href = 'gestionar_delivery.php'; }
                else alert('Error: ' + (data.message || ''));
            });
        }

        function cancelarDelivery() {
            if (!confirm('¿Cancelar y eliminar este pedido de delivery?')) return;
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cancelar_delivery', id_orden: idOrden })
            })
            .then(r => r.json())
            .then(data => { if (data.success) window.location.href = 'gestionar_delivery.php'; });
        }

        function volverMenu() {
            window.location.href = 'gestionar_delivery.php';
        }

        document.addEventListener('DOMContentLoaded', cargarProductos);
    </script>

    <footer class="footer-global">
        Sistema de Gestión de Restaurante &mdash; Versión 1.0
    </footer>
</body>
</html>
