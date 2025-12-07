<?php
/**
 * Plugin Name: Sistema Prenotazione Alba/Tramonto
 * Description: Sistema di prenotazione con calendario gestibile e pannello admin
 * Version: 3.3
 * Author: Monkey Bianki
 */

if (!defined('ABSPATH')) exit;

/* ============================================
   CONFIGURAZIONE PREZZI
============================================ */
define('PREZZO_GRUPPO_ADULTO', 280);
define('PREZZO_GRUPPO_BAMBINO', 280);
define('PREZZO_PRIVATO', 1100);

/* ============================================
   ATTIVAZIONE PLUGIN E DATABASE
============================================ */
register_activation_hook(__FILE__, 'booking_system_install');

function booking_system_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_availability = $wpdb->prefix . 'booking_availability';
    $sql1 = "CREATE TABLE IF NOT EXISTS $table_availability (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        date date NOT NULL,
        alba_available tinyint(1) DEFAULT 1,
        tramonto_available tinyint(1) DEFAULT 1,
        alba_slots int DEFAULT 6,
        tramonto_slots int DEFAULT 6,
        alba_booked int DEFAULT 0,
        tramonto_booked int DEFAULT 0,
        alba_privato tinyint(1) DEFAULT 0,
        tramonto_privato tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY date (date)
    ) $charset_collate;";

    $table_bookings = $wpdb->prefix . 'booking_reservations';
    $sql2 = "CREATE TABLE IF NOT EXISTS $table_bookings (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        booking_date date NOT NULL,
        time_slot varchar(20) NOT NULL,
        tipo_pacchetto varchar(20) NOT NULL DEFAULT 'gruppo',
        nome varchar(100) NOT NULL,
        cognome varchar(100) NOT NULL,
        codice_fiscale varchar(16) NOT NULL,
        email varchar(100) NOT NULL,
        telefono varchar(50) NOT NULL,
        adulti int NOT NULL,
        bambini int NOT NULL,
        totale_biglietti int NOT NULL,
        prezzo_totale decimal(10,2) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    
    // Imposta opzioni default per email
    if (!get_option('booking_email_admin')) {
        update_option('booking_email_admin', get_option('admin_email'));
    }
    if (!get_option('booking_email_enabled')) {
        update_option('booking_email_enabled', '1');
    }
    if (!get_option('booking_email_logo')) {
        update_option('booking_email_logo', 'https://www.dreamballoons.it/wp-content/uploads/2024/12/logo_su_nero.png');
    }
    if (!get_option('booking_email_header_image')) {
        update_option('booking_email_header_image', 'https://www.dreamballoons.it/wp-content/uploads/2025/12/IMG_1437.png');
    }
    if (!get_option('booking_email_primary_color')) {
        update_option('booking_email_primary_color', '#1976D2');
    }
    
    booking_system_update_database();
}

function booking_system_update_database() {
    global $wpdb;
    $table_availability = $wpdb->prefix . 'booking_availability';
    $table_bookings = $wpdb->prefix . 'booking_reservations';
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_availability'") == $table_availability;
    if ($table_exists) {
        $columns = $wpdb->get_col("DESCRIBE $table_availability");
        if (!in_array('alba_booked', $columns)) $wpdb->query("ALTER TABLE $table_availability ADD COLUMN alba_booked int DEFAULT 0");
        if (!in_array('tramonto_booked', $columns)) $wpdb->query("ALTER TABLE $table_availability ADD COLUMN tramonto_booked int DEFAULT 0");
        if (!in_array('alba_privato', $columns)) $wpdb->query("ALTER TABLE $table_availability ADD COLUMN alba_privato tinyint(1) DEFAULT 0");
        if (!in_array('tramonto_privato', $columns)) $wpdb->query("ALTER TABLE $table_availability ADD COLUMN tramonto_privato tinyint(1) DEFAULT 0");
    }
    
    $bookings_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_bookings'") == $table_bookings;
    if ($bookings_exists) {
        $columns = $wpdb->get_col("DESCRIBE $table_bookings");
        if (!in_array('tipo_pacchetto', $columns)) $wpdb->query("ALTER TABLE $table_bookings ADD COLUMN tipo_pacchetto varchar(20) NOT NULL DEFAULT 'gruppo' AFTER time_slot");
        if (!in_array('prezzo_totale', $columns)) $wpdb->query("ALTER TABLE $table_bookings ADD COLUMN prezzo_totale decimal(10,2) DEFAULT 0 AFTER totale_biglietti");
    }
    return true;
}

