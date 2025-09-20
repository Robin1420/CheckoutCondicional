<?php
/**
 * Plugin Name: CheckoutCondicionalV.1
 * Description: Sistema de checkout condicional con gestión de empresas de envío, agencias y fechas de entrega según provincia.
 * Author: Robinzon Sanchez
 * Version: 1.4
 */

if (!defined('ABSPATH')) exit;

// Verificar que WooCommerce esté activo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// === LIMPIEZA DE CACHE PARA ACTUALIZACIÓN FORZADA ===
add_action('init', 'checkout_condicional_v1_force_update', 1);
function checkout_condicional_v1_force_update() {
    // Limpiar cache de WordPress
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Limpiar cache de transients
    if (function_exists('delete_transient')) {
        delete_transient('checkout_condicional_cache');
        delete_transient('empresas_envio_cache');
        delete_transient('agencias_envio_cache');
    }
    
    // Limpiar cache de opciones
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete('checkout_condicional_config', 'options');
    }
    
    // Forzar recarga de scripts y estilos
    if (function_exists('wp_enqueue_scripts')) {
        wp_dequeue_script('checkout-condicional-v1');
        wp_dequeue_style('checkout-condicional-v1');
    }
}

// === ACTIVACIÓN DEL PLUGIN ===
register_activation_hook(__FILE__, 'checkout_condicional_v1_activate');
register_deactivation_hook(__FILE__, 'checkout_condicional_v1_deactivate');
function checkout_condicional_v1_activate() {
    global $wpdb;
    
    // Limpiar cache al activar
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Limpiar transients
    if (function_exists('delete_transient')) {
        delete_transient('checkout_condicional_cache');
        delete_transient('empresas_envio_cache');
        delete_transient('agencias_envio_cache');
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabla de empresas de envío
    $table_empresas = $wpdb->prefix . 'empresas_envio_v1';
    $sql_empresas = "CREATE TABLE $table_empresas (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nombre varchar(255) NOT NULL,
        estado tinyint(1) DEFAULT 1,
        fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Tabla de agencias
    $table_agencias = $wpdb->prefix . 'agencias_envio_v1';
    $sql_agencias = "CREATE TABLE $table_agencias (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        empresa_id mediumint(9) NOT NULL,
        nombre varchar(255) NOT NULL,
        direccion text,
        telefono varchar(50),
        estado tinyint(1) DEFAULT 1,
        fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY empresa_id (empresa_id)
    ) $charset_collate;";
    
    // Tabla de configuración del plugin
    $table_config = $wpdb->prefix . 'checkout_condicional_config_v1';
    $sql_config = "CREATE TABLE $table_config (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        config_key varchar(100) NOT NULL,
        config_value text,
        fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY config_key (config_key)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_empresas);
    dbDelta($sql_agencias);
    dbDelta($sql_config);
    
    // Insertar datos de ejemplo solo si las tablas están vacías
    $empresas_existentes = $wpdb->get_var("SELECT COUNT(*) FROM $table_empresas");
    if ($empresas_existentes == 0) {
        $wpdb->insert($table_empresas, array('nombre' => 'Olva Courier'));
        $wpdb->insert($table_empresas, array('nombre' => 'Shalom'));
        $wpdb->insert($table_empresas, array('nombre' => 'FedEx'));
        
        $empresa_olva = $wpdb->get_var("SELECT id FROM $table_empresas WHERE nombre = 'Olva Courier'");
        $empresa_shalom = $wpdb->get_var("SELECT id FROM $table_empresas WHERE nombre = 'Shalom'");
        
        if ($empresa_olva) {
            $wpdb->insert($table_agencias, array(
                'empresa_id' => $empresa_olva,
                'nombre' => 'ANDAHUAYLAS',
                'direccion' => 'Jr. Juan Francisco Ramos 559',
                'telefono' => '123'
            ));
        }
        
        if ($empresa_shalom) {
            $wpdb->insert($table_agencias, array(
                'empresa_id' => $empresa_shalom,
                'nombre' => 'Chachapoyas Co Dos De Mayo',
                'direccion' => 'Jr. Dos De Mayo Cdra. 15 S/n Chachapoyas',
                'telefono' => '12345'
            ));
        }
    }
    
    // Insertar configuración inicial
    $config_existente = $wpdb->get_var("SELECT COUNT(*) FROM $table_config WHERE config_key = 'modo_agencia'");
    if ($config_existente == 0) {
        $wpdb->insert($table_config, array(
            'config_key' => 'modo_agencia',
            'config_value' => 'lista' // 'lista' o 'texto'
        ));
    }
}

// === DESACTIVACIÓN DEL PLUGIN ===
function checkout_condicional_v1_deactivate() {
    // Limpiar cache al desactivar
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Limpiar transients
    if (function_exists('delete_transient')) {
        delete_transient('checkout_condicional_cache');
        delete_transient('empresas_envio_cache');
        delete_transient('agencias_envio_cache');
    }
    
    error_log('=== PLUGIN CHECKOUT CONDICIONAL V1 DESACTIVADO ===');
}

// === MENÚ ADMINISTRATIVO ===
add_action('admin_menu', 'checkout_condicional_v1_admin_menu');
function checkout_condicional_v1_admin_menu() {
    add_menu_page(
        'Checkout Condicional V1',
        'Checkout Condicional V1',
        'manage_options',
        'checkout-condicional-v1',
        'checkout_condicional_v1_admin_page',
        'dashicons-location',
        30
    );
}

