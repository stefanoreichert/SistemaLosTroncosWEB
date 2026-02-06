<?php
require_once 'config.php';
requireAuth();

$nivel = getNivelUsuario();
$usuario = $_SESSION['usuario'] ?? 'Usuario';

// Función para obtener el estado de las mesas
function obtenerEstadoMesas() {
    $conn = getConnection();
    $sql = "SELECT `mesa`, COUNT(*) as items
            FROM `mesa pedido` 
            GROUP BY `mesa`";
    $stmt = $conn->query($sql);
    $mesas = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mesas[$row['mesa']] = $row;
    }
    return $mesas;
}

$estadoMesas = obtenerEstadoMesas();
$libres = 40 - count($estadoMesas);
$ocupadas = count($estadoMesas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Restaurante - Mesas</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="menu-bar">
        <div class="menu-left">
            <span class="menu-title">Sistema Restaurante Los Troncos - <?php echo ucfirst($nivel); ?> (<?php echo $usuario; ?>)</span>
        </div>
        <div class="menu-right">
            <?php if ($nivel === 'admin'): ?>
                <button class="btn btn-sm" onclick="location.href='reportes.php?tipo=dia'">📊 Resumen del Día</button>
                <button class="btn btn-sm" onclick="location.href='reportes.php?tipo=mes'">📅 Resumen del Mes</button>
            <?php endif; ?>
            <button class="btn btn-sm btn-secondary" onclick="if(confirm('¿Desea salir?')) location.href='logout.php'">Salir</button>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <div class="header">
                <h1>Sistema Restaurante - Mesas</h1>
            </div>

            <div class="content-wrapper">
                <div class="mesas-grid">
                    <?php for ($i = 1; $i <= 40; $i++): ?>
                        <?php 
                            $ocupada = isset($estadoMesas[$i]);
                            $clase = $ocupada ? 'mesa-ocupada' : 'mesa-libre';
                            
                            // Según el nivel, diferentes restricciones
                            if ($nivel === 'cocina') {
                                // Cocina solo ve mesas ocupadas
                                $visible = $ocupada;
                                $click = "abrirMesa($i)";
                            } elseif ($nivel === 'mozo') {
                                // Mozo solo puede tomar pedidos (no puede cerrar)
                                $visible = true;
                                $click = "abrirMesa($i)";
                            } else {
                                // Admin tiene acceso completo
                                $visible = true;
                                $click = "abrirMesa($i)";
                            }
                        ?>
                        <?php if ($visible): ?>
                            <div class="mesa-card <?php echo $clase; ?>" 
                                 onclick="<?php echo $click; ?>"
                                 ondblclick="verPedidoRapido(<?php echo $i; ?>)">
                                <div class="mesa-numero">Mesa <?php echo $i; ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <div class="sidebar">
                    <div class="stats-card">
                        <h3>Estadísticas</h3>
                        <div class="stat-item">
                            <span class="stat-label">Mesas libres:</span>
                            <span class="stat-value libre"><?php echo $libres; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Mesas ocupadas:</span>
                            <span class="stat-value ocupada"><?php echo $ocupadas; ?></span>
                        </div>
                        <div class="help-text">
                            <p><strong>Uso:</strong></p>
                            <?php if ($nivel === 'cocina'): ?>
                                <p>• Solo muestra órdenes activas</p>
                                <p>• Click: Ver detalle orden</p>
                                <p>• Nivel: COCINA</p>
                            <?php elseif ($nivel === 'mozo'): ?>
                                <p>• Click: Tomar pedido</p>
                                <p>• Doble click: Ver resumen</p>
                                <p>• Nivel: MOZO</p>
                            <?php else: ?>
                                <p>• Click: Abrir/Editar pedido</p>
                                <p>• Doble click: Ver resumen rápido</p>
                                <p>• Nivel: ADMIN (Acceso completo)</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para vista rápida del pedido -->
    <div id="modalPedido" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal()">&times;</span>
            <h2 id="modalTitulo">Mesa X</h2>
            <div id="modalContenido"></div>
        </div>
    </div>

    <script src="scripts.js"></script>
    <script>
        function abrirMesa(numeroMesa) {
            window.location.href = 'ventana_pedido.php?mesa=' + numeroMesa;
        }

        function verPedidoRapido(numeroMesa) {
            event.stopPropagation();
            fetch('api.php?action=ver_pedido_rapido&mesa=' + numeroMesa)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalTitulo').textContent = 'Mesa ' + numeroMesa;
                        document.getElementById('modalContenido').innerHTML = data.html;
                        document.getElementById('modalPedido').style.display = 'block';
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar el pedido');
                });
        }

        function cerrarModal() {
            document.getElementById('modalPedido').style.display = 'none';
        }

        // Cerrar modal al hacer click fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalPedido');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Recargar cada 30 segundos para actualizar el estado
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