/* ============================================
   CARICAMENTO CSS E JAVASCRIPT
============================================ */
add_action('wp_enqueue_scripts', 'booking_system_enqueue_assets');

function booking_system_enqueue_assets() {
    wp_enqueue_style('booking-system-style', plugins_url('assets/css/booking-style.css', __FILE__), array(), '3.3.0');
    wp_enqueue_script('booking-system-script', plugins_url('assets/js/booking-script.js', __FILE__), array(), '3.3.0', true);
    wp_localize_script('booking-system-script', 'bookingAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'prezzi' => array('gruppo_adulto' => PREZZO_GRUPPO_ADULTO, 'gruppo_bambino' => PREZZO_GRUPPO_BAMBINO, 'privato' => PREZZO_PRIVATO)
    ));
}

/* ============================================
   MENU AMMINISTRAZIONE
============================================ */
add_action('admin_menu', 'booking_system_admin_menu');

function booking_system_admin_menu() {
    add_menu_page('Gestione Prenotazioni', 'Prenotazioni', 'manage_options', 'booking-system', 'booking_system_admin_page', 'dashicons-calendar-alt', 30);
    add_submenu_page('booking-system', 'Tutte le Prenotazioni', 'Tutte le Prenotazioni', 'manage_options', 'booking-all-reservations', 'booking_all_reservations_page');
    add_submenu_page('booking-system', 'Impostazioni Email', 'Impostazioni Email', 'manage_options', 'booking-email-settings', 'booking_email_settings_page');
}

/* ============================================
   PAGINA: IMPOSTAZIONI EMAIL
============================================ */
function booking_email_settings_page() {
    // Salva impostazioni
    if (isset($_POST['save_email_settings'])) {
        check_admin_referer('booking_email_settings_action', 'booking_email_nonce');
        
        update_option('booking_email_admin', sanitize_email($_POST['email_admin']));
        update_option('booking_email_enabled', isset($_POST['email_enabled']) ? '1' : '0');
        update_option('booking_email_logo', esc_url_raw($_POST['email_logo']));
        update_option('booking_email_header_image', esc_url_raw($_POST['email_header_image']));
        update_option('booking_email_primary_color', sanitize_hex_color($_POST['email_primary_color']));
        
        echo '<div class="notice notice-success"><p>✅ Impostazioni salvate!</p></div>';
    }
    
    // Invia email di test
    if (isset($_POST['send_test_email'])) {
        check_admin_referer('booking_email_settings_action', 'booking_email_nonce');
        
        $test_email = sanitize_email($_POST['test_email_address']);
        if ($test_email) {
            $test_data = array(
                'nome' => 'Mario',
                'cognome' => 'Rossi',
                'email' => $test_email,
                'telefono' => '+39 123 456 7890',
                'codice_fiscale' => 'RSSMRA80A01H501Z',
                'date' => date('Y-m-d', strtotime('+7 days')),
                'time' => 'alba',
                'tipo' => 'gruppo',
                'adulti' => 2,
                'bambini' => 1,
                'totale' => 3,
                'prezzo' => 840
            );
            
            $sent = booking_send_customer_email($test_data);
            
            if ($sent) {
                echo '<div class="notice notice-success"><p>✅ Email di test inviata a ' . esc_html($test_email) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Errore invio email</p></div>';
            }
        }
    }
    
    $email_admin = get_option('booking_email_admin', get_option('admin_email'));
    $email_enabled = get_option('booking_email_enabled', '1');
    $email_logo = get_option('booking_email_logo', '');
    $email_header_image = get_option('booking_email_header_image', '');
    $email_primary_color = get_option('booking_email_primary_color', '#1976D2');
    ?>
    <div class="wrap">
        <h1>📧 Impostazioni Email</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('booking_email_settings_action', 'booking_email_nonce'); ?>
            
            <div style="background:#fff;padding:25px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1);max-width:700px;margin:20px 0;">
                
                <h2 style="margin-top:0;color:#1976D2;">📬 Notifiche Admin</h2>
                
                <table class="form-table">
                    <tr>
                        <th><label for="email_enabled">Ricevi notifiche</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_enabled" id="email_enabled" value="1" <?php checked($email_enabled, '1'); ?>>
                                Invia email quando arriva una nuova prenotazione
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email_admin">Email destinatario</label></th>
                        <td>
                            <input type="email" name="email_admin" id="email_admin" value="<?php echo esc_attr($email_admin); ?>" style="width:300px;padding:8px;">
                            <p class="description">Le notifiche di nuove prenotazioni arriveranno a questo indirizzo</p>
                        </td>
                    </tr>
                </table>
                
                <hr style="margin:30px 0;">
                
                <h2 style="color:#1976D2;">🎨 Personalizza Email Cliente</h2>
                
                <table class="form-table">
                    <tr>
                        <th><label for="email_logo">URL Logo</label></th>
                        <td>
                            <input type="url" name="email_logo" id="email_logo" value="<?php echo esc_attr($email_logo); ?>" style="width:100%;padding:8px;">
                            <p class="description">Logo che appare in alto nell'email (consigliato: sfondo trasparente)</p>
                            <?php if ($email_logo): ?>
                                <div style="margin-top:10px;padding:15px;background:#f5f5f5;border-radius:8px;text-align:center;">
                                    <img src="<?php echo esc_url($email_logo); ?>" style="max-height:60px;">
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email_header_image">Immagine Header</label></th>
                        <td>
                            <input type="url" name="email_header_image" id="email_header_image" value="<?php echo esc_attr($email_header_image); ?>" style="width:100%;padding:8px;">
                            <p class="description">Immagine di sfondo nella parte superiore dell'email</p>
                            <?php if ($email_header_image): ?>
                                <div style="margin-top:10px;padding:10px;background:#f5f5f5;border-radius:8px;">
                                    <img src="<?php echo esc_url($email_header_image); ?>" style="max-width:100%;max-height:150px;border-radius:8px;">
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email_primary_color">Colore principale</label></th>
                        <td>
                            <input type="color" name="email_primary_color" id="email_primary_color" value="<?php echo esc_attr($email_primary_color); ?>" style="width:60px;height:40px;padding:0;border:none;cursor:pointer;">
                            <span style="margin-left:10px;color:#666;"><?php echo esc_html($email_primary_color); ?></span>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_email_settings" class="button button-primary button-large" value="💾 Salva Impostazioni">
                </p>
            </div>
            
            <!-- Test Email -->
            <div style="background:#fff;padding:25px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1);max-width:700px;margin:20px 0;">
                <h2 style="margin-top:0;color:#1976D2;">🧪 Invia Email di Test</h2>
                <p style="color:#666;">Invia un'email di prova per vedere come apparirà ai clienti.</p>
                
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="email" name="test_email_address" placeholder="tua@email.com" style="flex:1;padding:10px;border:1px solid #ddd;border-radius:6px;">
                    <input type="submit" name="send_test_email" class="button button-secondary" value="📤 Invia Test">
                </div>
            </div>
        </form>
    </div>
    <?php
}

