<?php
require_once 'config.php';
requireAuth();

// Debug: Mostrar nivel
$nivelActual = getNivelUsuario();
if ($nivelActual !== 'cocina' && $nivelActual !== 'admin') {
    die('ERROR: Tu nivel es "' . htmlspecialchars($nivelActual) . '". Se requiere "cocina" o "admin". Tu sesión: ' . print_r($_SESSION, true));
}

requireNivel('cocina');

$numeroMesa = isset($_GET['mesa']) ? (int)$_GET['mesa'] : 1;

// Obtener pedido de la mesa con información del mozo
function obtenerPedidoCocina($mesa) {
    $conn = getConnection();
    $sql = "SELECT `mp`.`mesa`, `mp`.`producto_id`, `p`.`nombre`, `mp`.`cantidad`, 
                   `mp`.`fecha_hora`, COALESCE(`u`.`nombre`, 'Desconocido') as mozo
            FROM `mesa pedido` AS `mp`
            JOIN `productos` AS `p` ON `mp`.`producto_id` = `p`.`id`
            LEFT JOIN `usuario` AS `u` ON `mp`.`id_mozo` = `u`.`id_usuario`
            WHERE `mp`.`mesa` = ?
            ORDER BY `mp`.`fecha_hora` ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$mesa]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener la hora mínima del pedido (cuando llegó el primer artículo)
function obtenerHoraLlegadaPedido($mesa) {
    $conn = getConnection();
    $sql = "SELECT MIN(`fecha_hora`) as hora_llegada FROM `mesa pedido` WHERE `mesa` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$mesa]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['hora_llegada'] ?? date('Y-m-d H:i:s');
}

