<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = getConnection();
    echo "== Estructura de la tabla 'usuario' ==\n\n";
    
    $stmt = $conn->query("DESCRIBE usuario");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($columns) === 0) {
        echo "La tabla 'usuario' no existe o está vacía.\n";
    } else {
        echo "Columnas encontradas:\n";
        foreach ($columns as $col) {
            echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    }
    
    echo "\n== Primeros registros de la tabla ==\n\n";
    $stmt = $conn->query("SELECT * FROM usuario LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rows) === 0) {
        echo "No hay registros en la tabla.\n";
    } else {
        foreach ($rows as $row) {
            echo "Registro: " . json_encode($row) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
