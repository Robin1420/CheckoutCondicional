<?php
/**
 * Plugin Name: CheckoutCondicionalV.1
 * Description: Sistema de checkout condicional con gestión de empresas de envío, agencias y fechas de entrega según provincia.
 * Author: Robinzon Sanchez
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

// Verificar que WooCommerce esté activo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// === ACTIVACIÓN DEL PLUGIN ===
register_activation_hook(__FILE__, 'checkout_condicional_v1_activate');
function checkout_condicional_v1_activate() {
    global $wpdb;
    
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
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_empresas);
    dbDelta($sql_agencias);
    
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
add_action('woocommerce_after_checkout_billing_form', 'checkout_extra_fields_v1');
function checkout_extra_fields_v1($checkout) {
    global $wpdb;

    // Obtener empresas activas
    $empresas = $wpdb->get_results("SELECT id, nombre FROM {$wpdb->prefix}empresas_envio_v1 WHERE estado = 1 ORDER BY nombre");

    echo '<div id="extra_fields_checkout" style="margin-top: 20px; padding: 20px; background: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
    echo '<h3 style="margin: 0 0 20px 0; color: #333333; font-size: 18px; border-bottom: 2px solid #cccccc; padding-bottom: 10px;">Información de Envío</h3>';

    // === CAMPOS: EMPRESA Y AGENCIA EN LA MISMA FILA ===
    echo '<div class="empresa-envio-field" style="display:none; margin-bottom: 20px;">';
    echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
    
    // === CAMPO: EMPRESA DE ENVÍO ===
    echo '<div>';
    echo '<label for="billing_empresa_envio" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333333;">Empresa de envío <span class="required" style="color: #e74c3c;">*</span></label>';
    echo '<select name="billing_empresa_envio" id="billing_empresa_envio" class="select" style="width: 100%; padding: 12px; border: 2px solid #d0d0d0; border-radius: 6px; font-size: 14px; background: #ffffff; color: #333333; transition: border-color 0.3s ease;">';
    echo '<option value="" disabled selected>Seleccionar empresa</option>';
    
    foreach ($empresas as $empresa) {
        echo '<option value="' . intval($empresa->id) . '">' . esc_html($empresa->nombre) . '</option>';
    }
    
    echo '</select>';
    echo '</div>';
    
    // === CAMPO: AGENCIA DE ENVÍO ===
    echo '<div>';
    echo '<label for="billing_agencia_envio" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333333;">Agencia de envío <span class="required" style="color: #e74c3c;">*</span></label>';
    echo '<select name="billing_agencia_envio" id="billing_agencia_envio" class="select" style="width: 100%; padding: 12px; border: 2px solid #d0d0d0; border-radius: 6px; font-size: 14px; background: #ffffff; color: #333333; transition: border-color 0.3s ease;">';
    echo '<option value="" disabled selected>Seleccionar agencia</option>';
    echo '</select>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
    
    // Información adicional de la agencia seleccionada
    echo '<div id="agencia_info" class="form-row-wide agencia-envio-field" style="display: none; margin-top: 15px; padding: 15px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    echo '<h4 style="margin: 0 0 15px 0; color: #495057; font-size: 16px; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">Información de la Agencia</h4>';
    echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
    echo '<div><strong style="color: #6c757d;">Empresa:</strong><br><span id="agencia_empresa_text" style="color: #495057;"></span></div>';
    echo '<div><strong style="color: #6c757d;">Agencia:</strong><br><span id="agencia_nombre_text" style="color: #495057;"></span></div>';
    echo '<div><strong style="color: #6c757d;">Dirección:</strong><br><span id="agencia_direccion_text" style="color: #495057;"></span></div>';
    echo '<div><strong style="color: #6c757d;">Teléfono:</strong><br><span id="agencia_telefono_text" style="color: #495057;"></span></div>';
    echo '</div>';
    echo '</div>';

    // === CAMPOS: RANGO DE FECHAS DE ENTREGA (para Lima) ===
    echo '<div class="fecha-entrega-field" style="display:none;">';
    echo '<h4 style="margin: 0 0 15px 0; color: #495057; font-size: 16px; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">Selecciona el Rango de Fechas de Entrega</h4>';
    
    echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
    echo '<div>';
    echo '<label for="billing_fecha_entrega_inicio" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Fecha de inicio <span class="required" style="color: #e74c3c;">*</span></label>';
    echo '<input type="date" name="billing_fecha_entrega_inicio" id="billing_fecha_entrega_inicio" class="input-text" min="' . date('Y-m-d', strtotime('+1 day')) . '" max="' . date('Y-m-d', strtotime('+7 days')) . '" style="width: 100%; padding: 12px; border: 2px solid #d0d0d0; border-radius: 6px; font-size: 14px; background: #ffffff; color: #333333; transition: border-color 0.3s ease;">';
    echo '</div>';
    
    echo '<div>';
    echo '<label for="billing_fecha_entrega_fin" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Fecha de fin <span class="required" style="color: #e74c3c;">*</span></label>';
    echo '<input type="date" name="billing_fecha_entrega_fin" id="billing_fecha_entrega_fin" class="input-text" min="' . date('Y-m-d', strtotime('+1 day')) . '" max="' . date('Y-m-d', strtotime('+7 days')) . '" style="width: 100%; padding: 12px; border: 2px solid #d0d0d0; border-radius: 6px; font-size: 14px; background: #ffffff; color: #333333; transition: border-color 0.3s ease;">';
    echo '</div>';
    echo '</div>';
    
    echo '<p style="margin: 15px 0 0 0; font-size: 13px; color: #6c757d; background: #fff3cd; padding: 10px; border-radius: 4px; border-left: 4px solid #ffc107;">';
    echo '<strong>Nota:</strong> El rango de fechas debe ser máximo de 2 días y no puede ser anterior a mañana.';
    echo '</p>';
    echo '</div>';

    echo '</div>';
}

// === GUARDAR CAMPOS ===
add_action('woocommerce_checkout_update_order_meta', 'bytezon_save_extra_checkout_fields_v1');
function bytezon_save_extra_checkout_fields_v1($order_id) {
    global $wpdb;
    
    // Guardar empresa de envío (nombre)
    if (!empty($_POST['billing_empresa_envio'])) {
        $empresa_id = intval($_POST['billing_empresa_envio']);
        $empresa_nombre = $wpdb->get_var($wpdb->prepare(
            "SELECT nombre FROM {$wpdb->prefix}empresas_envio_v1 WHERE id = %d",
            $empresa_id
        ));
        if ($empresa_nombre) {
            update_post_meta($order_id, '_billing_empresa_envio', $empresa_nombre);
        }
    }
    
    // Guardar agencia de envío (nombre)
    if (!empty($_POST['billing_agencia_envio'])) {
        $agencia_id = intval($_POST['billing_agencia_envio']);
        $agencia_nombre = $wpdb->get_var($wpdb->prepare(
            "SELECT nombre FROM {$wpdb->prefix}agencias_envio_v1 WHERE id = %d",
            $agencia_id
        ));
        if ($agencia_nombre) {
            update_post_meta($order_id, '_billing_agencia_envio', $agencia_nombre);
        }
    }
    
    // Guardar fechas de entrega
    if (!empty($_POST['billing_fecha_entrega_inicio'])) {
        update_post_meta($order_id, '_billing_fecha_entrega_inicio', sanitize_text_field($_POST['billing_fecha_entrega_inicio']));
    }
    
    if (!empty($_POST['billing_fecha_entrega_fin'])) {
        update_post_meta($order_id, '_billing_fecha_entrega_fin', sanitize_text_field($_POST['billing_fecha_entrega_fin']));
    }
}

// === MOSTRAR EN ADMIN ===
add_action('woocommerce_admin_order_data_after_billing_address', 'bytezon_show_extra_checkout_fields_admin_v1', 10, 1);
function bytezon_show_extra_checkout_fields_admin_v1($order) {
    $empresa = get_post_meta($order->get_id(), '_billing_empresa_envio', true);
    $agencia = get_post_meta($order->get_id(), '_billing_agencia_envio', true);
    $fecha_inicio = get_post_meta($order->get_id(), '_billing_fecha_entrega_inicio', true);
    $fecha_fin = get_post_meta($order->get_id(), '_billing_fecha_entrega_fin', true);
    
    if ($empresa) {
        echo '<p><strong>' . __('Empresa de envío') . ':</strong> ' . esc_html($empresa) . '</p>';
    }

    if ($agencia) {
        echo '<p><strong>' . __('Agencia de envío') . ':</strong> ' . esc_html($agencia) . '</p>';
    }
    
    if ($fecha_inicio && $fecha_fin) {
        echo '<p><strong>' . __('Rango de entrega') . ':</strong> ' . esc_html($fecha_inicio) . ' - ' . esc_html($fecha_fin) . '</p>';
    } elseif ($fecha_inicio) {
        echo '<p><strong>' . __('Fecha de entrega') . ':</strong> ' . esc_html($fecha_inicio) . '</p>';
    }
}

// === AJAX PARA OBTENER AGENCIAS ===
add_action('wp_ajax_get_agencias_checkout_v1', 'get_agencias_checkout_v1_ajax');
add_action('wp_ajax_nopriv_get_agencias_checkout_v1', 'get_agencias_checkout_v1_ajax');
function get_agencias_checkout_v1_ajax() {
    global $wpdb;
    
    $empresa_id = intval($_POST['empresa_id']);
    
    if (!$empresa_id) {
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
    
    wp_send_json_success($agencias);
}

// === JAVASCRIPT CONDICIONAL ===
add_action('wp_footer', 'bytezon_conditional_checkout_fields_v1');
function bytezon_conditional_checkout_fields_v1() {
    if (is_checkout()) :
    ?>
    <style>
    /* Estilos adicionales para los comboboxes */
    #extra_fields_checkout select:focus {
        border-color: #007cba !important;
        box-shadow: 0 0 0 1px #007cba !important;
        outline: none !important;
    }
    
    #extra_fields_checkout input[type="date"]:focus {
        border-color: #007cba !important;
        box-shadow: 0 0 0 1px #007cba !important;
        outline: none !important;
    }
    
    /* Estilos para las opciones disabled */
    #extra_fields_checkout select option[disabled] {
        color: #999999;
        font-style: italic;
    }
    
    /* Hover en los comboboxes */
    #extra_fields_checkout select:hover {
        border-color: #b0b0b0 !important;
    }
    
    #extra_fields_checkout input[type="date"]:hover {
        border-color: #b0b0b0 !important;
    }
    
    /* Animación suave para los campos */
    .empresa-envio-field, .agencia-envio-field, .fecha-entrega-field {
        transition: all 0.3s ease;
    }
    
    /* Estilos para el mensaje de información */
    #agencia_info {
        animation: fadeIn 0.5s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Estilos para las opciones del select */
    #extra_fields_checkout select option {
        background: #ffffff;
        color: #333333;
    }
    
    #extra_fields_checkout select option:hover {
        background: #f8f9fa;
    }
    
    /* Estilos adicionales para mejorar la apariencia */
    #extra_fields_checkout {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    
    #extra_fields_checkout h3 {
        color: #2c3e50;
    }
    
    #extra_fields_checkout label {
        color: #34495e;
    }
    </style>
    
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Función para mostrar/ocultar campos según provincia
            function toggleFieldsByProvince() {
                var provincia = $('#billing_provincia').val();
                
                if (provincia && provincia.toLowerCase() === 'lima') {
                    $('.empresa-envio-field, .agencia-envio-field').hide();
                    $('.fecha-entrega-field').show();
                } else {
                    $('.empresa-envio-field').show();
                    $('.fecha-entrega-field').hide();
                    $('.agencia-envio-field').show();
                }
            }
            
            // Función para cargar agencias
            function loadAgencies(empresaId) {
                if (!empresaId || empresaId === '') {
                    return;
                }
                
                $('#billing_agencia_envio').html('<option value="" disabled selected>Cargando agencias...</option>');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'get_agencias_checkout_v1',
                        empresa_id: empresaId
                    },
                    success: function(response) {
                        if (response.success && response.data && response.data.length > 0) {
                            var options = '<option value="" disabled selected>Seleccionar agencia</option>';
                            
                            response.data.forEach(function(agencia) {
                                options += '<option value="' + agencia.id + '" data-direccion="' + (agencia.direccion || '') + '" data-telefono="' + (agencia.telefono || '') + '">' + agencia.nombre + '</option>';
                            });
                            
                            $('#billing_agencia_envio').html(options);
                        } else {
                            $('#billing_agencia_envio').html('<option value="" disabled selected>No hay agencias disponibles</option>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#billing_agencia_envio').html('<option value="" disabled selected>Error al cargar agencias</option>');
                    }
                });
            }
            
            // Función para mostrar información de la agencia
            function showAgencyInfo(agenciaId) {
                if (!agenciaId || agenciaId === '') {
                    $('#agencia_info').hide();
                    return;
                }
                
                var selectedOption = $('#billing_agencia_envio option[value="' + agenciaId + '"]');
                var empresaText = $('#billing_empresa_envio option:selected').text();
                var agenciaNombre = selectedOption.text();
                var direccion = selectedOption.data('direccion');
                var telefono = selectedOption.data('telefono');
                
                if (agenciaNombre && agenciaNombre !== 'Seleccionar agencia' && agenciaNombre !== 'Cargando agencias...' && agenciaNombre !== 'No hay agencias disponibles' && agenciaNombre !== 'Error al cargar agencias') {
                    $('#agencia_empresa_text').text(empresaText);
                    $('#agencia_nombre_text').text(agenciaNombre);
                    $('#agencia_direccion_text').text(direccion || 'No disponible');
                    $('#agencia_telefono_text').text(telefono || 'No disponible');
                    $('#agencia_info').show();
                } else {
                    $('#agencia_info').hide();
                }
            }
            
            // Event Listeners
            $('#billing_provincia').on('change', function() {
                $('#billing_empresa_envio').val('').prop('selectedIndex', 0);
                $('#billing_agencia_envio').val('').prop('selectedIndex', 0);
                $('#billing_fecha_entrega_inicio').val('');
                $('#billing_fecha_entrega_fin').val('');
                $('#agencia_info').hide();
                toggleFieldsByProvince();
            });
            
            $('#billing_empresa_envio').on('change', function() {
                var empresaId = $(this).val();
                var empresaText = $(this).find('option:selected').text();
                
                $('#billing_agencia_envio').val('');
                $('#agencia_info').hide();
                
                if (empresaId && empresaId !== '') {
                    loadAgencies(empresaId);
                } else {
                    $('.agencia-envio-field').hide();
                }
            });
            
            $('#billing_agencia_envio').on('change', function() {
                var agenciaId = $(this).val();
                showAgencyInfo(agenciaId);
            });
            
            // Inicialización
            toggleFieldsByProvince();
        });
    </script>
    <?php
    endif;
}

