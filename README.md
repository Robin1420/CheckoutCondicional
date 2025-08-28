# 🚚 CheckoutCondicional - Plugin de WooCommerce

**Sistema completo de checkout condicional con gestión de empresas de envío, agencias y fechas de entrega según provincia.**

## 📋 Descripción

CheckoutCondicional es un plugin avanzado para WooCommerce que implementa un sistema inteligente de campos condicionales en el checkout. Dependiendo de la provincia seleccionada por el cliente, se muestran diferentes opciones:

- **Para Lima**: Selector de rango de fechas de entrega (2 días máximo)
- **Para otras provincias**: Selector de empresa de envío y agencia correspondiente

## ✨ Características Principales

### 🎯 Funcionalidades del Checkout
- **Campos condicionales** según provincia
- **Rango de fechas** para Lima (día siguiente + 2 días máximo)
- **Selección de empresa** → **Agencia** para otras provincias
- **Validación automática** de campos obligatorios
- **Interfaz responsiva** y fácil de usar

### 🏢 Gestión Administrativa
- **Panel completo** para gestionar empresas de envío
- **CRUD completo** para agencias (Crear, Leer, Actualizar, Eliminar)
- **Interfaz intuitiva** con pestañas organizadas
- **Modales de edición** para modificar datos
- **Eliminación suave** (cambia estado a inactivo)

### 🔧 Características Técnicas
- **Base de datos personalizada** con tablas optimizadas
- **AJAX** para carga dinámica de agencias
- **Hooks de WooCommerce** para integración perfecta
- **Validación del lado del servidor** y cliente
- **Compatibilidad** con versiones modernas de WordPress/WooCommerce

## 🚀 Instalación

### Requisitos Previos
- WordPress 5.0 o superior
- WooCommerce 5.0 o superior
- PHP 7.4 o superior
- MySQL 5.7 o superior

### Pasos de Instalación
1. **Subir el plugin** a `/wp-content/plugins/checkout-condicional/`
2. **Activar el plugin** desde el panel de WordPress
3. **Configurar empresas** y agencias desde el menú "Checkout Condicional"
4. **Probar el checkout** con diferentes provincias

## ⚙️ Configuración

### 1. Configurar Empresas de Envío
1. Ve a **Checkout Condicional** → **Empresas de Envío**
2. Haz clic en **"Agregar Empresa"**
3. Escribe el nombre de la empresa
4. Haz clic en **"Agregar Empresa"**

### 2. Configurar Agencias
1. Ve a **Checkout Condicional** → **Agencias**
2. Selecciona la empresa correspondiente
3. Completa los datos de la agencia:
   - Nombre de la agencia
   - Dirección
   - Teléfono
4. Haz clic en **"Agregar Agencia"**

### 3. Gestionar Datos Existentes
- **Editar**: Haz clic en el botón azul "Editar"
- **Eliminar**: Haz clic en el botón rojo "Eliminar"
- **Visualizar**: Los datos se muestran en tablas organizadas

## 📊 Estructura de la Base de Datos

### Tabla: `wp_empresas_envio`
```sql
- id (PRIMARY KEY)
- nombre (varchar 255)
- estado (tinyint 1)
- fecha_creacion (datetime)
```

### Tabla: `wp_agencias_envio`
```sql
- id (PRIMARY KEY)
- empresa_id (FOREIGN KEY)
- nombre (varchar 255)
- direccion (text)
- telefono (varchar 50)
- estado (tinyint 1)
- fecha_creacion (datetime)
```

## 🔄 Flujo de Funcionamiento

### Para Clientes de Lima
1. Selecciona provincia "LIMA"
2. Se ocultan campos de empresa/agencia
3. Se muestran campos de fecha de entrega
4. Selecciona rango de 2 días máximo
5. Completa el checkout

### Para Clientes de Otras Provincias
1. Selecciona provincia diferente a "LIMA"
2. Se ocultan campos de fecha
3. Selecciona empresa de envío
4. Se cargan agencias disponibles via AJAX
5. Selecciona agencia específica
6. Completa el checkout

## 📱 API y Hooks

### Hooks de WooCommerce Utilizados
```php
// Campos del checkout
add_action('woocommerce_after_checkout_billing_form', 'checkout_extra_fields');

// Guardar datos
add_action('woocommerce_checkout_update_order_meta', 'bytezon_save_extra_checkout_fields');

// Mostrar en admin
add_action('woocommerce_admin_order_data_after_billing_address', 'bytezon_show_extra_checkout_fields_admin');

// Validación
add_action('woocommerce_checkout_process', 'validate_extra_checkout_fields');
```