$pedido = obtenerPedidoCocina($numeroMesa);
$horaPedido = obtenerHoraLlegadaPedido($numeroMesa);
$mozo = !empty($pedido) ? $pedido[0]['mozo'] : 'Desconocido';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Cocina - Mesa <?php echo $numeroMesa; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #e8eef2 0%, #d4dfe8 50%, #c5d9e8 100%);
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
 
        .container-cocina { 
            background: #f5f5f5;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            max-width: 900px;
            width: 100%;
            overflow: hidden;
            border: 5px solid #5f5d5c;
        }

        .header-cocina {
            background: linear-gradient(135deg, #33bc21 0%, #31c529 100%);
            color: #f5f5f5;
            padding: 30px;
            text-align: center;
            border-bottom: 5px solid #212121;
        }

        .reloj-container {
            margin-bottom: 20px;
        }

        .reloj {
            font-size: 72px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            letter-spacing: 10px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.7);
            color: #fff;
        }

        .titulo-mesa {
            font-size: 48px;
            margin: 20px 0 10px 0;
            font-weight: bold;
            color: #fff;
        }

        .info-pedido {
            background: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            border: 2px solid #9e9e9e;
        }

        .info-mozo {
            font-size: 18px;
            margin-bottom: 8px;
            color: #f5f5f5;
        }

        .info-hora {
            font-size: 16px;
            margin-bottom: 8px;
            color: #f5f5f5;
        }

        .tiempo-transcurrido {
            font-size: 16px;
            background: #757575;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 10px;
            color: #fff;
            border: 2px solid #9e9e9e;
        }

        .contenido-cocina {
            padding: 40px;
        }

        .ordenes-container {
            display: grid;
            gap: 20px;
        }

        .articulo {
            background: linear-gradient(135deg, #bdbdbd 0%, #9e9e9e 100%);
            padding: 25px;
            border-radius: 10px;
            border-left: 8px solid #757575;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .articulo:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            background: linear-gradient(135deg, #9e9e9e 0%, #757575 100%);
        }

        .articulo-nombre {
            font-size: 32px;
            font-weight: bold;
            color: #212121;
            flex-grow: 1;
        }

        .articulo-cantidad {
            background: #757575;
            color: #f5f5f5;
            padding: 20px 30px;
            border-radius: 50%;
            font-size: 40px;
            font-weight: bold;
            min-width: 100px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .articulo-hora {
            margin-left: 20px;
            font-size: 16px;
            color: #212121;
            text-align: center;
            min-width: 90px;
            font-weight: bold;
        }

        .sin-ordenes {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            font-size: 20px;
        }

        .pie-cocina {
            background: #9e9e9e;
            padding: 20px;
            text-align: center;
            border-top: 3px solid #5d4037;
        }

        .btn-volver {
            background: #757575;
            color: #f5f5f5;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .btn-volver:hover {
            background: #616161;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
        }
        .btn-avisar-mozo {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: #ffffff;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 18px;
        }

        .btn-avisar-mozo:hover {
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.6);
        }

        .btn-avisar-mozo:active {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
        }

        .campana-icon {
            font-size: 24px;
            animation: campanaShake 0.5s ease-in-out;
        }

        @keyframes campanaShake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-15deg); }
            75% { transform: rotate(15deg); }
        }

        .btn-avisar-mozo:hover .campana-icon {
            animation: campanaShake 0.5s ease-in-out infinite;
        }
        @media (max-width: 768px) {
            .reloj {
                font-size: 48px;
                letter-spacing: 5px;
            }

            .titulo-mesa {
                font-size: 32px;
            }

            .articulo {
                flex-direction: column;
                text-align: center;
            }

            .articulo-nombre {
                font-size: 24px;
                margin-bottom: 15px;
            }

            .articulo-cantidad {
                margin-bottom: 15px;
                min-width: 80px;
                padding: 15px 20px;
                font-size: 32px;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

            .articulo-hora {
                margin-left: 0;
            }
        }

        @media (max-width: 480px) {
            .reloj {
                font-size: 36px;
                letter-spacing: 3px;
            }

            .titulo-mesa {
                font-size: 24px;
            }

            .contenido-cocina {
                padding: 20px;
            }

            .articulo {
                padding: 15px;
            }

            .articulo-nombre {
                font-size: 18px;
            }

            .articulo-cantidad {
                font-size: 28px;
                padding: 12px 15px;
                min-width: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="container-cocina">
        <!-- HEADER CON RELOJ EN TIEMPO REAL -->
        <div class="header-cocina">
            <div class="reloj-container">
                <div class="reloj" id="relojActual">00:00:00</div>
            </div>

            <div class="titulo-mesa">MESA <?php echo str_pad($numeroMesa, 2, '0', STR_PAD_LEFT); ?></div>

            <div class="info-pedido">
                <div class="info-mozo">
                    👨‍🍳 <strong>Mozo:</strong> <?php echo htmlspecialchars($mozo); ?>
                </div>
                <div class="info-hora">
                    🕐 <strong>Pedido llegó:</strong> <span id="horaPedido"><?php echo date('H:i:s', strtotime($horaPedido)); ?></span>
                </div>
                <div class="tiempo-transcurrido">
                    ⏱️ <strong>Tiempo:</strong> <span id="tiempoTranscurrido">0s</span>
                </div>
            </div>
        </div>

        <!-- CONTENIDO: ÓRDENES A COCINAR -->
        <div class="contenido-cocina">
            <?php if (count($pedido) > 0): ?>
                <div class="ordenes-container">
                    <?php foreach ($pedido as $item): ?>
                        <div class="articulo">
                            <div class="articulo-nombre">
                                <?php echo htmlspecialchars($item['nombre']); ?>
                            </div>
                            <div class="articulo-cantidad">
                                <?php echo $item['cantidad']; ?>
                            </div>
                            <div class="articulo-hora">
                                <?php echo date('H:i:s', strtotime($item['fecha_hora'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="sin-ordenes">
                    <p>Sin órdenes para preparar en esta mesa</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- PIE DE PÁGINA -->
        <div class="pie-cocina">
            <button class="btn-avisar-mozo" onclick="avisarMozo()"><span class="campana-icon">🔔</span> Pedido Listo</button>
            <button class="btn-volver" onclick="volverMenu()">← Volver al Menú</button>
        </div>
    </div>

    <script>
        const numeroMesa = <?php echo $numeroMesa; ?>;
        const horaPedidoInicial = new Date('<?php echo $horaPedido; ?>'.replace(' ', 'T'));

        // Actualizar reloj en tiempo real
        function actualizarReloj() {
            const ahora = new Date();
            const horas = String(ahora.getHours()).padStart(2, '0');
            const minutos = String(ahora.getMinutes()).padStart(2, '0');
            const segundos = String(ahora.getSeconds()).padStart(2, '0');
            document.getElementById('relojActual').textContent = `${horas}:${minutos}:${segundos}`;

            // Calcular tiempo transcurrido
            const diferencia = ahora - horaPedidoInicial;
            const minutosTrans = Math.floor(diferencia / 60000);
            const segundosTrans = Math.floor((diferencia % 60000) / 1000);

            let tiempoTexto = '';
            if (minutosTrans > 0) {
                tiempoTexto = `${minutosTrans}m ${segundosTrans}s`;
            } else {
                tiempoTexto = `${segundosTrans}s`;
            }

            document.getElementById('tiempoTranscurrido').textContent = tiempoTexto;
        }

        // Actualizar cada segundo
        setInterval(actualizarReloj, 1000);
        actualizarReloj(); // Llamada inicial

        // Recargar la página cada 5 segundos para obtener nuevas órdenes
        setInterval(() => {
            location.reload();
        }, 5000);

        function volverMenu() {
            window.location.href = 'menu_principal.php';
        }

        function avisarMozo() {
            // Crear una solicitud AJAX para notificar al mozo
            const formData = new FormData();
            formData.append('action', 'notificar_pedido_listo');
            formData.append('mesa', numeroMesa);

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar notificación visual
                    mostrarNotificacionExito('Mozo notificado: Pedido de mesa ' + numeroMesa + ' listo');
                } else {
                    mostrarNotificacionError('Error al notificar al mozo');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacionError('Error de conexión');
            });
        }

        function mostrarNotificacionExito(mensaje) {
            const notif = document.createElement('div');
            notif.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #4CAF50;
                color: white;
                padding: 20px 30px;
                border-radius: 8px;
                font-size: 16px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 10000;
                animation: slideIn 0.3s ease-out;
            `;
            notif.textContent = '✓ ' + mensaje;
            document.body.appendChild(notif);
            
            setTimeout(() => {
                notif.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notif.remove(), 300);
            }, 3000);
        }

        function mostrarNotificacionError(mensaje) {
            const notif = document.createElement('div');
            notif.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #f44336;
                color: white;
                padding: 20px 30px;
                border-radius: 8px;
                font-size: 16px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 10000;
                animation: slideIn 0.3s ease-out;
            `;
            notif.textContent = '✗ ' + mensaje;
            document.body.appendChild(notif);
            
            setTimeout(() => {
                notif.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notif.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>
