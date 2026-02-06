<?php
require_once 'config.php';

$usuario = 'Admin';
$password = '1234';

try {
    $conn = getConnection();
    
    // Buscar el usuario en la base de datos (case-insensitive)
    $sql = "SELECT `id_usuario`, `nombre`, `contraseña`, `nivel` FROM `usuario` WHERE LOWER(`nombre`) = LOWER(?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Debug Login</h3>";
    echo "Usuario buscado: " . $usuario . "<br>";
    echo "Contraseña ingresada: " . $password . "<br><br>";
    
    if ($user) {
        echo "<b>Usuario encontrado:</b><br>";
        echo "ID: " . $user['id_usuario'] . "<br>";
        echo "Nombre: " . $user['nombre'] . "<br>";
        echo "Contraseña BD (tipo): " . gettype($user['contraseña']) . "<br>";
        echo "Contraseña BD (valor): " . var_export($user['contraseña'], true) . "<br>";
        echo "Contraseña ingresada (tipo): " . gettype($password) . "<br>";
        echo "Contraseña ingresada (valor): " . var_export($password, true) . "<br>";
        echo "Nivel: " . $user['nivel'] . "<br><br>";
        
        echo "<b>Comparación:</b><br>";
        echo "¿Son iguales? " . ($password === $user['contraseña'] ? 'SÍ' : 'NO') . "<br>";
        echo "¿Son iguales (==)? " . ($password == $user['contraseña'] ? 'SÍ' : 'NO') . "<br>";
    } else {
        echo "Usuario NO encontrado";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