// === PÁGINA ADMINISTRATIVA ===
function checkout_condicional_v1_admin_page() {
    global $wpdb;
    
    // Procesar formularios
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'agregar_empresa' && wp_verify_nonce($_POST['_wpnonce'], 'agregar_empresa')) {
            $nombre = sanitize_text_field($_POST['nombre_empresa']);
            $wpdb->insert($wpdb->prefix . 'empresas_envio_v1', array('nombre' => $nombre));
            echo '<div class="notice notice-success"><p>Empresa agregada exitosamente.</p></div>';
        }
        
        if ($_POST['action'] === 'agregar_agencia' && wp_verify_nonce($_POST['_wpnonce'], 'agregar_agencia')) {
            $empresa_id = intval($_POST['empresa_id']);
            $nombre = sanitize_text_field($_POST['nombre_agencia']);
            $direccion = sanitize_textarea_field($_POST['direccion_agencia']);
            $telefono = sanitize_text_field($_POST['telefono_agencia']);
            
            $wpdb->insert($wpdb->prefix . 'agencias_envio_v1', array(
                'empresa_id' => $empresa_id,
                'nombre' => $nombre,
                'direccion' => $direccion,
                'telefono' => $telefono
            ));
            echo '<div class="notice notice-success"><p>Agencia agregada exitosamente.</p></div>';
        }
    }
    
    // Obtener datos
    $empresas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}empresas_envio_v1 WHERE estado = 1 ORDER BY nombre");
    $agencias = $wpdb->get_results("SELECT a.*, e.nombre as empresa_nombre FROM {$wpdb->prefix}agencias_envio_v1 a 
                                    JOIN {$wpdb->prefix}empresas_envio_v1 e ON a.empresa_id = e.id 
                                    WHERE a.estado = 1 ORDER BY e.nombre, a.nombre");
    
    ?>
    <div class="wrap">
        <h1>Checkout Condicional V1 - Gestión de Envíos</h1>
        
        <div class="nav-tab-wrapper">
            <a href="#empresas" class="nav-tab nav-tab-active">Empresas de Envío</a>
            <a href="#agencias" class="nav-tab">Agencias</a>
            <a href="#configuracion" class="nav-tab">Configuración</a>
        </div>
        
        <!-- SECCIÓN EMPRESAS -->
        <div id="empresas" class="tab-content">
            <h2>Empresas de Envío</h2>
            
            <form method="post" style="margin-bottom: 20px;">
                <?php wp_nonce_field('agregar_empresa'); ?>
                <input type="hidden" name="action" value="agregar_empresa">
                <table class="form-table">
                    <tr>
                        <th><label for="nombre_empresa">Nombre de la Empresa:</label></th>
                        <td>
                            <input type="text" id="nombre_empresa" name="nombre_empresa" required style="width: 300px;">
                            <input type="submit" class="button button-primary" value="Agregar Empresa">
                        </td>
                    </tr>
                </table>
            </form>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Fecha Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empresas as $empresa): ?>
                    <tr>
                        <td><?php echo $empresa->id; ?></td>
                        <td><?php echo esc_html($empresa->nombre); ?></td>
                        <td><?php echo $empresa->estado ? 'Activo' : 'Inactivo'; ?></td>
                        <td><?php echo $empresa->fecha_creacion; ?></td>
                        <td>
                            <button class="button button-small editar-empresa" data-id="<?php echo $empresa->id; ?>" data-nombre="<?php echo esc_attr($empresa->nombre); ?>">
                                <span class="dashicons dashicons-edit"></span> Editar
                            </button>
                            <button class="button button-small button-link-delete eliminar-empresa" data-id="<?php echo $empresa->id; ?>">
                                <span class="dashicons dashicons-trash"></span> Eliminar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- SECCIÓN AGENCIAS -->
        <div id="agencias" class="tab-content" style="display: none;">
            <h2>Agencias de Envío</h2>
            
            <form method="post" style="margin-bottom: 20px;">
                <?php wp_nonce_field('agregar_agencia'); ?>
                <input type="hidden" name="action" value="agregar_agencia">
                <table class="form-table">
                    <tr>
                        <th><label for="empresa_id">Empresa:</label></th>
                        <td>
                            <select id="empresa_id" name="empresa_id" required>
                                <option value="">Seleccionar empresa</option>
                                <?php foreach ($empresas as $empresa): ?>
                                <option value="<?php echo $empresa->id; ?>"><?php echo esc_html($empresa->nombre); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="nombre_agencia">Nombre de la Agencia:</label></th>
                        <td>
                            <input type="text" id="nombre_agencia" name="nombre_agencia" required style="width: 300px;">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="direccion_agencia">Dirección:</label></th>
                        <td>
                            <textarea id="direccion_agencia" name="direccion_agencia" rows="3" style="width: 300px;"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="telefono_agencia">Teléfono:</label></th>
                        <td>
                            <input type="text" id="telefono_agencia" name="telefono_agencia" style="width: 200px;">
                        </td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                            <input type="submit" class="button button-primary" value="Agregar Agencia">
                        </td>
                    </tr>
                </table>
            </form>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Empresa</th>
                        <th>Nombre Agencia</th>
                        <th>Dirección</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agencias as $agencia): ?>
                    <tr>
                        <td><?php echo $agencia->id; ?></td>
                        <td><?php echo esc_html($agencia->empresa_nombre); ?></td>
                        <td><?php echo esc_html($agencia->nombre); ?></td>
                        <td><?php echo esc_html($agencia->direccion); ?></td>
                        <td><?php echo esc_html($agencia->telefono); ?></td>
                        <td><?php echo $agencia->estado ? 'Activo' : 'Inactivo'; ?></td>
                        <td>
                            <button class="button button-small editar-agencia" data-id="<?php echo $agencia->id; ?>" data-empresa="<?php echo esc_attr($agencia->empresa_id); ?>" data-nombre="<?php echo esc_attr($agencia->nombre); ?>" data-direccion="<?php echo esc_attr($agencia->direccion); ?>" data-telefono="<?php echo esc_attr($agencia->telefono); ?>">
                                <span class="dashicons dashicons-edit"></span> Editar
                            </button>
                            <button class="button button-small button-link-delete eliminar-agencia" data-id="<?php echo $agencia->id; ?>">
                                <span class="dashicons dashicons-trash"></span> Eliminar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- SECCIÓN CONFIGURACIÓN -->
        <div id="configuracion" class="tab-content" style="display: none;">
            <h2>Configuración del Checkout</h2>
            
            <?php
            // Procesar cambio de configuración
            if (isset($_POST['action']) && $_POST['action'] === 'guardar_configuracion' && wp_verify_nonce($_POST['_wpnonce'], 'guardar_configuracion')) {
                $modo_agencia = sanitize_text_field($_POST['modo_agencia']);
                
                // Debug: verificar que se recibe el valor
                error_log('Modo agencia recibido: ' . $modo_agencia);
                
                // Verificar si la tabla existe, si no, crearla
                $table_name = $wpdb->prefix . 'checkout_condicional_config_v1';
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
                
                if (!$table_exists) {
                    // Crear la tabla si no existe
                    $charset_collate = $wpdb->get_charset_collate();
                    $sql_config = "CREATE TABLE $table_name (
                        id mediumint(9) NOT NULL AUTO_INCREMENT,
                        config_key varchar(100) NOT NULL,
                        config_value text,
                        fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY config_key (config_key)
                    ) $charset_collate;";
                    
                    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                    dbDelta($sql_config);
                    
                    echo '<div class="notice notice-warning"><p>Tabla de configuración creada. Intenta guardar nuevamente.</p></div>';
                } else {
                    $result = $wpdb->replace($table_name, array(
                        'config_key' => 'modo_agencia',
                        'config_value' => $modo_agencia
                    ));
                    
                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>Configuración guardada exitosamente. Modo: ' . esc_html($modo_agencia) . '</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Error al guardar la configuración: ' . $wpdb->last_error . '</p></div>';
                    }
                }
            }
            
            // Obtener configuración actual
            $modo_actual = $wpdb->get_var("SELECT config_value FROM {$wpdb->prefix}checkout_condicional_config_v1 WHERE config_key = 'modo_agencia'");
            if (!$modo_actual) {
                $modo_actual = 'lista';
            }
            
            // Debug: mostrar el valor actual
            echo '<!-- Debug: Modo actual = ' . esc_html($modo_actual) . ' -->';
            ?>
            
            <form method="post" style="margin-bottom: 20px;">
                <?php wp_nonce_field('guardar_configuracion'); ?>
                <input type="hidden" name="action" value="guardar_configuracion">
                <table class="form-table">
                    <tr>
                        <th><label for="modo_agencia">Modo de selección de agencia:</label></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="modo_agencia" value="lista" <?php checked($modo_actual, 'lista'); ?>>
                                    <strong>Lista predefinida</strong> - El cliente selecciona de una lista de agencias
                                </label><br><br>
                                <label>
                                    <input type="radio" name="modo_agencia" value="texto" <?php checked($modo_actual, 'texto'); ?>>
                                    <strong>Campo de texto libre</strong> - El cliente puede escribir cualquier agencia
                                </label>
                            </fieldset>
                            <p class="description">
                                Esta configuración afecta cómo se muestra el campo de agencia en el checkout.
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Guardar Configuración">
                </p>
            </form>
            
            <div class="card" style="max-width: 600px;">
                <h3>Información sobre los modos:</h3>
                <ul>
                    <li><strong>Lista predefinida:</strong> Los clientes solo pueden seleccionar agencias que estén registradas en la base de datos.</li>
                    <li><strong>Campo de texto libre:</strong> Los clientes pueden escribir cualquier nombre de agencia que deseen.</li>
                </ul>
                <p><em>En ambos casos, el valor se guardará en los metadatos de la orden como <code>_billing_agencia_envio</code>.</em></p>
            </div>
        </div>
    </div>
    
    <style>
    .tab-content { margin-top: 20px; }
    .nav-tab { cursor: pointer; }
    
    .button-small {
        margin: 2px;
        padding: 4px 8px;
        font-size: 11px;
    }
    
    .editar-empresa, .editar-agencia {
        background: #0073aa;
        border-color: #0073aa;
        color: white;
    }
    
    .eliminar-empresa, .eliminar-agencia {
        background: #dc3232;
        border-color: #dc3232;
        color: white;
    }
    
    .modal-edicion {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 50%;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close:hover {
        color: #000;
    }
    </style>
    
    <!-- Modal para editar empresa -->
    <div id="modal-editar-empresa" class="modal-edicion">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Editar Empresa</h3>
            <form id="form-editar-empresa">
                <input type="hidden" id="edit_empresa_id" name="empresa_id">
                <table class="form-table">
                    <tr>
                        <th><label for="edit_nombre_empresa">Nombre:</label></th>
                        <td><input type="text" id="edit_nombre_empresa" name="nuevo_nombre" required style="width: 300px;"></td>
                    </tr>
                </table>
                <input type="submit" class="button button-primary" value="Actualizar Empresa">
            </form>
        </div>
    </div>
    
    <!-- Modal para editar agencia -->
    <div id="modal-editar-agencia" class="modal-edicion">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Editar Agencia</h3>
            <form id="form-editar-agencia">
                <input type="hidden" id="edit_agencia_id" name="agencia_id">
                <table class="form-table">
                    <tr>
                        <th><label for="edit_nombre_agencia">Nombre:</label></th>
                        <td><input type="text" id="edit_nombre_agencia" name="nuevo_nombre" required style="width: 300px;"></td>
                    </tr>
                    <tr>
                        <th><label for="edit_direccion_agencia">Dirección:</label></th>
                        <td><textarea id="edit_direccion_agencia" name="nueva_direccion" rows="3" style="width: 300px;"></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="edit_telefono_agencia">Teléfono:</label></th>
                        <td><input type="text" id="edit_telefono_agencia" name="nuevo_telefono" style="width: 200px;"></td>
                    </tr>
                </table>
                <input type="submit" class="button button-primary" value="Actualizar Agencia">
            </form>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Navegación por pestañas
        $('.nav-tab').click(function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.tab-content').hide();
            $(target).show();
        });
        
        // Editar empresa
        $('.editar-empresa').click(function() {
            var id = $(this).data('id');
            var nombre = $(this).data('nombre');
            
            $('#edit_empresa_id').val(id);
            $('#edit_nombre_empresa').val(nombre);
            $('#modal-editar-empresa').show();
        });
        
        // Editar agencia
        $('.editar-agencia').click(function() {
            var id = $(this).data('id');
            var nombre = $(this).data('nombre');
            var direccion = $(this).data('direccion');
            var telefono = $(this).data('telefono');
            
            $('#edit_agencia_id').val(id);
            $('#edit_nombre_agencia').val(nombre);
            $('#edit_direccion_agencia').val(direccion);
            $('#edit_telefono_agencia').val(telefono);
            $('#modal-editar-agencia').show();
        });
        
        // Cerrar modales
        $('.close').click(function() {
            $('.modal-edicion').hide();
        });
        
        // Cerrar modal al hacer clic fuera
        $(window).click(function(e) {
            if ($(e.target).hasClass('modal-edicion')) {
                $('.modal-edicion').hide();
            }
        });
        
        // Formulario editar empresa
        $('#form-editar-empresa').submit(function(e) {
            e.preventDefault();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'editar_empresa_v1',
                    empresa_id: $('#edit_empresa_id').val(),
                    nuevo_nombre: $('#edit_nombre_empresa').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Empresa actualizada correctamente');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }
            });
        });
        
        // Formulario editar agencia
        $('#form-editar-agencia').submit(function(e) {
            e.preventDefault();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'editar_agencia_v1',
                    agencia_id: $('#edit_agencia_id').val(),
                    nuevo_nombre: $('#edit_nombre_agencia').val(),
                    nueva_direccion: $('#edit_direccion_agencia').val(),
                    nuevo_telefono: $('#edit_telefono_agencia').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Agencia actualizada correctamente');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }
            });
        });
        
        // Eliminar empresa
        $('.eliminar-empresa').click(function() {
            if (confirm('¿Estás seguro de que quieres eliminar esta empresa?')) {
                var id = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'eliminar_empresa_v1',
                        empresa_id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Empresa eliminada correctamente');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            }
        });
        
        // Eliminar agencia
        $('.eliminar-agencia').click(function() {
            if (confirm('¿Estás seguro de que quieres eliminar esta agencia?')) {
                var id = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'eliminar_agencia_v1',
                        agencia_id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Agencia eliminada correctamente');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            }
        });
    });
    </script>
    <?php
}

