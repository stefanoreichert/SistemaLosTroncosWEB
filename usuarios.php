<?php
require_once 'config.php';
requireAuth();

// Solo el admin puede acceder a esta página
if (!esAdmin()) {
    header('Location: menu_principal.php');
    exit;
}

$usuario = $_SESSION['usuario'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Los Troncos</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ===== Estilos específicos para la página de usuarios ===== */
        .usuarios-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-header h2 {
            color: #fff;
            font-size: 1.6rem;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }

        /* Tabla */
        .tabla-usuarios {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0,0,0,0.15);
        }

        .tabla-usuarios thead {
            background: #37474f;
            color: #fff;
        }

        .tabla-usuarios th,
        .tabla-usuarios td {
            padding: 13px 16px;
            text-align: left;
            font-size: 0.95rem;
        }

        .tabla-usuarios tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s;
        }

        .tabla-usuarios tbody tr:hover {
            background: #f2f2f2;
        }

        .tabla-usuarios tbody tr:last-child {
            border-bottom: none;
        }

        /* Badges de nivel */
        .badge-nivel {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge-admin   { background: #e3eaf6; color: #1565c0; }
        .badge-mozo    { background: #e8f5e9; color: #2e7d32; }
        .badge-cocina  { background: #fff3e0; color: #e65100; }

        /* Acciones en tabla */
        .acciones-celda {
            display: flex;
            gap: 8px;
        }

        /* Botones */
        .btn-agregar {
            background: #2e7d32;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.95rem;
            transition: background 0.2s, transform 0.1s;
        }

        .btn-agregar:hover { background: #1b5e20; transform: translateY(-1px); }

        .btn-editar {
            background: #1565c0;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.2s;
        }

        .btn-editar:hover { background: #0d47a1; }

        .btn-eliminar {
            background: #c62828;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.2s;
        }

        .btn-eliminar:hover { background: #b71c1c; }

        /* Sin usuarios */
        .sin-usuarios {
            text-align: center;
            padding: 30px;
            color: #999;
            font-size: 1rem;
        }

        /* ===== Modal ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.activo { display: flex; }

        .modal-box {
            background: #fff;
            border-radius: 12px;
            width: 100%;
            max-width: 440px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            position: relative;
            animation: fadeInModal 0.2s ease;
        }

        @keyframes fadeInModal {
            from { opacity: 0; transform: scale(0.93); }
            to   { opacity: 1; transform: scale(1); }
        }

        .modal-box h3 {
            margin-bottom: 22px;
            color: #333;
            font-size: 1.25rem;
        }

        .modal-close {
            position: absolute;
            top: 14px; right: 18px;
            background: none;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: #999;
            line-height: 1;
        }

        .modal-close:hover { color: #333; }

        .form-field {
            margin-bottom: 16px;
        }

        .form-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #444;
            font-size: 0.9rem;
        }

        .form-field input,
        .form-field select {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #ddd;
            border-radius: 7px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
            outline: none;
            color: #333;
        }

        .form-field input:focus,
        .form-field select:focus {
            border-color: #666;
        }

        .form-hint {
            font-size: 0.78rem;
            color: #999;
            margin-top: 4px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
        }

        .btn-cancelar {
            background: #f0f0f0;
            color: #555;
            border: none;
            padding: 9px 20px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 0.93rem;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-cancelar:hover { background: #e0e0e0; }

        .btn-guardar {
            background: #1565c0;
            color: #fff;
            border: none;
            padding: 9px 22px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 0.93rem;
            font-weight: 700;
            transition: background 0.2s;
        }

        .btn-guardar:hover { background: #0d47a1; }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 25px;
            right: 25px;
            padding: 12px 22px;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            font-size: 0.93rem;
            z-index: 2000;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s, transform 0.3s;
            pointer-events: none;
        }

        .toast.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .toast.exito  { background: #2e7d32; }
        .toast.error  { background: #c62828; }

        /* Buscador */
        .buscador-wrap {
            margin-bottom: 16px;
        }

        .buscador-wrap input {
            width: 100%;
            padding: 9px 14px;
            border: 1.5px solid rgba(255,255,255,0.6);
            border-radius: 8px;
            font-size: 0.95rem;
            background: rgba(255,255,255,0.9);
            color: #333;
            outline: none;
        }

        .buscador-wrap input:focus {
            border-color: #fff;
            background: #fff;
        }
    </style>
</head>
<body>
    <!-- Barra de navegación -->
    <div class="menu-bar">
        <div class="menu-left">
            <span class="menu-title">Gestión de Usuarios &mdash; Admin (<?php echo htmlspecialchars($usuario); ?>)</span>
        </div>
        <div class="menu-right">
            <button class="btn btn-sm" onclick="location.href='menu_principal.php'">&#8592; Volver al Menú</button>
            <button class="btn btn-sm btn-secondary" onclick="if(confirm('¿Desea salir?')) location.href='logout.php'">Salir</button>
        </div>
    </div>

    <div class="usuarios-container">
        <!-- Encabezado -->
        <div class="page-header">
            <h2>Usuarios del Sistema</h2>
            <button class="btn-agregar" onclick="abrirModalCrear()">+ Nuevo Usuario</button>
        </div>

        <!-- Buscador -->
        <div class="buscador-wrap">
            <input type="text" id="buscador" placeholder="Buscar usuario por nombre o nivel..." oninput="filtrarTabla()" />
        </div>

        <!-- Tabla de usuarios -->
        <table class="tabla-usuarios" id="tablaUsuarios">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Nivel</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tbodyUsuarios">
                <tr><td colspan="4" class="sin-usuarios">Cargando usuarios...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Modal: Crear / Editar usuario -->
    <div class="modal-overlay" id="modalUsuario">
        <div class="modal-box">
            <button class="modal-close" onclick="cerrarModal()" title="Cerrar">&times;</button>
            <h3 id="modalTitulo">Nuevo Usuario</h3>

            <input type="hidden" id="campoId" />

            <div class="form-field">
                <label for="campoNombre">Nombre de usuario</label>
                <input type="text" id="campoNombre" placeholder="Ej: Juan García" maxlength="80" autocomplete="off" />
            </div>

            <div class="form-field">
                <label for="campoNivel">Nivel / Rol</label>
                <select id="campoNivel">
                    <option value="mozo">Mozo</option>
                    <option value="cocina">Cocina</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="form-field">
                <label for="campoPassword" id="labelPassword">Contraseña</label>
                <input type="password" id="campoPassword" placeholder="Ingresa la contraseña" maxlength="120" autocomplete="new-password" />
                <p class="form-hint" id="hintPassword">La contraseña es obligatoria para nuevos usuarios.</p>
            </div>

            <div class="modal-footer">
                <button class="btn-cancelar" onclick="cerrarModal()">Cancelar</button>
                <button class="btn-guardar" onclick="guardarUsuario()">Guardar</button>
            </div>
        </div>
    </div>

    <!-- Toast de notificaciones -->
    <div class="toast" id="toast"></div>

    <script src="scripts.js"></script>
    <script>
        // =============================================
        //  Estado global
        // =============================================
        let todosLosUsuarios = [];
        let modoEdicion = false;

        // =============================================
        //  Carga inicial
        // =============================================
        document.addEventListener('DOMContentLoaded', cargarUsuarios);

        function cargarUsuarios() {
            fetch('api.php?action=obtener_usuarios')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) { mostrarToast(data.message || 'Error al cargar usuarios', 'error'); return; }
                    todosLosUsuarios = data.usuarios || [];
                    renderTabla(todosLosUsuarios);
                })
                .catch(() => mostrarToast('Error de conexión con el servidor', 'error'));
        }

        // =============================================
        //  Renderizado de tabla
        // =============================================
        function renderTabla(usuarios) {
            const tbody = document.getElementById('tbodyUsuarios');
            if (usuarios.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="sin-usuarios">No hay usuarios registrados.</td></tr>';
                return;
            }

            tbody.innerHTML = usuarios.map((u, i) => `
                <tr id="fila-${u.id_usuario}">
                    <td>${i + 1}</td>
                    <td>${escHtml(u.nombre)}</td>
                    <td><span class="badge-nivel badge-${u.nivel}">${ucfirst(u.nivel)}</span></td>
                    <td>
                        <div class="acciones-celda">
                            <button class="btn-editar" onclick="abrirModalEditar(${u.id_usuario}, '${escHtml(u.nombre)}', '${u.nivel}')">Editar</button>
                            <button class="btn-eliminar" onclick="eliminarUsuario(${u.id_usuario}, '${escHtml(u.nombre)}')">Eliminar</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        // =============================================
        //  Buscador
        // =============================================
        function filtrarTabla() {
            const q = document.getElementById('buscador').value.toLowerCase().trim();
            const filtrados = q
                ? todosLosUsuarios.filter(u => u.nombre.toLowerCase().includes(q) || u.nivel.toLowerCase().includes(q))
                : todosLosUsuarios;
            renderTabla(filtrados);
        }

        // =============================================
        //  Modal: Crear
        // =============================================
        function abrirModalCrear() {
            modoEdicion = false;
            document.getElementById('modalTitulo').textContent = '+ Nuevo Usuario';
            document.getElementById('campoId').value = '';
            document.getElementById('campoNombre').value = '';
            document.getElementById('campoNivel').value = 'mozo';
            document.getElementById('campoPassword').value = '';
            document.getElementById('hintPassword').textContent = 'La contraseña es obligatoria para nuevos usuarios.';
            document.getElementById('labelPassword').textContent = 'Contraseña';
            document.getElementById('modalUsuario').classList.add('activo');
            setTimeout(() => document.getElementById('campoNombre').focus(), 100);
        }

        // =============================================
        //  Modal: Editar
        // =============================================
        function abrirModalEditar(id, nombre, nivel) {
            modoEdicion = true;
            document.getElementById('modalTitulo').textContent = 'Editar Usuario';
            document.getElementById('campoId').value = id;
            document.getElementById('campoNombre').value = nombre;
            document.getElementById('campoNivel').value = nivel;
            document.getElementById('campoPassword').value = '';
            document.getElementById('hintPassword').textContent = 'Dejá en blanco para no cambiar la contraseña.';
            document.getElementById('labelPassword').textContent = 'Nueva contraseña (opcional)';
            document.getElementById('modalUsuario').classList.add('activo');
            setTimeout(() => document.getElementById('campoNombre').focus(), 100);
        }

        // =============================================
        //  Cerrar modal
        // =============================================
        function cerrarModal() {
            document.getElementById('modalUsuario').classList.remove('activo');
        }

        // Cerrar al hacer click fuera del modal-box
        document.getElementById('modalUsuario').addEventListener('click', function(e) {
            if (e.target === this) cerrarModal();
        });

        // =============================================
        //  Guardar (crear o actualizar)
        // =============================================
        function guardarUsuario() {
            const nombre   = document.getElementById('campoNombre').value.trim();
            const nivel    = document.getElementById('campoNivel').value;
            const password = document.getElementById('campoPassword').value.trim();
            const id       = document.getElementById('campoId').value;

            if (!nombre) { mostrarToast('El nombre es obligatorio', 'error'); return; }
            if (!modoEdicion && !password) { mostrarToast('La contraseña es obligatoria', 'error'); return; }

            const action  = modoEdicion ? 'actualizar_usuario' : 'agregar_usuario';
            const payload = { action, nombre, nivel, password };
            if (modoEdicion) payload.id_usuario = parseInt(id);

            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    mostrarToast(data.message, 'exito');
                    cerrarModal();
                    cargarUsuarios();
                } else {
                    mostrarToast(data.message || 'Error al guardar', 'error');
                }
            })
            .catch(() => mostrarToast('Error de conexión', 'error'));
        }

        // =============================================
        //  Eliminar
        // =============================================
        function eliminarUsuario(id, nombre) {
            if (!confirm(`¿Estás seguro de que querés eliminar al usuario "${nombre}"?\nEsta acción no se puede deshacer.`)) return;

            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'eliminar_usuario', id_usuario: id })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    mostrarToast(data.message, 'exito');
                    cargarUsuarios();
                } else {
                    mostrarToast(data.message || 'Error al eliminar', 'error');
                }
            })
            .catch(() => mostrarToast('Error de conexión', 'error'));
        }

        // =============================================
        //  Helpers
        // =============================================
        function mostrarToast(msg, tipo = 'exito') {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.className = `toast ${tipo} visible`;
            setTimeout(() => { toast.classList.remove('visible'); }, 3200);
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function ucfirst(str) {
            return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
        }

        // Enviar con Enter en los campos del modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && document.getElementById('modalUsuario').classList.contains('activo')) {
                guardarUsuario();
            }
            if (e.key === 'Escape' && document.getElementById('modalUsuario').classList.contains('activo')) {
                cerrarModal();
            }
        });
    </script>

    <footer class="footer-global">
        Sistema de Gesti&oacute;n de Restaurante &mdash; Versi&oacute;n 1.0
    </footer>
</body>
</html>