/* ============================================
   PAGINA: TUTTE LE PRENOTAZIONI
============================================ */
function booking_all_reservations_page() {
    global $wpdb;
    $table_bookings = $wpdb->prefix . 'booking_reservations';
    $table_avail = $wpdb->prefix . 'booking_availability';
    
    if (isset($_GET['delete_booking']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_booking_' . $_GET['delete_booking'])) {
            $booking_id = intval($_GET['delete_booking']);
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", $booking_id));
            
            if ($booking) {
                $slot_field = ($booking->time_slot == 'alba') ? 'alba_booked' : 'tramonto_booked';
                $privato_field = ($booking->time_slot == 'alba') ? 'alba_privato' : 'tramonto_privato';
                $tipo = isset($booking->tipo_pacchetto) ? $booking->tipo_pacchetto : 'gruppo';
                
                if ($tipo == 'privato') {
                    $wpdb->query($wpdb->prepare("UPDATE $table_avail SET $privato_field = 0 WHERE date = %s", $booking->booking_date));
                } else {
                    $wpdb->query($wpdb->prepare("UPDATE $table_avail SET $slot_field = $slot_field - %d WHERE date = %s", $booking->totale_biglietti, $booking->booking_date));
                }
                
                $wpdb->delete($table_bookings, array('id' => $booking_id), array('%d'));
                echo '<div class="notice notice-success is-dismissible"><p>✅ Prenotazione eliminata!</p></div>';
            }
        }
    }
    
    $bookings = $wpdb->get_results("SELECT * FROM $table_bookings ORDER BY booking_date DESC, created_at DESC");
    ?>
    <div class="wrap">
        <h1>📋 Tutte le Prenotazioni</h1>
        
        <?php if (empty($bookings)): ?>
            <p style="color:#999;">Nessuna prenotazione.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px;">ID</th>
                        <th>Pacchetto</th>
                        <th>Data</th>
                        <th>Orario</th>
                        <th>Cliente</th>
                        <th>Contatti</th>
                        <th>Persone</th>
                        <th>Prezzo</th>
                        <th style="width:80px;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): 
                        $date_obj = new DateTime($booking->booking_date);
                        $is_past = $date_obj < new DateTime('today');
                        $tipo = isset($booking->tipo_pacchetto) ? $booking->tipo_pacchetto : 'gruppo';
                        $prezzo = isset($booking->prezzo_totale) ? $booking->prezzo_totale : 0;
                    ?>
                        <tr <?php echo $is_past ? 'style="opacity:0.5;"' : ''; ?>>
                            <td><strong>#<?php echo $booking->id; ?></strong></td>
                            <td>
                                <?php if ($tipo == 'privato'): ?>
                                    <span style="background:#FFF3E0;color:#E65100;padding:3px 8px;border-radius:4px;font-size:11px;">🎈 Privato</span>
                                <?php else: ?>
                                    <span style="background:#E3F2FD;color:#1565C0;padding:3px 8px;border-radius:4px;font-size:11px;">👥 Gruppo</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo $date_obj->format('d/m/Y'); ?></strong></td>
                            <td><?php echo $booking->time_slot == 'alba' ? '🌅 Alba' : '🌇 Tramonto'; ?></td>
                            <td><?php echo esc_html($booking->nome . ' ' . $booking->cognome); ?></td>
                            <td><?php echo esc_html($booking->email); ?><br><small><?php echo esc_html($booking->telefono); ?></small></td>
                            <td><strong><?php echo $booking->totale_biglietti; ?></strong></td>
                            <td><strong style="color:#2E7D32;">€<?php echo number_format($prezzo, 0); ?></strong></td>
                            <td>
                                <a href="?page=booking-all-reservations&delete_booking=<?php echo $booking->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_booking_' . $booking->id); ?>" 
                                   class="button button-small" style="color:#dc3545;"
                                   onclick="return confirm('Eliminare questa prenotazione?');">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top:20px;padding:15px;background:#f0f0f1;border-radius:5px;">
                <strong>📊</strong> Prenotazioni: <strong><?php echo count($bookings); ?></strong> | 
                Incasso: <strong style="color:#2E7D32;">€<?php echo number_format(array_sum(array_column($bookings, 'prezzo_totale')), 0); ?></strong>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/* ============================================
   PAGINA: GESTIONE DISPONIBILITÀ
============================================ */
function booking_system_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'booking_availability';
    
    booking_system_update_database();

    if (isset($_POST['save_availability'])) {
        check_admin_referer('booking_save_action', 'booking_nonce');
        
        $date_start = sanitize_text_field($_POST['booking_date_start']);
        $date_end = sanitize_text_field($_POST['booking_date_end']);
        $alba = isset($_POST['alba_available']) ? 1 : 0;
        $tramonto = isset($_POST['tramonto_available']) ? 1 : 0;
        $alba_slots = intval($_POST['alba_slots']);
        $tramonto_slots = intval($_POST['tramonto_slots']);

        $start = new DateTime($date_start);
        $end = new DateTime($date_end);
        $end->modify('+1 day');
        $daterange = new DatePeriod($start, new DateInterval('P1D'), $end);
        
        $count = 0;
        foreach($daterange as $date) {
            $date_str = $date->format('Y-m-d');
            $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_name WHERE date = %s", $date_str));

            if ($existing) {
                $wpdb->update($table_name, array('alba_available' => $alba, 'tramonto_available' => $tramonto, 'alba_slots' => $alba_slots, 'tramonto_slots' => $tramonto_slots), array('date' => $date_str));
            } else {
                $wpdb->insert($table_name, array('date' => $date_str, 'alba_available' => $alba, 'tramonto_available' => $tramonto, 'alba_slots' => $alba_slots, 'tramonto_slots' => $tramonto_slots, 'alba_booked' => 0, 'tramonto_booked' => 0, 'alba_privato' => 0, 'tramonto_privato' => 0));
            }
            $count++;
        }
        echo '<div class="notice notice-success"><p>✅ Salvate ' . $count . ' date!</p></div>';
    }

    if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_availability_' . $_GET['delete'])) {
            $wpdb->delete($table_name, array('id' => intval($_GET['delete'])));
            echo '<div class="notice notice-success"><p>✅ Data eliminata!</p></div>';
        }
    }

    $availabilities = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date ASC");
    ?>
    <div class="wrap">
        <h1>🗓️ Gestione Disponibilità</h1>
        
        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:12px 15px;margin:15px 0;">
            <strong>💰 Prezzi:</strong> Gruppo: <strong>€<?php echo PREZZO_GRUPPO_ADULTO; ?></strong>/persona | Privato: <strong>€<?php echo PREZZO_PRIVATO; ?></strong> (coppia)
        </div>
        
        <div style="background:white;padding:20px;margin:20px 0;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
            <h2>➕ Aggiungi Date</h2>
            <form method="post">
                <?php wp_nonce_field('booking_save_action', 'booking_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>Data Inizio</th>
                        <td><input type="date" name="booking_date_start" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>"></td>
                    </tr>
                    <tr>
                        <th>Data Fine</th>
                        <td><input type="date" name="booking_date_end" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>"></td>
                    </tr>
                    <tr>
                        <th>Disponibilità</th>
                        <td>
                            <label><input type="checkbox" name="alba_available" checked> 🌅 Alba</label>
                            <input type="number" name="alba_slots" value="6" min="1" max="20" style="width:60px;margin-left:10px;"> posti<br><br>
                            <label><input type="checkbox" name="tramonto_available" checked> 🌇 Tramonto</label>
                            <input type="number" name="tramonto_slots" value="6" min="1" max="20" style="width:60px;margin-left:10px;"> posti
                        </td>
                    </tr>
                </table>
                <p><input type="submit" name="save_availability" class="button button-primary" value="💾 Salva"></p>
            </form>
        </div>

        <div style="background:white;padding:20px;margin:20px 0;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
            <h2>📋 Date (<?php echo count($availabilities); ?>)</h2>
            <?php if (empty($availabilities)): ?>
                <p style="color:#999;">Nessuna data.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Data</th><th>Alba</th><th>Tramonto</th><th>Azioni</th></tr></thead>
                    <tbody>
                        <?php foreach ($availabilities as $avail): 
                            $alba_priv = isset($avail->alba_privato) && $avail->alba_privato;
                            $tram_priv = isset($avail->tramonto_privato) && $avail->tramonto_privato;
                        ?>
                            <tr>
                                <td><strong><?php echo date('d/m/Y', strtotime($avail->date)); ?></strong></td>
                                <td>
                                    <?php if ($alba_priv): ?>
                                        <span style="background:#FFF3E0;color:#E65100;padding:2px 6px;border-radius:4px;font-size:11px;">🎈 PRIVATO</span>
                                    <?php elseif ($avail->alba_available): ?>
                                        ✅ <?php echo ($avail->alba_slots - $avail->alba_booked); ?>/<?php echo $avail->alba_slots; ?>
                                    <?php else: ?>
                                        ❌
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tram_priv): ?>
                                        <span style="background:#FFF3E0;color:#E65100;padding:2px 6px;border-radius:4px;font-size:11px;">🎈 PRIVATO</span>
                                    <?php elseif ($avail->tramonto_available): ?>
                                        ✅ <?php echo ($avail->tramonto_slots - $avail->tramonto_booked); ?>/<?php echo $avail->tramonto_slots; ?>
                                    <?php else: ?>
                                        ❌
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=booking-system&delete=<?php echo $avail->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_availability_' . $avail->id); ?>" 
                                       class="button button-small" onclick="return confirm('Eliminare?')">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/* ============================================
   SHORTCODE FRONTEND
============================================ */
add_shortcode('booking_system', 'booking_system_shortcode');