// === CAMPOS DEL CHECKOUT ===
add_action('woocommerce_checkout_fields', 'checkout_extra_fields_integration_v1', 101);
function checkout_extra_fields_integration_v1($fields) {
    global $wpdb;

    // LOG: Inicio de la función
    error_log('=== CHECKOUT CONDICIONAL V1 - INTEGRACIÓN UBIGEO ===');
    error_log('Función checkout_extra_fields_integration_v1 ejecutada');

    // Obtener empresas activas
    $empresas = $wpdb->get_results("SELECT id, nombre FROM {$wpdb->prefix}empresas_envio_v1 WHERE estado = 1 ORDER BY nombre");
    error_log('Empresas encontradas: ' . count($empresas));
    
    // Obtener configuración del modo de agencia
    $modo_agencia = $wpdb->get_var("SELECT config_value FROM {$wpdb->prefix}checkout_condicional_config_v1 WHERE config_key = 'modo_agencia'");
    if (!$modo_agencia) {
        $modo_agencia = 'lista';
    }
    error_log('Modo de agencia: ' . $modo_agencia);

    // Preparar opciones de empresas
    $empresas_options = array('' => 'Seleccionar empresa');
    foreach ($empresas as $empresa) {
        $empresas_options[$empresa->id] = $empresa->nombre;
    }
    error_log('Opciones de empresas preparadas: ' . count($empresas_options) . ' opciones');

    // === CAMPOS DE ENVÍO CON CONTENEDOR ESTILIZADO ===
    // Crear un campo personalizado que actúe como contenedor
    $fields['order']['shipping_info_container'] = array(
        'type' => 'shipping_info_container',
        'label' => '',
        'required' => false,
        'class' => array('shipping-info-container'),
        'priority' => 13,
        'custom_attributes' => array(
            'data-empresas' => json_encode($empresas_options),
            'data-modo-agencia' => $modo_agencia
        )
    );
    error_log('Contenedor de información de envío añadido con prioridad 13');

    error_log('Total de campos añadidos a la sección order: ' . count($fields['order']));
    error_log('=== FIN INTEGRACIÓN UBIGEO ===');

    return $fields;
}