// === VALIDACIÓN DE CAMPOS ===
add_action('woocommerce_checkout_process', 'validate_extra_checkout_fields_v1');
function validate_extra_checkout_fields_v1() {
    $provincia = $_POST['billing_provincia'] ?? '';
    
    if (strtolower($provincia) === 'lima') {
        if (empty($_POST['billing_fecha_entrega_inicio']) || empty($_POST['billing_fecha_entrega_fin'])) {
            wc_add_notice(__('Por favor selecciona el rango de fechas de entrega para Lima.'), 'error');
        } else {
            $fecha_inicio = strtotime($_POST['billing_fecha_entrega_inicio']);
            $fecha_fin = strtotime($_POST['billing_fecha_entrega_fin']);
            
            if ($fecha_fin < $fecha_inicio) {
                wc_add_notice(__('La fecha de fin debe ser mayor o igual a la fecha de inicio.'), 'error');
            }
            
            $diferencia_dias = ($fecha_fin - $fecha_inicio) / (60 * 60 * 24);
            if ($diferencia_dias > 2) {
                wc_add_notice(__('El rango de fechas no puede ser mayor a 2 días.'), 'error');
            }
        }
    } else {
        if (empty($_POST['billing_empresa_envio'])) {
            wc_add_notice(__('Por favor selecciona una empresa de envío.'), 'error');
        }
        if (empty($_POST['billing_agencia_envio'])) {
            wc_add_notice(__('Por favor selecciona una agencia de envío.'), 'error');
        }
    }
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