function booking_system_shortcode($atts) {
    $atts = shortcode_atts(array('tipo' => 'gruppo'), $atts);
    $tipo = sanitize_text_field($atts['tipo']);
    
    ob_start();
    $template_path = plugin_dir_path(__FILE__) . 'templates/booking-form.php';
    
    if (file_exists($template_path)) {
        include($template_path);
    } else {
        echo '<div style="color:red;">Errore: Template non trovato!</div>';
    }
    
    return ob_get_clean();
}

/* ============================================
   AJAX: GET AVAILABILITY
============================================ */
add_action('wp_ajax_get_booking_availability', 'get_booking_availability');
add_action('wp_ajax_nopriv_get_booking_availability', 'get_booking_availability');

function get_booking_availability() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'booking_availability';
    $tipo = isset($_POST['tipo']) ? sanitize_text_field($_POST['tipo']) : 'gruppo';
    $results = $wpdb->get_results("SELECT * FROM $table_name WHERE date >= CURDATE() ORDER BY date ASC");
    wp_send_json_success(array('dates' => $results, 'tipo' => $tipo));
}

/* ============================================
   FUNZIONE: INVIA EMAIL CLIENTE
============================================ */
function booking_send_customer_email($data) {
    $email_header_image = get_option('booking_email_header_image', 'https://www.dreamballoons.it/wp-content/uploads/2025/12/IMG_1437.png');
    $primary_color = get_option('booking_email_primary_color', '#1976D2');
    
    $date_obj = new DateTime($data['date']);
    $day_names = array('Monday'=>'Lunedì','Tuesday'=>'Martedì','Wednesday'=>'Mercoledì','Thursday'=>'Giovedì','Friday'=>'Venerdì','Saturday'=>'Sabato','Sunday'=>'Domenica');
    $day = $day_names[$date_obj->format('l')];
    $date_formatted = $date_obj->format('d/m/Y');
    $orario_label = ($data['time'] == 'alba') ? 'Alba' : 'Tramonto';
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin:0;padding:20px;background-color:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
        <div style="max-width:500px;margin:0 auto;background-color:#ffffff;border-radius:20px;overflow:hidden;">
            
            <!-- Header con immagine -->
            <div style="height:180px;overflow:hidden;">
                <img src="' . $email_header_image . '" alt="" style="width:100%;height:180px;object-fit:cover;display:block;">
            </div>
            
            <!-- Logo centrato -->
            <div style="text-align:center;margin-top:-45px;margin-bottom:10px;position:relative;z-index:10;">
                <img src="https://www.dreamballoons.it/wp-content/uploads/2024/12/logo_su_nero.png" alt="Dream Balloons" style="height:50px;">
            </div>
            
            <!-- Contenuto -->
            <div style="padding:25px 30px 30px;">
                
                <h1 style="margin:15px 0 8px;font-size:24px;font-weight:600;color:#333;text-align:center;">Prenotazione Confermata</h1>
                <p style="margin:0 0 25px;font-size:15px;color:#888;text-align:center;">
                    Ciao <strong style="color:#333;">' . $data['nome'] . '</strong>, grazie per aver prenotato!
                </p>
                
                <!-- Dettagli -->
                <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid #f0f0f0;color:#999;font-size:14px;">Data</td>
                        <td style="padding:14px 0;border-bottom:1px solid #f0f0f0;color:#333;font-size:14px;font-weight:600;text-align:right;">' . $day . ', ' . $date_formatted . '</td>
                    </tr>
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid #f0f0f0;color:#999;font-size:14px;">Orario</td>
                        <td style="padding:14px 0;border-bottom:1px solid #f0f0f0;color:#333;font-size:14px;font-weight:600;text-align:right;">' . $orario_label . '</td>
                    </tr>
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid #f0f0f0;color:#999;font-size:14px;">Adulti</td>
                        <td style="padding:14px 0;border-bottom:1px solid #f0f0f0;color:#333;font-size:14px;font-weight:600;text-align:right;">' . $data['adulti'] . '</td>
                    </tr>
                    <tr>
                        <td style="padding:14px 0;color:#999;font-size:14px;">Bambini</td>
                        <td style="padding:14px 0;color:#333;font-size:14px;font-weight:600;text-align:right;">' . $data['bambini'] . '</td>
                    </tr>
                </table>
                
                <!-- Box Totali affiancati -->
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="width:48%;padding-right:4%;">
                            <div style="background:#F07B7B;border-radius:12px;padding:15px;text-align:center;">
                                <div style="color:rgba(255,255,255,0.8);font-size:10px;text-transform:uppercase;letter-spacing:1px;">Biglietti</div>
                                <div style="color:#fff;font-size:28px;font-weight:700;margin-top:4px;">' . $data['totale'] . '</div>
                            </div>
                        </td>
                        <td style="width:48%;">
                            <div style="background:#222;border-radius:12px;padding:15px;text-align:center;">
                                <div style="color:rgba(255,255,255,0.6);font-size:10px;text-transform:uppercase;letter-spacing:1px;">Totale</div>
                                <div style="color:#fff;font-size:28px;font-weight:700;margin-top:4px;">€' . number_format($data['prezzo'], 0, ',', '.') . '</div>
                            </div>
                        </td>
                    </tr>
                </table>
                
            </div>
            
            <!-- Footer -->
            <div style="background:#fafafa;padding:18px;text-align:center;border-top:1px solid #f0f0f0;">
                <p style="margin:0;color:#aaa;font-size:12px;">
                    <a href="mailto:info@dreamballoons.it" style="color:#888;text-decoration:none;">info@dreamballoons.it</a> &nbsp;•&nbsp; 
                    <a href="https://dreamballoons.it" style="color:#888;text-decoration:none;">dreamballoons.it</a>
                </p>
            </div>
            
        </div>
    </body>
    </html>';

    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: Dream Balloons <noreply@dreamballoons.it>');
    
    return wp_mail($data['email'], 'Prenotazione Confermata - Dream Balloons', $message, $headers);
}