// === CAMPO PERSONALIZADO PARA CONTENEDOR ===
add_filter('woocommerce_form_field_shipping_info_container', 'render_shipping_info_container_field', 10, 4);
function render_shipping_info_container_field($field, $key, $args, $value) {
    global $wpdb;
    
    // Obtener datos de las empresas
    $empresas = $wpdb->get_results("SELECT id, nombre FROM {$wpdb->prefix}empresas_envio_v1 WHERE estado = 1 ORDER BY nombre");
    $modo_agencia = $wpdb->get_var("SELECT config_value FROM {$wpdb->prefix}checkout_condicional_config_v1 WHERE config_key = 'modo_agencia'");
    if (!$modo_agencia) {
        $modo_agencia = 'lista';
    }
    
    // Preparar opciones de empresas
    $empresas_options = array('' => 'Seleccionar empresa');
    foreach ($empresas as $empresa) {
        $empresas_options[$empresa->id] = $empresa->nombre;
    }
    
    ob_start();
    ?>
    <div class="shipping-info-wrapper" id="shipping-info-wrapper" style="display: none;">
        <div class="shipping-info-header">
            <h3 class="shipping-info-title">
                Información de Envío
            </h3>
            <p class="shipping-info-description">Selecciona la empresa y agencia de envío para tu pedido</p>
        </div>
        
        <div class="shipping-info-content">
            <!-- Campos de Empresa y Agencia -->
            <div class="shipping-fields-row" id="shipping-company-fields">
                <div class="shipping-field-group">
                    <label for="billing_empresa_envio" class="shipping-field-label">
                        <span class="label-text">Empresa de envío</span>
                        <span class="required-asterisk">*</span>
                    </label>
                    <div class="shipping-field-wrapper">
                        <select name="billing_empresa_envio" id="billing_empresa_envio" class="shipping-field-select" required>
                            <?php foreach ($empresas_options as $id => $nombre): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($id, ''); ?>>
                                    <?php echo esc_html($nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-icon">
                            <i class="dropdown-icon">▼</i>
                        </div>
                    </div>
                </div>
                
                <div class="shipping-field-group">
                    <label for="billing_agencia_envio" class="shipping-field-label">
                        <span class="label-text">Agencia de envío</span>
                        <span class="required-asterisk">*</span>
                    </label>
                    <div class="shipping-field-wrapper">
                        <?php if ($modo_agencia === 'texto'): ?>
                            <input type="text" name="billing_agencia_envio" id="billing_agencia_envio" 
                                   class="shipping-field-input" placeholder="Escribe el nombre de la agencia" required>
                        <?php else: ?>
                            <select name="billing_agencia_envio" id="billing_agencia_envio" class="shipping-field-select" required>
                                <option value="" disabled selected>Seleccionar agencia</option>
                            </select>
                            <div class="field-icon">
                                <i class="dropdown-icon">▼</i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// === GUARDAR CAMPOS ===
add_action('woocommerce_checkout_update_order_meta', 'bytezon_save_extra_checkout_fields_v1');
function bytezon_save_extra_checkout_fields_v1($order_id) {
    global $wpdb;
    
    error_log('=== GUARDAR CAMPOS CHECKOUT CONDICIONAL V1 ===');
    error_log('Order ID: ' . $order_id);
    
    // Obtener el objeto de la orden
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log('ERROR: No se pudo obtener la orden con ID: ' . $order_id);
        return;
    }
    
    // Debug: Log de todos los campos POST
    error_log('=== CAMPOS POST RECIBIDOS ===');
    error_log('rt_ubigeo_departamento: ' . ($_POST['rt_ubigeo_departamento'] ?? 'NO ENCONTRADA'));
    error_log('rt_ubigeo_provincia: ' . ($_POST['rt_ubigeo_provincia'] ?? 'NO ENCONTRADA'));
    error_log('billing_empresa_envio: ' . ($_POST['billing_empresa_envio'] ?? 'NO ENCONTRADA'));
    error_log('billing_agencia_envio: ' . ($_POST['billing_agencia_envio'] ?? 'NO ENCONTRADA'));
    error_log('=== FIN CAMPOS POST ===');
    
    // Guardar empresa de envío (nombre)
    if (!empty($_POST['billing_empresa_envio'])) {
        $empresa_id = intval($_POST['billing_empresa_envio']);
        error_log('Guardando empresa con ID: ' . $empresa_id);
        
        $empresa_nombre = $wpdb->get_var($wpdb->prepare(
            "SELECT nombre FROM {$wpdb->prefix}empresas_envio_v1 WHERE id = %d",
            $empresa_id
        ));
        
        if ($empresa_nombre) {
            $order->add_meta_data('_billing_empresa_envio', $empresa_nombre, true);
            error_log('Empresa guardada exitosamente: ' . $empresa_nombre);
        } else {
            error_log('ERROR: No se encontró empresa con ID: ' . $empresa_id);
        }
    } else {
        error_log('No hay empresa de envío para guardar');
    }
    
    // Guardar agencia de envío (nombre)
    if (!empty($_POST['billing_agencia_envio'])) {
        // Obtener configuración del modo de agencia
        $modo_agencia = $wpdb->get_var("SELECT config_value FROM {$wpdb->prefix}checkout_condicional_config_v1 WHERE config_key = 'modo_agencia'");
        if (!$modo_agencia) {
            $modo_agencia = 'lista';
        }
        error_log('Modo de agencia para guardar: ' . $modo_agencia);
        
        if ($modo_agencia === 'texto') {
            // Modo texto: guardar directamente el valor ingresado
            $agencia_nombre = sanitize_text_field($_POST['billing_agencia_envio']);
            $order->add_meta_data('_billing_agencia_envio', $agencia_nombre, true);
            error_log('Agencia guardada (texto): ' . $agencia_nombre);
        } else {
            // Modo lista: buscar el nombre en la base de datos
            $agencia_id = intval($_POST['billing_agencia_envio']);
            error_log('Buscando agencia con ID: ' . $agencia_id);
            
            $agencia_nombre = $wpdb->get_var($wpdb->prepare(
                "SELECT nombre FROM {$wpdb->prefix}agencias_envio_v1 WHERE id = %d",
                $agencia_id
            ));
            
            if ($agencia_nombre) {
                $order->add_meta_data('_billing_agencia_envio', $agencia_nombre, true);
                error_log('Agencia guardada (lista): ' . $agencia_nombre);
            } else {
                error_log('ERROR: No se encontró agencia con ID: ' . $agencia_id);
            }
        }
    } else {
        error_log('No hay agencia de envío para guardar');
    }
    
    // Las fechas de entrega ya no se usan
    
    // Guardar los metadatos en la base de datos
    $result = $order->save();
    if ($result) {
        error_log('Orden guardada exitosamente con metadatos actualizados');
    } else {
        error_log('ERROR: No se pudo guardar la orden');
    }
    
    error_log('=== FIN GUARDAR CAMPOS ===');
}

// === MOSTRAR EN ADMIN ===
add_action('woocommerce_admin_order_data_after_billing_address', 'bytezon_show_extra_checkout_fields_admin_v1', 10, 1);
function bytezon_show_extra_checkout_fields_admin_v1($order) {
    $empresa = $order->get_meta('_billing_empresa_envio');
    $agencia = $order->get_meta('_billing_agencia_envio');
    
    if ($empresa) {
        echo '<p><strong>' . __('Empresa de envío') . ':</strong> ' . esc_html($empresa) . '</p>';
    }

    if ($agencia) {
        echo '<p><strong>' . __('Agencia de envío') . ':</strong> ' . esc_html($agencia) . '</p>';
    }
}

// === AJAX PARA OBTENER AGENCIAS ===
add_action('wp_ajax_get_agencias_checkout_v1', 'get_agencias_checkout_v1_ajax');
add_action('wp_ajax_nopriv_get_agencias_checkout_v1', 'get_agencias_checkout_v1_ajax');
function get_agencias_checkout_v1_ajax() {
    global $wpdb;
    
    error_log('=== AJAX GET AGENCIAS CHECKOUT V1 ===');
    
    $empresa_id = intval($_POST['empresa_id']);
    error_log('Empresa ID recibido: ' . $empresa_id);
    
    if (!$empresa_id) {
        error_log('ERROR: ID de empresa no válido');
        wp_send_json_error('ID de empresa no válido');
        return;
    }
    
    $agencias = $wpdb->get_results($wpdb->prepare(
        "SELECT id, nombre, direccion, telefono 
         FROM {$wpdb->prefix}agencias_envio_v1 
         WHERE empresa_id = %d AND estado = 1 
         ORDER BY nombre",
        $empresa_id
    ));
    
    error_log('Agencias encontradas: ' . count($agencias));
    if (count($agencias) > 0) {
        error_log('Primera agencia: ' . $agencias[0]->nombre);
    }
    
    error_log('=== FIN AJAX GET AGENCIAS ===');
    wp_send_json_success($agencias);
}

// === JAVASCRIPT CONDICIONAL ===
add_action('wp_footer', 'bytezon_conditional_checkout_fields_v1');
function bytezon_conditional_checkout_fields_v1() {
    if (is_checkout()) :
        // Parámetro de versión para forzar recarga
        $version = '1.1.' . time();
    ?>
    <style>
    /* === ESTILOS PROFESIONALES PARA CONTENEDOR DE ENVÍO === */
    /* Versión: <?php echo $version; ?> - Actualización forzada */
    
    /* Contenedor principal */
    .shipping-info-wrapper {
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border: 1px solid #e1e5e9;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin: 10px 0;
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .shipping-info-wrapper:hover {
        box-shadow: 0 6px 25px rgba(0, 0, 0, 0.12);
        transform: translateY(-2px);
    }
    
    /* Header del contenedor */
    .shipping-info-header {
        background: #007cba;
        color: white;
        padding: 20px 20px 0px 20px;
        position: relative;
        overflow: hidden;
        border-radius: 12px 12px 0 0;
    }
    
    .shipping-info-title {
        margin: 0 0 5px 0;
        font-size: 16px;
        font-weight: 600;
        color: white;
        position: relative;
        z-index: 1;
    }
    
    .shipping-info-description {
        margin: 0;
        font-size: 12px;
        opacity: 1;
        position: relative;
        z-index: 1;
        line-height: 1.4;
    }
    
    /* Contenido del contenedor */
    .shipping-info-content {
        padding: 0px 20px 0px 20px;
        background: white;
    }
    
    /* Fila de campos */
    .shipping-fields-row {
        display: flex;
        gap: 15px;
        margin-bottom: 0;
    }
    
    .shipping-fields-row:last-child {
        margin-bottom: 0;
    }
    
    /* Grupo de campo individual */
    .shipping-field-group {
        flex: 1;
        position: relative;
    }
    
    /* Labels */
    .shipping-field-label {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
        position: relative;
    }
    
    .label-text {
        display: inline-block;
    }
    
    .required-asterisk {
        color: #e74c3c;
        margin-left: 3px;
        font-weight: bold;
        font-size: 14px;
        line-height: 1;
    }
    
    .optional-text {
        color: #7f8c8d;
        font-weight: 400;
        font-size: 12px;
        margin-left: 5px;
    }
    
    /* Wrapper del campo */
    .shipping-field-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    /* Campos de entrada */
    .shipping-field-select,
    .shipping-field-input {
        width: 100%;
        padding: 12px 40px 12px 14px;
        border: 1px solid #d0d0d0;
        border-radius: 8px;
        font-size: 14px;
        background: #ffffff;
        color: #2c3e50;
        transition: all 0.3s ease;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
    }
    
    .shipping-field-select:focus,
    .shipping-field-input:focus {
        border-color: #007cba;
        box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
        outline: none;
        transform: translateY(-1px);
    }
    
    .shipping-field-select:hover,
    .shipping-field-input:hover {
        border-color: #bdc3c7;
        transform: translateY(-1px);
    }
    
    /* Iconos de los campos */
    .field-icon {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        color: #7f8c8d;
        font-size: 12px;
        transition: color 0.3s ease;
    }
    
    .shipping-field-select:focus + .field-icon,
    .shipping-field-input:focus + .field-icon {
        color: #007cba;
    }
    
    .dropdown-icon {
        font-size: 10px;
    }
    
    .calendar-icon {
        font-size: 14px;
    }
    
    /* Estados especiales */
    .shipping-field-select option[disabled] {
        color: #bdc3c7;
        font-style: italic;
    }
    
    .shipping-field-select option:not([disabled]) {
        color: #2c3e50;
    }
    
    /* Animaciones */
    .shipping-fields-row {
        animation: slideInUp 0.5s ease-out;
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .shipping-info-wrapper {
            margin: 15px 0;
            border-radius: 8px;
        }
        
        .shipping-info-header {
            padding: 12px 15px;
        }
        
        .shipping-info-title {
            font-size: 15px;
        }
        
        .shipping-info-description {
            font-size: 11px;
        }
        
        .shipping-info-content {
            padding: 15px;
        }
        
        .shipping-fields-row {
            flex-direction: column;
            gap: 12px;
        }
        
        .shipping-field-select,
        .shipping-field-input {
            padding: 10px 35px 10px 12px;
        }
    }
    
    @media (max-width: 480px) {
        .shipping-info-header {
            padding: 10px 12px;
        }
        
        .shipping-info-title {
            font-size: 14px;
        }
        
        .shipping-info-description {
            font-size: 10px;
        }
        
        .shipping-info-content {
            padding: 12px;
        }
        
        .shipping-field-select,
        .shipping-field-input {
            padding: 8px 30px 8px 10px;
            font-size: 13px;
        }
    }
    
    /* Integración con WooCommerce */
    .woocommerce-checkout .shipping-info-wrapper {
        margin-top: 15px;
    }
    
    /* Ocultar campos cuando no son necesarios */
    .shipping-fields-row[style*="display: none"] {
        display: none !important;
    }
    </style>
    
    <script type="text/javascript">
        // Versión: <?php echo $version; ?> - Actualización forzada
        jQuery(document).ready(function($) {
            console.log('=== CHECKOUT CONDICIONAL V1 - JAVASCRIPT INICIADO ===');
            console.log('Versión del plugin: <?php echo $version; ?>');
            
            // Ya no necesitamos lista específica de provincias de Lima
            
            // Función para verificar si debe mostrar información de envío
            function shouldShowShippingInfo(departamento, provincia) {
                console.log('🔍 shouldShowShippingInfo - Departamento ID:', departamento, 'Provincia ID:', provincia);
                
                // Si no hay departamento, no mostrar
                if (!departamento) {
                    console.log('❌ No hay departamento - NO mostrar envío');
                    return false;
                }
                
                // Obtener nombres reales de los selects
                var deptText = $('#rt_ubigeo_departamento option:selected').text().toLowerCase().trim();
                var provText = $('#rt_ubigeo_provincia option:selected').text().toLowerCase().trim();
                
                console.log('🔍 Textos reales - DeptText:', deptText, 'ProvText:', provText);
                
                // ❌ NO mostrar cuando: Departamento = Callao (independientemente de la provincia)
                if (deptText === 'callao') {
                    console.log('❌ Callao (cualquier provincia) - NO mostrar envío');
                    return false;
                }
                
                // ✅ SÍ mostrar cuando: Departamento ≠ Lima y Departamento ≠ Callao (inmediatamente)
                if (deptText !== 'lima' && deptText !== 'callao') {
                    console.log('✅ Departamento ≠ Lima/Callao - SÍ mostrar envío');
                    return true;
                }
                
                // Para Lima, validar provincia
                if (deptText === 'lima') {
                    // ❌ NO mostrar cuando: No hay provincia seleccionada (solo para Lima)
                    if (!provincia || provText === 'seleccione una provincia' || provText === 'seleccionar provincia' || provText === '') {
                        console.log('❌ Lima sin provincia seleccionada - NO mostrar envío');
                        return false;
                    }
                    
                    // ❌ NO mostrar cuando: Departamento = Lima y Provincia = Lima
                    if (provText === 'lima') {
                        console.log('❌ Lima + Lima - NO mostrar envío');
                        return false;
                    }
                    
                    // ❌ NO mostrar cuando: Departamento = Lima y Provincia = Callao
                    if (provText === 'callao') {
                        console.log('❌ Lima + Callao - NO mostrar envío');
                        return false;
                    }
                    
                    // ✅ SÍ mostrar cuando: Departamento = Lima y Provincia ≠ Lima y Provincia ≠ Callao
                    console.log('✅ Lima + Provincia ≠ Lima/Callao - SÍ mostrar envío');
                    return true;
                }
                
                // Por defecto, no mostrar
                console.log('❌ Condición no reconocida - NO mostrar envío');
                return false;
            }
            
            // Función para mostrar/ocultar campos según departamento y provincia
            function toggleFieldsByLocation() {
                var departamento = $('#rt_ubigeo_departamento').val();
                var provincia = $('#rt_ubigeo_provincia').val();
                
                console.log('toggleFieldsByLocation - Departamento:', departamento, 'Provincia:', provincia);
                
                var showShipping = shouldShowShippingInfo(departamento, provincia);
                
                if (showShipping) {
                    console.log('✅ Mostrando información de envío');
                    $('#shipping-info-wrapper').show();
                } else {
                    console.log('❌ Ocultando información de envío');
                    $('#shipping-info-wrapper').hide();
                }
                
                // Log del estado final del contenedor
                console.log('Estado final del contenedor - Visible:', $('#shipping-info-wrapper').is(':visible'));
            }
            
            // Función para inicializar campos al cargar la página
            function initializeFields() {
                var departamento = $('#rt_ubigeo_departamento').val();
                var provincia = $('#rt_ubigeo_provincia').val();
                
                console.log('initializeFields - Departamento inicial ID:', departamento, 'Provincia inicial ID:', provincia);
                
                // Si hay departamento seleccionado, evaluar
                if (departamento) {
                    var deptText = $('#rt_ubigeo_departamento option:selected').text().toLowerCase().trim();
                    console.log('initializeFields - Departamento texto:', deptText);
                    
                    if (deptText === 'callao') {
                        console.log('Inicialización: Callao - Ocultando');
                        $('#shipping-info-wrapper').hide();
                    } else if (deptText === 'lima') {
                        console.log('Inicialización: Lima - Evaluando con provincia');
                        toggleFieldsByLocation();
                    } else {
                        console.log('Inicialización: Departamento ≠ Lima/Callao - Mostrando');
                        $('#shipping-info-wrapper').show();
                    }
                } else {
                    console.log('Inicialización: No hay departamento - Manteniendo oculto');
                    $('#shipping-info-wrapper').hide();
                }
            }
            
            // Función para cargar agencias
            function loadAgencies(empresaId) {
                console.log('loadAgencies - Empresa ID:', empresaId);
                
                if (!empresaId || empresaId === '') {
                    console.log('loadAgencies - Empresa ID vacío, saliendo');
                    return;
                }
                
                $('#billing_agencia_envio').html('<option value="" disabled selected>Cargando agencias...</option>');
                console.log('loadAgencies - Iniciando AJAX para cargar agencias');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'get_agencias_checkout_v1',
                        empresa_id: empresaId
                    },
                    success: function(response) {
                        console.log('loadAgencies - Respuesta AJAX:', response);
                        
                        if (response.success && response.data && response.data.length > 0) {
                            var options = '<option value="" disabled selected>Seleccionar agencia</option>';
                            
                            response.data.forEach(function(agencia) {
                                options += '<option value="' + agencia.id + '">' + agencia.nombre + '</option>';
                            });
                            
                            $('#billing_agencia_envio').html(options);
                            console.log('loadAgencies - Agencias cargadas:', response.data.length);
                        } else {
                            $('#billing_agencia_envio').html('<option value="" disabled selected>No hay agencias disponibles</option>');
                            console.log('loadAgencies - No hay agencias disponibles');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('loadAgencies - Error AJAX:', error);
                        console.log('loadAgencies - Response:', xhr.responseText);
                        $('#billing_agencia_envio').html('<option value="" disabled selected>Error al cargar agencias</option>');
                    }
                });
            }
            
            // Event Listeners para Ubigeo
            $('#rt_ubigeo_departamento').on('change', function() {
                console.log('Evento: Cambio en rt_ubigeo_departamento');
                // Limpiar campos cuando cambie el departamento
                $('#billing_empresa_envio').val('').prop('selectedIndex', 0);
                $('#billing_agencia_envio').val('').prop('selectedIndex', 0);
                
                // Obtener nombre real del departamento
                var deptText = $(this).find('option:selected').text().toLowerCase().trim();
                console.log('Departamento seleccionado (texto):', deptText);
                
                // Si el departamento es Callao, ocultar inmediatamente
                if (deptText === 'callao') {
                    console.log('Callao seleccionado - Ocultando inmediatamente');
                    $('#shipping-info-wrapper').hide();
                }
                // Si el departamento es Lima, evaluar con la provincia actual (NO mostrar hasta seleccionar provincia)
                else if (deptText === 'lima') {
                    console.log('Lima seleccionado - Evaluando con provincia');
                    toggleFieldsByLocation();
                }
                // Si el departamento es diferente a Lima y Callao, mostrar inmediatamente
                else {
                    console.log('Departamento ≠ Lima/Callao - Mostrando inmediatamente');
                    $('#shipping-info-wrapper').show();
                }
            });
            
            $('#rt_ubigeo_provincia').on('change', function() {
                console.log('Evento: Cambio en rt_ubigeo_provincia');
                // Limpiar campos cuando cambie la provincia
                $('#billing_empresa_envio').val('').prop('selectedIndex', 0);
                $('#billing_agencia_envio').val('').prop('selectedIndex', 0);
                toggleFieldsByLocation();
            });
            
            // Event Listeners para empresa de envío
            $('#billing_empresa_envio').on('change', function() {
                var empresaId = $(this).val();
                console.log('Evento: Cambio en billing_empresa_envio - Empresa ID:', empresaId);
                
                $('#billing_agencia_envio').val('');
                
                // Verificar si el campo de agencia es un select o input
                if ($('#billing_agencia_envio').is('select')) {
                    console.log('Campo agencia es SELECT - Modo lista');
                    // Modo lista: cargar agencias
                    if (empresaId && empresaId !== '') {
                        loadAgencies(empresaId);
                    }
                } else {
                    console.log('Campo agencia es INPUT - Modo texto');
                }
            });

            // Verificar que los campos existen
            console.log('Verificando existencia de campos:');
            console.log('- rt_ubigeo_departamento:', $('#rt_ubigeo_departamento').length);
            console.log('- rt_ubigeo_provincia:', $('#rt_ubigeo_provincia').length);
            console.log('- billing_empresa_envio:', $('#billing_empresa_envio').length);
            console.log('- billing_agencia_envio:', $('#billing_agencia_envio').length);
            console.log('- shipping-info-wrapper:', $('#shipping-info-wrapper').length);
            console.log('- shipping-company-fields:', $('#shipping-company-fields').length);
            
            // Verificar estado inicial del contenedor
            console.log('Estado inicial del contenedor:');
            console.log('- shipping-info-wrapper visible:', $('#shipping-info-wrapper').is(':visible'));
            console.log('- shipping-info-wrapper display:', $('#shipping-info-wrapper').css('display'));

            // Inicialización
            initializeFields();
            
            // Re-inicializar cuando se actualice el checkout
            $(document.body).on('updated_checkout', function() {
                console.log('Evento: updated_checkout - Re-inicializando campos');
                setTimeout(initializeFields, 100);
            });
            
            console.log('=== CHECKOUT CONDICIONAL V1 - JAVASCRIPT CONFIGURADO ===');
        });
    </script>
    <?php
    endif;
}

