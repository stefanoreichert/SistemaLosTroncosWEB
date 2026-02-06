<?php
// Archivo de entrada principal - Redirige al login o al menú según el estado de sesión
require_once 'config.php';

// Si el usuario ya está autenticado, ir directo al menú principal
if (isAuthenticated()) {
    header('Location: menu_principal.php');
} else {
    // Si no está autenticado, ir al login
    header('Location: login.php');
}
exit;
?>
