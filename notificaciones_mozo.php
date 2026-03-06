<?php
require_once 'config.php';
requireAuth();
requireNivel('mozo');

$conn = getConnection();

// Si viene del AJAX, procesar confirmación
if (isset($_POST['action']) && $_POST['action'] === 'confirmar_recibido') {
    $mesa = $_POST['mesa'] ?? 0;
    // Agregar notificación de confirmación para cocina
    $sql = "INSERT INTO `notificaciones` (`tipo`, `mensaje`, `mesa`, `fecha_hora`, `leido`) 
            VALUES ('confirmacion_mozo', 'Mozo recibió pedido de mesa " . intval($mesa) . "', ?, NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$mesa]);
    echo json_encode(['success' => true]);
    exit;
}

// Obtener todas las notificaciones (sin filtrar por leído)
$sql = "SELECT * FROM `notificaciones` WHERE `tipo` = 'pedido_listo' ORDER BY `fecha_hora` DESC LIMIT 20";
$stmt = $conn->query($sql);
$notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - Mozo</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #e8eef2 0%, #d4dfe8 50%, #c5d9e8 100%);
        }

        .container-notificaciones {
            background: #f5f5f5;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            max-width: 900px;
            width: 100%;
            margin: 50px auto;
            padding: 40px;
            min-height: 500px;
        }

        .header-notif {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #2196F3;
            padding-bottom: 20px;
        }

        .header-notif h1 {
            color: #2196F3;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .notificacion-item {
            background: linear-gradient(135deg, #ffffff 0%, #f9f9f9 100%);
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #4CAF50;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notificacion-icono {
            font-size: 40px;
            min-width: 60px;
        }

        .notificacion-contenido {
            flex-grow: 1;
        }

        .notificacion-mensaje {
            font-size: 18px;
            color: #212121;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .notificacion-hora {
            font-size: 14px;
            color: #666;
        }

        .sin-notificaciones {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            font-size: 18px;
        }

        .boton-volver {
            display: inline-block;
            margin-top: 30px;
            background: #757575;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-size: 16px;
            transition: all 0.3s;
        }

        .boton-volver:hover {
            background: #616161;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container-notificaciones">
        <div class="header-notif">
            <h1>🔔 Notificaciones</h1>
            <p>Pedidos listos de cocina</p>
        </div>

        <?php if (count($notificaciones) > 0): ?>
            <?php foreach ($notificaciones as $notif): ?>
                <div class="notificacion-item">
                    <div class="notificacion-icono">✅</div>
                    <div class="notificacion-contenido">
                        <div class="notificacion-mensaje"><?php echo htmlspecialchars($notif['mensaje']); ?></div>
                        <div class="notificacion-hora">
                            🕐 <?php echo date('H:i:s', strtotime($notif['fecha_hora'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="sin-notificaciones">
                <p>No hay notificaciones nuevas</p>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 40px;">
            <button class="boton-volver" onclick="location.href='menu_principal.php'">← Volver al Menú</button>
        </div>
    </div>

    <footer class="footer-global">
        Sistema de Gesti&oacute;n de Restaurante &mdash; Versi&oacute;n 1.0
    </footer>
</body>
</html>
