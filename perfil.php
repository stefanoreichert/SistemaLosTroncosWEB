<?php
require_once 'config.php';
requireAuth();

$usuario  = $_SESSION['usuario'] ?? 'Usuario';
$nivel    = getNivelUsuario();
$idUsuario = $_SESSION['usuario_id'] ?? null;

$mensajeExito = '';
$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passwordActual  = trim($_POST['password_actual']  ?? '');
    $passwordNueva   = trim($_POST['password_nueva']   ?? '');
    $passwordConfirm = trim($_POST['password_confirm'] ?? '');

    if (empty($passwordActual) || empty($passwordNueva) || empty($passwordConfirm)) {
        $mensajeError = 'Completa todos los campos.';
    } elseif ($passwordNueva !== $passwordConfirm) {
        $mensajeError = 'La nueva contraseña y la confirmación no coinciden.';
    } elseif (strlen($passwordNueva) < 4) {
        $mensajeError = 'La nueva contraseña debe tener al menos 4 caracteres.';
    } else {
        $conn = getConnection();
        $sql  = "SELECT `contraseña` FROM `usuario` WHERE `id_usuario` = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idUsuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $passwordActual !== $user['contraseña']) {
            $mensajeError = 'La contraseña actual es incorrecta.';
        } else {
            $sql  = "UPDATE `usuario` SET `contraseña` = ? WHERE `id_usuario` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$passwordNueva, $idUsuario]);
            $mensajeExito = 'Contraseña actualizada correctamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Sistema Restaurante</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="menu-bar">
        <div class="menu-left">
            <span class="menu-title">Mi Perfil — <?php echo htmlspecialchars($usuario); ?></span>
        </div>
        <div class="menu-right">
            <button class="btn btn-sm" onclick="location.href='menu_principal.php'">← Volver al Menú</button>
            <button class="btn btn-sm btn-secondary" onclick="if(confirm('¿Desea salir?')) location.href='logout.php'">Salir</button>
        </div>
    </div>

    <div class="container" style="max-width: 520px; margin: 30px auto;">
        <div class="main-content">
            <div class="header">
                <h1>👤 Mi Perfil</h1>
            </div>

            <!-- Información del usuario -->
            <div style="padding: 5px 0 25px;">
                <div class="stat-item" style="margin-bottom: 12px;">
                    <span class="stat-label">Usuario:</span>
                    <strong style="font-size:16px;"><?php echo htmlspecialchars($usuario); ?></strong>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Nivel:</span>
                    <strong style="font-size:16px;"><?php echo ucfirst($nivel); ?></strong>
                </div>
            </div>

            <hr style="margin-bottom: 25px; border: none; border-top: 2px solid #dee2e6;">

            <h3 style="color: #2c3e50; margin-bottom: 20px; font-size: 20px;">Cambiar Contraseña</h3>

            <?php if ($mensajeExito): ?>
                <div class="alert" style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;padding:12px 15px;border-radius:6px;margin-bottom:20px;">
                    ✅ <?php echo $mensajeExito; ?>
                </div>
            <?php endif; ?>

            <?php if ($mensajeError): ?>
                <div class="alert alert-error">
                    ⚠️ <?php echo $mensajeError; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Contraseña actual</label>
                    <input type="password" name="password_actual" class="form-control" placeholder="Ingresa tu contraseña actual" required>
                </div>
                <div class="form-group">
                    <label>Nueva contraseña</label>
                    <input type="password" name="password_nueva" class="form-control" placeholder="Mínimo 4 caracteres" required>
                </div>
                <div class="form-group">
                    <label>Confirmar nueva contraseña</label>
                    <input type="password" name="password_confirm" class="form-control" placeholder="Repite la nueva contraseña" required>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Guardar Cambios</button>
                    <button type="button" class="btn btn-secondary" onclick="location.href='menu_principal.php'">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer-global">
        Sistema de Gestión de Restaurante &mdash; Versión 1.0
    </footer>
</body>
</html>
