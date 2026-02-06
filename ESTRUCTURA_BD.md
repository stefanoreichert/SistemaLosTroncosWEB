# Estructura de Base de Datos - Los Troncos

## Tablas y Columnas

### 1. **productos**
```
id (int, PK, AUTO_INCREMENT)
nombre (varchar 50)
precio (double 15,2)
stock (int 100)
id_tipo (int, FK -> tipo.Id_tipo)
Id_tipo_producto (int, FK -> tipo_producto.Id_tipo_producto)
```

### 2. **mesa pedido** (Tabla de pedidos activos)
```
mesa (int, PK) - Número de mesa
producto_id (int, PK, FK -> productos.id)
cantidad (int)
fecha_hora (datetime, DEFAULT CURRENT_TIMESTAMP)
precio_unitario (decimal 10,2, DEFAULT 0.00)
hora_pedido (datetime, DEFAULT CURRENT_TIMESTAMP)
observacion (varchar 255)
id_mozo (int, FK -> usuario.id_usuario)
```

### 3. **tipo**
```
Id_tipo (int, PK, AUTO_INCREMENT)
Nombre (varchar 50)
```

### 4. **tipo producto**
```
Id_tipo_producto (int, PK, AUTO_INCREMENT)
Nombre (varchar 50)
```

### 5. **usuario** (Autenticación)
```
id_usuario (int, PK, AUTO_INCREMENT)
nombre (varchar 30)
contraseña (varchar 30)
nivel (varchar 10)
```

### 6. **mesa_observaciones** (Observaciones de mesas)
```
mesa (int, PK)
observacion (text)
```

### 7. **resumenes** (Resumen general)
```
id (int, PK, AUTO_INCREMENT)
fecha (date)
dia (int)
mes (int)
anio (int)
mesa (int)
total (decimal 10,2)
productos (text)
fecha_registro (timestamp, DEFAULT CURRENT_TIMESTAMP)
```

### 8. **resumenes_diarios** (Resumen diario)
```
id (int, PK, AUTO_INCREMENT)
fecha (date)
hora (time)
mesa (int)
total (decimal 10,2)
productos (text)
fecha_registro (timestamp, DEFAULT CURRENT_TIMESTAMP)
```

### 9. **resumenes_mensuales** (Resumen mensual)
```
id (int, PK, AUTO_INCREMENT)
fecha (date)
dia (int)
mes (int)
anio (int)
total_dia (decimal 10,2)
mesas_atendidas (int)
fecha_registro (timestamp, DEFAULT CURRENT_TIMESTAMP)
```

## Notas Importantes

- La tabla **`mesa pedido`** usa composición de claves primarias: `mesa` + `producto_id`
- **No existe columna `id`** en `mesa pedido`
- El precio se guarda en `precio_unitario` en `mesa pedido` (de la mesa en ese momento)
- Los precios de `productos` son los actuales, pueden cambiar
- Las tablas de resumen son para historial y reportes