### Endpoints AJAX
```php
// Obtener agencias por empresa
wp_ajax_get_agencias

// Editar empresa
wp_ajax_editar_empresa

// Eliminar empresa
wp_ajax_eliminar_empresa

// Editar agencia
wp_ajax_editar_agencia

// Eliminar agencia
wp_ajax_eliminar_agencia
```

## 📋 Estructura de Meta Datos del Pedido

### Para Pedidos de Lima
```json
{
    "meta_data": [
        {
            "key": "_billing_fecha_entrega_inicio",
            "value": "2025-08-28"
        },
        {
            "key": "_billing_fecha_entrega_fin",
            "value": "2025-08-29"
        }
    ]
}
```

### Para Pedidos de Otras Provincias
```json
{
    "meta_data": [
        {
            "key": "_billing_empresa_envio",
            "value": "Olva Courier"
        },
        {
            "key": "_billing_agencia_envio",
            "value": "Agencia Surco"
        }
    ]
}
```

## 🎨 Personalización

### Estilos CSS
El plugin incluye estilos personalizados que se pueden sobrescribir:
```css
.fecha-entrega-field input[type="date"] {
    /* Personalizar campos de fecha */
}

.empresa-envio-field, .agencia-envio-field {
    /* Personalizar campos de empresa/agencia */
}
```

### JavaScript
El plugin incluye JavaScript para la lógica condicional:
```javascript
// Función para mostrar/ocultar campos
function toggleFields() {
    var provincia = $('#billing_provincia').val();
    // Lógica condicional...
}
```

## 🐛 Solución de Problemas

### Problema: No aparecen las agencias
**Solución:**
1. Verifica que la empresa esté activa
2. Verifica que la agencia esté activa
3. Revisa el log de errores de WordPress
4. Verifica la consola del navegador

### Problema: No se guardan los datos
**Solución:**
1. Verifica que WooCommerce esté activo
2. Verifica permisos de usuario
3. Revisa el log de errores
4. Verifica que los campos no estén vacíos

### Problema: Error en la base de datos
**Solución:**
1. Desactiva y reactiva el plugin
2. Verifica permisos de MySQL
3. Revisa la versión de PHP/MySQL
4. Contacta al soporte

## 🔒 Seguridad

### Medidas Implementadas
- **Verificación de nonces** para formularios
- **Sanitización** de todos los datos de entrada
- **Verificación de permisos** de usuario
- **Preparación de consultas SQL** para prevenir inyección
- **Validación** del lado del servidor y cliente

### Permisos Requeridos
- **Administrador**: Acceso completo al plugin
- **Usuario**: Solo visualización en checkout

## 📈 Rendimiento

### Optimizaciones Implementadas
- **Consultas SQL optimizadas** con índices apropiados
- **Carga AJAX** para agencias (no bloquea la página)
- **Cache de consultas** en la base de datos
- **Lazy loading** de datos

### Recomendaciones
- Mantén la base de datos optimizada
- Limpia registros inactivos regularmente
- Monitorea el rendimiento de consultas

## 🔄 Actualizaciones

### Versión 2.0
- ✅ Sistema completo de gestión de empresas y agencias
- ✅ Interfaz administrativa mejorada
- ✅ Rango de fechas para Lima
- ✅ Funcionalidades CRUD completas
- ✅ Estilos mejorados
- ✅ Debug y logging

### Próximas Versiones
- 🔄 Integración con APIs de envío
- 🔄 Reportes y estadísticas
- 🔄 Notificaciones por email
- 🔄 Integración con otros plugins de envío

## 🤝 Soporte

### Información del Plugin
- **Nombre**: CheckoutCondicional
- **Versión**: 2.0
- **Autor**: Robinzon Sanchez
- **Requiere**: WordPress 5.0+, WooCommerce 5.0+
- **Probado hasta**: WordPress 6.4, WooCommerce 8.0

### Contacto
Para soporte técnico o consultas:
- **Email**: [Tu email]
- **Sitio web**: [Tu sitio]
- **Documentación**: [Enlace a documentación]

## 📄 Licencia

Este plugin está bajo licencia GPL v2 o posterior.

## 🙏 Agradecimientos

- **WooCommerce** por la excelente plataforma
- **WordPress** por el sistema de gestión de contenido
- **Comunidad** de desarrolladores por el apoyo

---

**¡Gracias por usar CheckoutCondicional! 🚚✨**