// === VALIDACIÓN DE CAMPOS ===
add_action('woocommerce_checkout_process', 'validate_extra_checkout_fields_v1');
function validate_extra_checkout_fields_v1() {
    error_log('=== VALIDACIÓN CHECKOUT CONDICIONAL V1 ===');
    
    // Obtener departamento y provincia desde ubigeo
    $departamento_id = $_POST['rt_ubigeo_departamento'] ?? '';
    $provincia_id = $_POST['rt_ubigeo_provincia'] ?? '';
    $departamento_nombre = '';
    $provincia_nombre = '';
    
    error_log('Departamento ID desde POST: ' . $departamento_id);
    error_log('Provincia ID desde POST: ' . $provincia_id);
    
    if ($departamento_id) {
        global $wpdb;
        $departamento_nombre = $wpdb->get_var($wpdb->prepare(
            "SELECT departamento FROM {$wpdb->prefix}ubigeo_departamentos WHERE id = %d",
            $departamento_id
        ));
        error_log('Departamento nombre desde BD: ' . $departamento_nombre);
    }
    
    if ($provincia_id) {
        global $wpdb;
        $provincia_nombre = $wpdb->get_var($wpdb->prepare(
            "SELECT provincia FROM {$wpdb->prefix}ubigeo_provincias WHERE id = %d",
            $provincia_id
        ));
        error_log('Provincia nombre desde BD: ' . $provincia_nombre);
    }
    
    // Verificar si debe mostrar información de envío
    $shouldShowShipping = false;
    
    if ($departamento_nombre && $provincia_nombre) {
        $deptLower = strtolower($departamento_nombre);
        $provLower = strtolower($provincia_nombre);
        
        // ❌ NO mostrar cuando: Departamento = Callao (independientemente de la provincia)
        if ($deptLower === 'callao') {
            error_log('Callao (cualquier provincia) - NO validar envío');
            $shouldShowShipping = false;
        }
        // ❌ NO mostrar cuando: Departamento = Lima y Provincia = Lima
        elseif ($deptLower === 'lima' && $provLower === 'lima') {
            error_log('Lima + Lima - NO validar envío');
            $shouldShowShipping = false;
        }
        // ❌ NO mostrar cuando: Departamento = Lima y Provincia = Callao
        elseif ($deptLower === 'lima' && $provLower === 'callao') {
            error_log('Lima + Callao - NO validar envío');
            $shouldShowShipping = false;
        }
        // ✅ SÍ mostrar cuando: Departamento ≠ Lima
        elseif ($deptLower !== 'lima') {
            error_log('Departamento ≠ Lima - SÍ validar envío');
            $shouldShowShipping = true;
        }
        // ✅ SÍ mostrar cuando: Departamento = Lima y Provincia ≠ Lima y Provincia ≠ Callao
        elseif ($deptLower === 'lima' && $provLower !== 'lima' && $provLower !== 'callao') {
            error_log('Lima + Provincia ≠ Lima/Callao - SÍ validar envío');
            $shouldShowShipping = true;
        }
    }
    
    if ($shouldShowShipping) {
        error_log('Validando empresa y agencia para: ' . $departamento_nombre . ' - ' . $provincia_nombre);
        
        if (empty($_POST['billing_empresa_envio'])) {
            error_log('ERROR: Empresa de envío faltante');
            wc_add_notice(__('Por favor selecciona una empresa de envío.'), 'error');
        } else {
            error_log('Empresa de envío seleccionada: ' . $_POST['billing_empresa_envio']);
        }
        
        if (empty($_POST['billing_agencia_envio'])) {
            error_log('ERROR: Agencia de envío faltante');
            wc_add_notice(__('Por favor selecciona una agencia de envío.'), 'error');
        } else {
            error_log('Agencia de envío seleccionada: ' . $_POST['billing_agencia_envio']);
        }
    } else {
        error_log('NO se requiere validación de envío para: ' . $departamento_nombre . ' - ' . $provincia_nombre);
    }
    
    error_log('=== FIN VALIDACIÓN ===');
}