/* ============================================
   FUNZIONE: INVIA EMAIL ADMIN
============================================ */
function booking_send_admin_email($data) {
    $email_enabled = get_option('booking_email_enabled', '1');
    $email_admin = get_option('booking_email_admin', get_option('admin_email'));
    
    if ($email_enabled != '1' || empty($email_admin)) return false;
    
    $date_obj = new DateTime($data['date']);
    $day_names = array('Monday'=>'Lunedì','Tuesday'=>'Martedì','Wednesday'=>'Mercoledì','Thursday'=>'Giovedì','Friday'=>'Venerdì','Saturday'=>'Sabato','Sunday'=>'Domenica');
    $day = $day_names[$date_obj->format('l')];
    $date_formatted = $date_obj->format('d/m/Y');
    $nome_pacchetto = ($data['tipo'] == 'privato') ? 'Volo Privato' : 'Volo di Gruppo';
    $badge_color = ($data['tipo'] == 'privato') ? '#E65100' : '#1976D2';
    
    $message = '
    <!DOCTYPE html>
    <html>
    <body style="margin:0;padding:20px;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
        <div style="max-width:450px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            
            <!-- Header -->
            <div style="background:#fff;padding:25px 25px 20px;text-align:center;border-bottom:1px solid #f0f0f0;">
                <div style="font-size:32px;margin-bottom:10px;">🎈</div>
                <h1 style="margin:0;font-size:20px;font-weight:600;color:#333;">Nuova Prenotazione</h1>
                <span style="display:inline-block;margin-top:10px;background:' . $badge_color . ';color:#fff;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:500;">' . $nome_pacchetto . '</span>
            </div>
            
            <!-- Contenuto -->
            <div style="padding:20px 25px;">
                
                <!-- Info Cliente -->
                <div style="background:#f8f9fa;border-radius:12px;padding:18px;margin-bottom:15px;">
                    <div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">👤 Cliente</div>
                    <div style="font-size:17px;font-weight:600;color:#333;margin-bottom:6px;">' . $data['nome'] . ' ' . $data['cognome'] . '</div>
                    <div style="font-size:13px;color:#666;margin-bottom:4px;">📧 ' . $data['email'] . '</div>
                    <div style="font-size:13px;color:#666;">📱 ' . $data['telefono'] . '</div>
                </div>
                
                <!-- Dettagli Prenotazione -->
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tr>
                        <td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#999;">Data</td>
                        <td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#333;font-weight:600;text-align:right;">' . $day . ', ' . $date_formatted . '</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#999;">Orario</td>
                        <td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#333;font-weight:600;text-align:right;">' . ($data['time'] == 'alba' ? '🌅 Alba' : '🌇 Tramonto') . '</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 0;color:#999;">Persone</td>
                        <td style="padding:12px 0;color:#333;font-weight:600;text-align:right;">' . $data['totale'] . ' (A:' . $data['adulti'] . ' B:' . $data['bambini'] . ')</td>
                    </tr>
                </table>
                
            </div>
            
            <!-- Footer con Totale -->
            <div style="background:#222;padding:20px 25px;text-align:center;">
                <div style="color:rgba(255,255,255,0.6);font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Totale</div>
                <div style="color:#fff;font-size:32px;font-weight:700;">€' . number_format($data['prezzo'], 0, ',', '.') . '</div>
            </div>
            
        </div>
    </body>
    </html>';

    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: Dream Balloons <noreply@dreamballoons.it>');
    
    return wp_mail($email_admin, 'Nuova Prenotazione ' . $nome_pacchetto . ' - ' . $data['nome'] . ' ' . $data['cognome'], $message, $headers);
}

