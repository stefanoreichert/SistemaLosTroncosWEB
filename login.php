<?php
require_once 'config.php';

$error = '';
$usuario = '';

// Si el usuario ya está autenticado, ir al menú principal
if (isAuthenticated()) {
    header('Location: menu_principal.php');
    exit;
}

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($usuario) || empty($password)) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        try {
            $conn = getConnection();
            
            // Buscar el usuario en la base de datos (case-insensitive)
            $sql = "SELECT `id_usuario`, `nombre`, `contraseña`, `nivel` FROM `usuario` WHERE LOWER(`nombre`) = LOWER(?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $password === $user['contraseña']) {
                // Contraseña correcta - iniciar sesión
                $_SESSION['usuario'] = $user['nombre'];
                $_SESSION['usuario_id'] = $user['id_usuario'];
                $_SESSION['nivel'] = $user['nivel'];
                
                header('Location: menu_principal.php');
                exit;
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (Exception $e) {
            $error = 'Error en la base de datos: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Los Troncos</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ── Layout general ── */
        body {
            background: #dde1e7;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 0;
        }

        /* ── Banner superior (igual al menu-bar del sistema) ── */
        .login-topbar {
            background: #2c3e50;
            color: #fff;
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,.15);
        }
        .login-topbar .sistema-nombre {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: .5px;
        }
        .login-topbar .sistema-sub {
            font-size: .85rem;
            color: #b0bec5;
            margin-top: 2px;
        }
        .login-topbar .restaurante-badge {
            background: #2e7d32;
            color: #fff;
            font-size: .82rem;
            font-weight: 600;
            padding: 6px 16px;
            border-radius: 6px;
            letter-spacing: .3px;
        }

        /* ── Área central ── */
        .login-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* ── Card (igual a .main-content del sistema) ── */
        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12);
            padding: 40px 44px;
            width: 100%;
            max-width: 420px;
        }

        .login-card-header {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 2px solid #dee2e6;
        }
        .login-card-header .icono {
            width: 64px;
            height: 64px;
            background: #2e7d32;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.8rem;
        }
        .login-card-header h2 {
            color: #2c3e50;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .login-card-header p {
            color: #666;
            font-size: .9rem;
        }

        /* ── Etiquetas de campo ── */
        .form-group label {
            font-size: .92rem;
            color: #2c3e50;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #1565c0;
            box-shadow: 0 0 0 3px rgba(21,101,192,.12);
        }

        /* ── Mensaje de error ── */
        .login-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #c62828;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 22px;
            font-size: .9rem;
        }

        /* ── Botón principal (igual a .btn-primary del sistema) ── */
        .btn-login {
            width: 100%;
            padding: 13px;
            background: #1565c0;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, transform .2s, box-shadow .2s;
            margin-top: 8px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .btn-login:hover {
            background: #0d47a1;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.18);
        }
        .btn-login:active { transform: translateY(0); }

        /* ── Footer ── */
        .login-footer {
            text-align: center;
            padding: 14px;
            background: #2c3e50;
            color: #78909c;
            font-size: .8rem;
        }
    </style>
</head>
<body>

<!-- Banner superior -->
<div class="login-topbar">
    <div>
        <div class="sistema-nombre">Sistema de Gestión de Restaurante</div>
        <div class="sistema-sub">Acceso al sistema</div>
    </div>
    <div class="restaurante-badge">Los Troncos</div>
</div>

<!-- Card central -->
<div class="login-wrap">
    <div class="login-card">

        <div class="login-card-header">
            <div class="icono">&#127869;</div>
            <h2>Iniciar Sesión</h2>
            <p>Ingresá tus credenciales para continuar</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="login-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" class="form-control"
                       value="<?php echo htmlspecialchars($usuario); ?>"
                       placeholder="Ingresá tu nombre de usuario"
                       required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Ingresá tu contraseña"
                       required>
            </div>

            <button type="submit" class="btn-login">Ingresar al Sistema</button>
        </form>

    </div>
</div>

<!-- Footer -->
<div class="login-footer">
    Sistema de Gestión de Restaurante &mdash; Los Troncos
</div>

</body>
</html>