// === AJAX PARA ADMIN ===
add_action('wp_ajax_editar_empresa_v1', 'editar_empresa_v1_ajax');
function editar_empresa_v1_ajax() {
    global $wpdb;
    
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos');
    }
    
    $empresa_id = intval($_POST['empresa_id']);
    $nuevo_nombre = sanitize_text_field($_POST['nuevo_nombre']);
    
    $resultado = $wpdb->update(
        $wpdb->prefix . 'empresas_envio_v1',
        array('nombre' => $nuevo_nombre),
        array('id' => $empresa_id),
        array('%s'),
        array('%d')
    );
    
    if ($resultado !== false) {
        wp_send_json_success('Empresa actualizada correctamente');
    } else {
        wp_send_json_error('Error al actualizar la empresa');
    }
}

add_action('wp_ajax_eliminar_empresa_v1', 'eliminar_empresa_v1_ajax');
function eliminar_empresa_v1_ajax() {
    global $wpdb;
    
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos');
    }
    
    $empresa_id = intval($_POST['empresa_id']);
    
    $resultado = $wpdb->update(
        $wpdb->prefix . 'empresas_envio_v1',
        array('estado' => 0),
        array('id' => $empresa_id),
        array('%d'),
        array('%d')
    );
    
    if ($resultado !== false) {
        wp_send_json_success('Empresa eliminada correctamente');
    } else {
        wp_send_json_error('Error al eliminar la empresa');
    }
}