/* ============================================
   AJAX: PROCESS BOOKING
============================================ */
add_action('wp_ajax_process_booking', 'handle_booking_submission');
add_action('wp_ajax_nopriv_process_booking', 'handle_booking_submission');

function handle_booking_submission() {
    global $wpdb;
    
    $date = sanitize_text_field($_POST['date']);
    $time = sanitize_text_field($_POST['time']);
    $tipo = sanitize_text_field($_POST['tipo']);
    $adulti = intval($_POST['adulti']);
    $bambini = intval($_POST['bambini']);
    $totale = $adulti + $bambini;
    $nome = sanitize_text_field($_POST['nome']);
    $cognome = sanitize_text_field($_POST['cognome']);
    $codice_fiscale = strtoupper(sanitize_text_field($_POST['codice_fiscale']));
    $email = sanitize_email($_POST['email']);
    $telefono = sanitize_text_field($_POST['telefono']);

    if ($tipo == 'privato') {
        $prezzo = PREZZO_PRIVATO;
        $totale = 2;
        $adulti = 2;
        $bambini = 0;
    } else {
        $prezzo = ($adulti * PREZZO_GRUPPO_ADULTO) + ($bambini * PREZZO_GRUPPO_BAMBINO);
    }

    $table_avail = $wpdb->prefix . 'booking_availability';
    $availability = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_avail WHERE date = %s", $date));
    
    if (!$availability) {
        wp_send_json_error(array('message' => 'Data non disponibile'));
        return;
    }
    
    $privato_field = ($time == 'alba') ? 'alba_privato' : 'tramonto_privato';
    $booked_field = ($time == 'alba') ? 'alba_booked' : 'tramonto_booked';
    
    $is_privato = ($time == 'alba') ? (isset($availability->alba_privato) ? $availability->alba_privato : 0) : (isset($availability->tramonto_privato) ? $availability->tramonto_privato : 0);
    $current_booked = ($time == 'alba') ? $availability->alba_booked : $availability->tramonto_booked;
    $max_slots = ($time == 'alba') ? $availability->alba_slots : $availability->tramonto_slots;
    $remaining = $max_slots - $current_booked;
    
    if ($tipo == 'privato') {
        if ($is_privato || $current_booked > 0) {
            wp_send_json_error(array('message' => 'Orario non disponibile per volo privato'));
            return;
        }
    } else {
        if ($is_privato) {
            wp_send_json_error(array('message' => 'Orario riservato per volo privato'));
            return;
        }
        if ($remaining < $totale) {
            wp_send_json_error(array('message' => 'Posti insufficienti. Rimasti: ' . $remaining));
            return;
        }
    }

    $table_bookings = $wpdb->prefix . 'booking_reservations';
    $inserted = $wpdb->insert($table_bookings, array(
        'booking_date' => $date, 'time_slot' => $time, 'tipo_pacchetto' => $tipo,
        'nome' => $nome, 'cognome' => $cognome, 'codice_fiscale' => $codice_fiscale,
        'email' => $email, 'telefono' => $telefono,
        'adulti' => $adulti, 'bambini' => $bambini, 'totale_biglietti' => $totale, 'prezzo_totale' => $prezzo
    ));
    
    if (!$inserted) {
        wp_send_json_error(array('message' => 'Errore salvataggio'));
        return;
    }

    if ($tipo == 'privato') {
        $wpdb->query($wpdb->prepare("UPDATE $table_avail SET $privato_field = 1 WHERE date = %s", $date));
    } else {
        $wpdb->query($wpdb->prepare("UPDATE $table_avail SET $booked_field = $booked_field + %d WHERE date = %s", $totale, $date));
    }

    // Prepara dati per email
    $email_data = array(
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'telefono' => $telefono,
        'codice_fiscale' => $codice_fiscale,
        'date' => $date,
        'time' => $time,
        'tipo' => $tipo,
        'adulti' => $adulti,
        'bambini' => $bambini,
        'totale' => $totale,
        'prezzo' => $prezzo
    );
    
    // Invia email
    booking_send_customer_email($email_data);
    booking_send_admin_email($email_data);

    wp_send_json_success(array('message' => 'Prenotazione confermata', 'prezzo' => $prezzo));
}
?>
