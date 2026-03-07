<?php
require_once 'config.php';
requireAuth();

$nivel   = getNivelUsuario();
$usuario = $_SESSION['usuario'] ?? 'Usuario';

// Obtener todos los deliveries activos (mesa >= DELIVERY_BASE+1 en mesa pedido)
function obtenerDeliveriesActivos() {
    $conn = getConnection();
    $base = DELIVERY_BASE;
    $sql  = "SELECT mp.mesa,
                    COALESCE(u.nombre, 'Desconocido') AS mozo_nombre,
                    MIN(mp.fecha_hora)                  AS hora_recibido,
                    SUM(mp.cantidad * mp.precio_unitario) AS total,
                    SUM(mp.cantidad)                   AS cant_items
             FROM `mesa pedido` AS mp
             LEFT JOIN `usuario` AS u ON mp.id_mozo = u.id_usuario
             WHERE mp.mesa >= :base + 1
             GROUP BY mp.mesa, u.nombre
             ORDER BY MIN(mp.fecha_hora) ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':base' => $base]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$deliveries = obtenerDeliveriesActivos();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Delivery — Los Troncos</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .delivery-header-bar {
            background: #1a237e;
            color: #fff;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 56px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0,0,0,.25);
        }
        .delivery-header-bar h1 { font-size: 1.25rem; font-weight: 700; letter-spacing: 1px; }
        .header-btns { display: flex; gap: 10px; }
        .btn-header {
            background: rgba(255,255,255,.15);
            border: 2px solid rgba(255,255,255,.3);
            color: #fff;
            padding: 7px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: .88rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-header:hover { background: rgba(255,255,255,.28); }
        .btn-header.primary { background: #ff6f00; border-color: #ff6f00; }
        .btn-header.primary:hover { background: #e65100; }

        /* === RESPONSIVE DELIVERY GESTIÓN === */
        @media (max-width: 768px) {
            .delivery-header-bar {
                height: auto;
                padding: 12px 14px;
                flex-wrap: wrap;
                gap: 10px;
            }
            .delivery-header-bar h1 { font-size: 1rem; width: 100%; }
            .header-btns { width: 100%; justify-content: space-between; }
            .btn-header { flex: 1; text-align: center; padding: 10px 8px; font-size: .85rem; }
            .delivery-content { padding: 0 12px; margin: 16px auto; }
            .delivery-grid { grid-template-columns: 1fr; gap: 14px; }
            .delivery-card-header { padding: 14px 16px 12px; }
            .delivery-id { font-size: 1.6rem; }
        }

        .delivery-content {
            max-width: 1300px;
            margin: 28px auto;
            padding: 0 20px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a237e;
            margin-bottom: 18px;
            padding-bottom: 8px;
            border-bottom: 3px solid #1a237e;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Grid de tarjetas */
        .delivery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .delivery-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #dee2e6;
            box-shadow: 0 4px 18px rgba(0,0,0,.08);
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
            display: flex;
            flex-direction: column;
        }
        .delivery-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,.14);
        }

        .delivery-card-header {
            padding: 18px 20px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .delivery-card-header.recibido  { background: #1565c0; }
        .delivery-card-header.preparando { background: #e65100; }
        .delivery-card-header.listo     { background: #2e7d32; }

        .delivery-id {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 2px;
        }
        .delivery-estado-badge {
            background: rgba(255,255,255,.2);
            color: #fff;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: .82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .delivery-card-body {
            padding: 16px 20px;
            flex: 1;
        }
        .card-info-row {
            display: flex;
            align-items: center;
            font-size: .9rem;
            color: #444;
            margin-bottom: 8px;
            gap: 8px;
        }
        .card-info-row .lbl {
            color: #999;
            font-size: .79rem;
            text-transform: uppercase;
            letter-spacing: .4px;
            min-width: 68px;
        }
        .card-info-row .val { font-weight: 600; }
        .card-info-row .val.sin { color: #aaa; font-style: italic; font-weight: 400; }

        .delivery-card-footer {
            border-top: 1px solid #eee;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8f9fa;
        }
        .delivery-total { font-weight: 700; font-size: 1.1rem; color: #1a237e; }
        .btn-gestionar {
            background: #1a237e;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: .88rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }
        .btn-gestionar:hover { background: #0d47a1; }

        /* Estado vacío */
        .sin-deliveries {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #888;
            font-size: 1.1rem;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,.07);
        }
        .sin-deliveries .icon { font-size: 3rem; margin-bottom: 14px; }
    </style>
</head>
<body>

<div class="delivery-header-bar">
    <h1>🛵 GESTIÓN DE DELIVERY</h1>
    <div class="header-btns">
        <button class="btn-header primary" onclick="nuevoDelivery()">+ Nuevo Pedido Delivery</button>
        <a href="menu_principal.php" class="btn-header">← Menú Principal</a>
    </div>
</div>

<div class="delivery-content">
    <div class="section-title">Pedidos Delivery Activos (<?php echo count($deliveries); ?>)</div>

    <div class="delivery-grid" id="deliveryGrid">
        <?php if (empty($deliveries)): ?>
        <div class="sin-deliveries">
            <div class="icon">🛵</div>
            <p>No hay pedidos de delivery activos.</p>
            <p style="margin-top:10px; font-size:.95rem;">Haga clic en <strong>+ Nuevo Pedido Delivery</strong> para crear uno.</p>
        </div>
        <?php else: ?>
            <?php foreach ($deliveries as $d):
                $numDel  = $d['mesa'] - DELIVERY_BASE;
                $horaRec = date('H:i', strtotime($d['hora_recibido']));
            ?>
            <div class="delivery-card" onclick="abrirDelivery(<?php echo $d['mesa']; ?>)">
                <div class="delivery-card-header" style="background:#1a237e;">
                    <div class="delivery-id">DELIVERY <?php echo $numDel; ?></div>
                </div>
                <div class="delivery-card-body">
                    <div class="card-info-row">
                        <span class="lbl">Ingresó:</span>
                        <span class="val"><?php echo $horaRec; ?>h</span>
                    </div>
                    <div class="card-info-row">
                        <span class="lbl">Mozo:</span>
                        <span class="val"><?php echo htmlspecialchars($d['mozo_nombre']); ?></span>
                    </div>
                    <div class="card-info-row">
                        <span class="lbl">Productos:</span>
                        <span class="val"><?php echo $d['cant_items']; ?> ítem<?php echo $d['cant_items'] != 1 ? 's' : ''; ?></span>
                    </div>
                </div>
                <div class="delivery-card-footer">
                    <div class="delivery-total">$<?php echo number_format($d['total'], 0, ',', '.'); ?></div>
                    <button class="btn-gestionar" onclick="event.stopPropagation(); abrirDelivery(<?php echo $d['mesa']; ?>)">
                        Gestionar →
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function nuevoDelivery() {
    const btn = event.currentTarget;
    if (btn) { btn.disabled = true; btn.textContent = 'Creando...'; }
    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'nuevo_delivery' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'ventana_pedido.php?mesa=' + data.mesa;
        } else {
            alert('Error al crear el delivery: ' + (data.message || ''));
            if (btn) { btn.disabled = false; btn.textContent = '+ Nuevo Pedido Delivery'; }
        }
    })
    .catch(() => {
        alert('Error de conexión');
        if (btn) { btn.disabled = false; btn.textContent = '+ Nuevo Pedido Delivery'; }
    });
}

function abrirDelivery(mesa) {
    window.location.href = 'ventana_pedido.php?mesa=' + mesa;
}

// Auto-recarga cada 20 segundos para ver nuevos pedidos
setInterval(() => location.reload(), 20000);
</script>

<footer class="footer-global">
    Sistema de Gestión de Restaurante &mdash; Versión 1.0
</footer>
</body>
</html>
