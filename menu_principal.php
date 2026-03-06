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

// Obtener mesas cuyo pedido está listo (notificación sin leer)
function obtenerMesasListas() {
    $conn = getConnection();
    $sql = "SELECT DISTINCT `mesa`, `mensaje` FROM `notificaciones` WHERE `tipo` = 'pedido_listo' AND `leido` = 0";
    $stmt = $conn->query($sql);
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[$row['mesa']] = $row['mensaje'];
    }
    return $result;
}
$mesasListas = obtenerMesasListas(); // [numeroMesa => mensaje]

// Contar mozos que tienen al menos una mesa con pedido abierto
function obtenerMozosActivos() {
    $conn = getConnection();
    $sql = "SELECT COUNT(DISTINCT `id_mozo`) as total
            FROM `mesa pedido`
            WHERE `id_mozo` IS NOT NULL";
    $row = $conn->query($sql)->fetch(PDO::FETCH_ASSOC);
    return intval($row['total'] ?? 0);
}

$mozosActivos = obtenerMozosActivos();
$cantListas   = count($mesasListas);
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
                <button class="btn btn-sm" onclick="location.href='reportes.php?tipo=dia'">Resumen del Día</button>
                <button class="btn btn-sm" onclick="location.href='reportes.php?tipo=mes'">Resumen del Mes</button>
                <button class="btn btn-sm" onclick="location.href='usuarios.php'">Gestión de Usuarios</button>
            <?php endif; ?>
            <?php if ($nivel === 'admin' || $nivel === 'cocina'): ?>
                <button class="btn btn-sm" onclick="location.href='vista_cocina.php'">Panel Cocina</button>
            <?php endif; ?>
            <button class="btn btn-sm" onclick="location.href='perfil.php'">Perfil</button>
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
                            $lista   = isset($mesasListas[$i]);
                            $mensajeLista = $mesasListas[$i] ?? '';
                            if ($lista) {
                                $clase = 'mesa-lista';
                            } else {
                                $clase = $ocupada ? 'mesa-ocupada' : 'mesa-libre';
                            }
                            
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
                            <div id="mesa-card-<?php echo $i; ?>" class="mesa-card <?php echo $clase; ?>" 
                                 onclick="<?php echo $click; ?>"
                                 ondblclick="verPedidoRapido(<?php echo $i; ?>)">
                                <div class="mesa-numero">Mesa <?php echo $i; ?></div>
                                <?php if ($lista): ?>
                                    <div class="mesa-estado-listo">Listo</div>
                                    <?php
                                    // Extraer nombre del mozo del mensaje
                                    preg_match('/Mozo: (.+)$/', $mensajeLista, $matches);
                                    $mozoNombre = $matches[1] ?? '';
                                    if ($mozoNombre): ?>
                                    <div class="mesa-mozo-listo">👤 <?php echo htmlspecialchars($mozoNombre); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <div class="sidebar">
                    <div class="stats-card">
                        <h3>Estadísticas</h3>
                        <div class="stat-item">
                            <span class="stat-label">Mesas libres:</span>
                            <span class="stat-value libre" id="stat-libres"><?php echo $libres; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Mesas ocupadas:</span>
                            <span class="stat-value ocupada" id="stat-ocupadas"><?php echo $ocupadas; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Mesas listas:</span>
                            <span class="stat-value" style="color:#e65100;" id="stat-listas"><?php echo $cantListas; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Mozos activos:</span>
                            <span class="stat-value" style="color:#1565c0;" id="stat-mozos"><?php echo $mozosActivos; ?></span>
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
            // Navegar directamente sin limpiar el estado “listo”;
            // el estado solo se limpia cuando el mozo agrega productos o cierra el pedido.
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

        // Polling cada 5 segundos: actualiza mesas listas y estadísticas
        function actualizarEstadisticas() {
            fetch('api.php?action=obtener_estadisticas')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;

                    // Actualizar contadores del sidebar
                    document.getElementById('stat-libres').textContent   = data.libres;
                    document.getElementById('stat-ocupadas').textContent  = data.ocupadas;
                    document.getElementById('stat-listas').textContent    = data.listas;
                    document.getElementById('stat-mozos').textContent     = data.mozos_activos;

                    // Actualizar tarjetas de mesas
                    const mesasListas = (data.mesas_listas || []).map(Number);
                    const mesasOcupadas = (data.mesas_ocupadas || []).map(Number);
                    const mensajes = data.mensajes || {};

                    document.querySelectorAll('.mesa-card').forEach(card => {
                        const id = parseInt(card.id.replace('mesa-card-', ''));
                        if (isNaN(id)) return;

                        if (mesasListas.includes(id)) {
                            card.className = 'mesa-card mesa-lista';
                            if (!card.querySelector('.mesa-estado-listo')) {
                                const badge = document.createElement('div');
                                badge.className = 'mesa-estado-listo';
                                badge.textContent = 'Listo';
                                card.appendChild(badge);
                            }
                            let mozoDiv = card.querySelector('.mesa-mozo-listo');
                            const msg = mensajes[id] || '';
                            const match = msg.match(/Mozo: (.+)$/);
                            const mozoNombre = match ? match[1] : '';
                            if (mozoNombre) {
                                if (!mozoDiv) {
                                    mozoDiv = document.createElement('div');
                                    mozoDiv.className = 'mesa-mozo-listo';
                                    card.appendChild(mozoDiv);
                                }
                                mozoDiv.textContent = mozoNombre;
                            }
                        } else if (mesasOcupadas.includes(id)) {
                            card.className = 'mesa-card mesa-ocupada';
                            const badge = card.querySelector('.mesa-estado-listo');
                            if (badge) badge.remove();
                            const mozoDiv = card.querySelector('.mesa-mozo-listo');
                            if (mozoDiv) mozoDiv.remove();
                        } else {
                            card.className = 'mesa-card mesa-libre';
                            const badge = card.querySelector('.mesa-estado-listo');
                            if (badge) badge.remove();
                            const mozoDiv = card.querySelector('.mesa-mozo-listo');
                            if (mozoDiv) mozoDiv.remove();
                        }
                    });
                })
                .catch(() => {});
        }
        setInterval(actualizarEstadisticas, 5000);
    </script>

    <footer class="footer-global">
        Sistema de Gesti&oacute;n de Restaurante &mdash; Versi&oacute;n 1.0
    </footer>
</body>
</html>
