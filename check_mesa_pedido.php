<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = getConnection();
    echo "== Estructura de la tabla 'mesa pedido' ==\n\n";
    
    $stmt = $conn->query("DESCRIBE `mesa pedido`");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($columns) === 0) {
        echo "La tabla 'mesa pedido' no existe.\n";
    } else {
        echo "Columnas encontradas:\n";
        foreach ($columns as $col) {
            echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    }
    
    echo "\n== Primeros registros de la tabla ==\n\n";
    $stmt = $conn->query("SELECT * FROM `mesa pedido` LIMIT 3");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rows) === 0) {
        echo "No hay registros en la tabla.\n";
    } else {
        foreach ($rows as $row) {
            echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
