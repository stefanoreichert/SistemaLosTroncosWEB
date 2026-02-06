// Archivo JavaScript para funcionalidades adicionales del sistema

// Función para formatear números como moneda
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Función para confirmar acciones
function confirmarAccion(mensaje) {
    return confirm(mensaje);
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info') {
    const colores = {
        'success': '#28a745',
        'error': '#dc3545',
        'warning': '#ffc107',
        'info': '#17a2b8'
    };

    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colores[tipo] || colores.info};
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
    `;
    notification.textContent = mensaje;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Agregar animaciones CSS
const style = document.createElement('style');
style.textContent = `
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
`;
document.head.appendChild(style);

// Función para validar formularios
function validarFormulario(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let valido = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#dc3545';
            valido = false;
        } else {
            input.style.borderColor = '#ddd';
        }
    });
    
    return valido;
}

// Detectar teclas rápidas
document.addEventListener('keydown', function(e) {
    // ESC para cerrar modales
    if (e.key === 'Escape') {
        const modales = document.querySelectorAll('.modal');
        modales.forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });
    }
});

// Auto-guardar en localStorage para recuperación de datos
function autoGuardar(key, data) {
    try {
        localStorage.setItem(key, JSON.stringify(data));
    } catch (e) {
        console.error('Error al guardar en localStorage:', e);
    }
}

function autoRecuperar(key) {
    try {
        const data = localStorage.getItem(key);
        return data ? JSON.parse(data) : null;
    } catch (e) {
        console.error('Error al recuperar de localStorage:', e);
        return null;
    }
}

// Función para imprimir contenido específico
function imprimirContenido(elementId) {
    const contenido = document.getElementById(elementId);
    if (!contenido) return;
    
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html>
        <head>
            <title>Imprimir</title>
            <link rel="stylesheet" href="styles.css">
        </head>
        <body>
            ${contenido.innerHTML}
            <script>
                window.onload = function() {
                    window.print();
                    window.close();
                };
            </script>
        </body>
        </html>
    `);
    ventana.document.close();
}

// Función para actualizar reloj en tiempo real
function actualizarReloj() {
    const elementos = document.querySelectorAll('.reloj');
    if (elementos.length === 0) return;
    
    setInterval(() => {
        const ahora = new Date();
        const hora = ahora.toLocaleTimeString('es-ES');
        elementos.forEach(el => el.textContent = hora);
    }, 1000);
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    actualizarReloj();
    
    // Agregar tooltips a botones
    const botones = document.querySelectorAll('[data-tooltip]');
    botones.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.cssText = `
                position: absolute;
                background: #333;
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 10000;
            `;
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
            tooltip.style.left = (rect.left + rect.width/2 - tooltip.offsetWidth/2) + 'px';
            
            this.addEventListener('mouseleave', function() {
                tooltip.remove();
            }, { once: true });
        });
    });
});

// Exportar funciones globales
window.formatCurrency = formatCurrency;
window.confirmarAccion = confirmarAccion;
window.mostrarNotificacion = mostrarNotificacion;
window.validarFormulario = validarFormulario;
window.autoGuardar = autoGuardar;
window.autoRecuperar = autoRecuperar;
window.imprimirContenido = imprimirContenido;
