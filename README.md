## 🚚 CheckoutCondicional - Plugin de WooCommerce

**Sistema completo de checkout condicional con gestión de empresas de envío, agencias y fechas de entrega según provincia.**

## 📋 Descripción

CheckoutCondicional es un plugin avanzado para WooCommerce que implementa un sistema inteligente de campos condicionales en el checkout. Dependiendo de la provincia seleccionada por el cliente, se muestran diferentes opciones:

- **Para Lima y Callao**: Selector de rango de fechas de entrega (2 días máximo)
- **Para otras provincias**: Selector de empresa de envío y agencia correspondiente

## ✨ Características Principales

### 🎯 Funcionalidades del Checkout
- **Campos condicionales** según provincia
- **Rango de fechas** para Lima y Callao (día siguiente + 2 días máximo)
- **Selección de empresa** → **Agencia** para otras provincias
- **Dos modos de selección de agencia**:
  1. Lista predefinida de agencias
  2. Campo de texto libre para ingresar agencia
- **Validación automática** de campos obligatorios
- **Interfaz responsiva** y fácil de usar

### 🏢 Gestión Administrativa
- **Panel completo** para gestionar empresas de envío
- **CRUD completo** para agencias (Crear, Leer, Actualizar, Eliminar)
- **Configuración de modo de selección de agencia**
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

### 3. Configuración de Modo de Agencia
1. Ve a **Checkout Condicional** → **Configuración**
2. Elige entre dos modos de selección de agencia:
   - **Lista predefinida**: Selección desde agencias registradas
   - **Campo de texto libre**: Permite ingresar cualquier nombre de agencia
3. Guarda la configuración

### 4. Gestionar Datos Existentes
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

### Tabla: `wp_checkout_condicional_config`
```sql
- id (PRIMARY KEY)
- config_key (varchar 100)
- config_value (text)
- fecha_creacion (datetime)
```

## 🔄 Flujo de Funcionamiento

### Para Clientes de Lima y Callao
1. Selecciona provincia "LIMA" o "CALLAO"
2. Se ocultan campos de empresa/agencia
3. Se muestran campos de fecha de entrega
4. Selecciona rango de 2 días máximo
5. Completa el checkout

### Para Clientes de Otras Provincias
1. Selecciona provincia diferente a "LIMA" o "CALLAO"
2. Se ocultan campos de fecha
3. Selecciona empresa de envío
4. Se cargan agencias disponibles via AJAX
5. Selecciona agencia (según modo configurado)
6. Completa el checkout

## 📋 Estructura de Meta Datos del Pedido

### Para Pedidos de Lima/Callao
```json
{
    "meta_data": [
        {
            "key": "_billing_fecha_entrega_1",
            "value": "2025-08-28"
        },
        {
            "key": "_billing_fecha_entrega_2",
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

## 🔄 Actualizaciones

### Versión 2.1
- ✅ Modo de selección de agencia (lista o texto libre)
- ✅ Soporte para provincia de Callao
- ✅ Mejoras en la configuración del plugin
- ✅ Optimización de código

### Próximas Versiones
- 🔄 Integración con APIs de envío
- 🔄 Reportes y estadísticas
- 🔄 Notificaciones por email
- 🔄 Integración con otros plugins de envío

## 🤝 Soporte

### Información del Plugin
- **Nombre**: CheckoutCondicional
- **Versión**: 2.1
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