add_action('wp_ajax_editar_agencia_v1', 'editar_agencia_v1_ajax');
function editar_agencia_v1_ajax() {
    global $wpdb;
    
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos');
    }
    
    $agencia_id = intval($_POST['agencia_id']);
    $nuevo_nombre = sanitize_text_field($_POST['nuevo_nombre']);
    $nueva_direccion = sanitize_textarea_field($_POST['nueva_direccion']);
    $nuevo_telefono = sanitize_text_field($_POST['nuevo_telefono']);
    
    $resultado = $wpdb->update(
        $wpdb->prefix . 'agencias_envio_v1',
        array(
            'nombre' => $nuevo_nombre,
            'direccion' => $nueva_direccion,
            'telefono' => $nuevo_telefono
        ),
        array('id' => $agencia_id),
        array('%s', '%s', '%s'),
        array('%d')
    );
    
    if ($resultado !== false) {
        wp_send_json_success('Agencia actualizada correctamente');
    } else {
        wp_send_json_error('Error al actualizar la agencia');
    }
}

add_action('wp_ajax_eliminar_agencia_v1', 'eliminar_agencia_v1_ajax');
function eliminar_agencia_v1_ajax() {
    global $wpdb;
    
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos');
    }
    
    $agresa_id = intval($_POST['agencia_id']);
    
    $resultado = $wpdb->update(
        $wpdb->prefix . 'agencias_envio_v1',
        array('estado' => 0),
        array('id' => $agresa_id),
        array('%d'),
        array('%d')
    );
    
    if ($resultado !== false) {
        wp_send_json_success('Agencia eliminada correctamente');
    } else {
        wp_send_json_error('Error al eliminar la agencia');
    }
}
