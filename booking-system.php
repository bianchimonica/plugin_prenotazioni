<?php
/**
 * Plugin Name: Sistema Prenotazione Alba/Tramonto
 * Description: Sistema di prenotazione con calendario gestibile e pannello admin
 * Version: 3.0
 * Author: Monkey Bianki
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================
   CONFIGURAZIONE PREZZI
   Modifica questi valori per aggiornare i prezzi
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

    // Tabella disponibilità
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
        PRIMARY KEY  (id),
        UNIQUE KEY date (date)
    ) $charset_collate;";

    // Tabella prenotazioni
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
        PRIMARY KEY  (id),
        KEY booking_date (booking_date),
        KEY time_slot (time_slot),
        KEY tipo_pacchetto (tipo_pacchetto)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    
    booking_system_update_database();
}

function booking_system_update_database() {
    global $wpdb;
    $table_availability = $wpdb->prefix . 'booking_availability';
    $table_bookings = $wpdb->prefix . 'booking_reservations';
    
    // Aggiorna tabella availability
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_availability'") == $table_availability;
    
    if ($table_exists) {
        $columns = $wpdb->get_col("DESCRIBE $table_availability");
        
        if (!in_array('alba_booked', $columns)) {
            $wpdb->query("ALTER TABLE $table_availability ADD COLUMN alba_booked int DEFAULT 0");
        }
        if (!in_array('tramonto_booked', $columns)) {
            $wpdb->query("ALTER TABLE $table_availability ADD COLUMN tramonto_booked int DEFAULT 0");
        }
        if (!in_array('alba_privato', $columns)) {
            $wpdb->query("ALTER TABLE $table_availability ADD COLUMN alba_privato tinyint(1) DEFAULT 0");
        }
        if (!in_array('tramonto_privato', $columns)) {
            $wpdb->query("ALTER TABLE $table_availability ADD COLUMN tramonto_privato tinyint(1) DEFAULT 0");
        }
    }
    
    // Aggiorna tabella bookings
    $bookings_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_bookings'") == $table_bookings;
    
    if ($bookings_exists) {
        $columns = $wpdb->get_col("DESCRIBE $table_bookings");
        
        if (!in_array('tipo_pacchetto', $columns)) {
            $wpdb->query("ALTER TABLE $table_bookings ADD COLUMN tipo_pacchetto varchar(20) NOT NULL DEFAULT 'gruppo' AFTER time_slot");
        }
        if (!in_array('prezzo_totale', $columns)) {
            $wpdb->query("ALTER TABLE $table_bookings ADD COLUMN prezzo_totale decimal(10,2) DEFAULT 0 AFTER totale_biglietti");
        }
    }
    
    return true;
}

/* ============================================
   CARICAMENTO CSS E JAVASCRIPT
============================================ */
add_action('wp_enqueue_scripts', 'booking_system_enqueue_assets');

function booking_system_enqueue_assets() {
    wp_enqueue_style(
        'booking-system-style',
        plugins_url('assets/css/booking-style.css', __FILE__),
        array(),
        '3.0.0'
    );
    
    wp_enqueue_script(
        'booking-system-script',
        plugins_url('assets/js/booking-script.js', __FILE__),
        array(),
        '3.0.0',
        true
    );
    
    wp_localize_script('booking-system-script', 'bookingAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'prezzi' => array(
            'gruppo_adulto' => PREZZO_GRUPPO_ADULTO,
            'gruppo_bambino' => PREZZO_GRUPPO_BAMBINO,
            'privato' => PREZZO_PRIVATO
        )
    ));
}

/* ============================================
   MENU AMMINISTRAZIONE
============================================ */
add_action('admin_menu', 'booking_system_admin_menu');

function booking_system_admin_menu() {
    add_menu_page(
        'Gestione Prenotazioni',
        'Prenotazioni',
        'manage_options',
        'booking-system',
        'booking_system_admin_page',
        'dashicons-calendar-alt',
        30
    );
    
    add_submenu_page(
        'booking-system',
        'Tutte le Prenotazioni',
        'Tutte le Prenotazioni',
        'manage_options',
        'booking-all-reservations',
        'booking_all_reservations_page'
    );
}

/* ============================================
   PAGINA: TUTTE LE PRENOTAZIONI
============================================ */
function booking_all_reservations_page() {
    global $wpdb;
    $table_bookings = $wpdb->prefix . 'booking_reservations';
    $table_avail = $wpdb->prefix . 'booking_availability';
    
    // Gestione eliminazione
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
    
    // LISTA PRENOTAZIONI
    $bookings = $wpdb->get_results("SELECT * FROM $table_bookings ORDER BY booking_date DESC, created_at DESC");
    ?>
    <div class="wrap">
        <h1>📋 Tutte le Prenotazioni</h1>
        
        <?php if (empty($bookings)): ?>
            <p style="color: #999;">Nessuna prenotazione.</p>
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
                        <th style="width:100px;">Azioni</th>
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
                                   onclick="return confirm('Eliminare questa prenotazione?');">
                                    🗑️
                                </a>
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

    // Salva disponibilità
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
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($start, $interval, $end);
        
        $count = 0;
        foreach($daterange as $date) {
            $date_str = $date->format('Y-m-d');
            $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_name WHERE date = %s", $date_str));

            if ($existing) {
                $wpdb->update($table_name,
                    array('alba_available' => $alba, 'tramonto_available' => $tramonto, 'alba_slots' => $alba_slots, 'tramonto_slots' => $tramonto_slots),
                    array('date' => $date_str)
                );
            } else {
                $wpdb->insert($table_name,
                    array('date' => $date_str, 'alba_available' => $alba, 'tramonto_available' => $tramonto, 'alba_slots' => $alba_slots, 'tramonto_slots' => $tramonto_slots, 'alba_booked' => 0, 'tramonto_booked' => 0, 'alba_privato' => 0, 'tramonto_privato' => 0)
                );
            }
            $count++;
        }
        echo '<div class="notice notice-success"><p>✅ Salvate ' . $count . ' date!</p></div>';
    }

    // Elimina
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
        
        <!-- Form -->
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

        <!-- Lista -->
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

    // Calcola prezzo
    if ($tipo == 'privato') {
        $prezzo = PREZZO_PRIVATO;
        $totale = 2;
        $adulti = 2;
        $bambini = 0;
    } else {
        $prezzo = ($adulti * PREZZO_GRUPPO_ADULTO) + ($bambini * PREZZO_GRUPPO_BAMBINO);
    }

    // Verifica disponibilità
    $table_avail = $wpdb->prefix . 'booking_availability';
    $availability = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_avail WHERE date = %s", $date));
    
    if (!$availability) {
        wp_send_json_error(array('message' => 'Data non disponibile'));
        return;
    }
    
    $privato_field = ($time == 'alba') ? 'alba_privato' : 'tramonto_privato';
    $booked_field = ($time == 'alba') ? 'alba_booked' : 'tramonto_booked';
    $slots_field = ($time == 'alba') ? 'alba_slots' : 'tramonto_slots';
    
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

    // Salva prenotazione
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

    // Aggiorna disponibilità
    if ($tipo == 'privato') {
        $wpdb->query($wpdb->prepare("UPDATE $table_avail SET $privato_field = 1 WHERE date = %s", $date));
    } else {
        $wpdb->query($wpdb->prepare("UPDATE $table_avail SET $booked_field = $booked_field + %d WHERE date = %s", $totale, $date));
    }

    // Email
    $date_obj = new DateTime($date);
    $day_names = array('Monday'=>'Lunedì','Tuesday'=>'Martedì','Wednesday'=>'Mercoledì','Thursday'=>'Giovedì','Friday'=>'Venerdì','Saturday'=>'Sabato','Sunday'=>'Domenica');
    $day = $day_names[$date_obj->format('l')];
    $date_formatted = $date_obj->format('d/m/Y');
    $nome_pacchetto = ($tipo == 'privato') ? 'Volo Privato per Due' : 'Volo di Gruppo';
    $orario_label = ($time == 'alba') ? 'Alba' : 'Tramonto';

    // EMAIL CLIENTE - Stile elegante con immagine
    $message_customer = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin:0;padding:0;background-color:#f5f5f5;font-family:Georgia,Times,serif;">
        <div style="max-width:600px;margin:0 auto;background-color:#ffffff;">
            
            <!-- Header con immagine mongolfiera -->
            <div style="position:relative;height:260px;overflow:hidden;">
                <!-- Logo PNG -->
                <img src="https://www.dreamballoons.it/wp-content/uploads/2024/12/logo_su_nero.png" alt="Dream Balloons" style="position:absolute;top:20px;left:20px;height:50px;z-index:10;">
                <!-- Immagine mongolfiera come sfondo -->
                <img src="https://www.dreamballoons.it/wp-content/uploads/2025/12/IMG_1437.png" alt="" style="width:100%;height:260px;object-fit:cover;">
            </div>
            
            <!-- Onda bianca SVG -->
            <div style="margin-top:-80px;position:relative;z-index:5;">
                <svg viewBox="0 0 600 100" style="display:block;width:100%;" preserveAspectRatio="none">
                    <path d="M0,100 L0,60 C100,90 200,30 300,50 C400,70 500,20 600,40 L600,100 Z" fill="#ffffff"/>
                </svg>
            </div>
            
            <!-- Contenuto principale -->
            <div style="padding:0 35px 35px 35px;background:#ffffff;margin-top:-5px;">
                
                <!-- Titolo -->
                <h1 style="margin:0 0 5px 0;font-size:32px;font-weight:bold;color:#1a1a1a;text-align:center;font-family:Georgia,Times,serif;">Prenotazione</h1>
                <h1 style="margin:0 0 20px 0;font-size:32px;font-weight:bold;color:#1a1a1a;text-align:center;font-family:Georgia,Times,serif;">Confermata</h1>
                
                <!-- Messaggio personalizzato -->
                <p style="font-size:16px;color:#555;text-align:center;margin:0 0 28px 0;line-height:1.5;">
                    Ciao <strong style="color:#1a1a1a;">' . $nome . '</strong>, grazie per aver prenotato<br>la tua esperienza in mongolfiera!
                </p>
                
                <!-- Tabella dettagli -->
                <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid #eee;color:#888;font-size:15px;">Data</td>
                        <td style="padding:14px 0;border-bottom:1px solid #eee;color:#1a1a1a;font-size:15px;font-weight:bold;text-align:right;">' . $day . ', ' . $date_formatted . '</td>
                    </tr>
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid #eee;color:#888;font-size:15px;">Orario</td>
                        <td style="padding:14px 0;border-bottom:1px solid #eee;color:#1a1a1a;font-size:15px;font-weight:bold;text-align:right;">' . $orario_label . '</td>
                    </tr>
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid #eee;color:#888;font-size:15px;">Adulti</td>
                        <td style="padding:14px 0;border-bottom:1px solid #eee;color:#1a1a1a;font-size:15px;font-weight:bold;text-align:right;">' . $adulti . '</td>
                    </tr>
                    <tr>
                        <td style="padding:14px 0;color:#888;font-size:15px;">Bambini</td>
                        <td style="padding:14px 0;color:#1a1a1a;font-size:15px;font-weight:bold;text-align:right;">' . $bambini . '</td>
                    </tr>
                </table>
                
                <!-- Box inline: Biglietti + Prezzo -->
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="width:48%;padding-right:2%;">
                            <div style="background:#F07B7B;border-radius:12px;padding:14px;text-align:center;">
                                <p style="margin:0 0 4px 0;color:rgba(255,255,255,0.85);font-size:10px;text-transform:uppercase;letter-spacing:1.5px;font-family:Arial,sans-serif;">Biglietti</p>
                                <p style="margin:0;color:#ffffff;font-size:28px;font-weight:bold;font-family:Georgia,serif;">' . $totale . '</p>
                            </div>
                        </td>
                        <td style="width:48%;padding-left:2%;">
                            <div style="background:#1a1a1a;border-radius:12px;padding:14px;text-align:center;">
                                <p style="margin:0 0 4px 0;color:rgba(255,255,255,0.6);font-size:10px;text-transform:uppercase;letter-spacing:1.5px;font-family:Arial,sans-serif;">Totale</p>
                                <p style="margin:0;color:#ffffff;font-size:28px;font-weight:bold;font-family:Georgia,serif;">€' . number_format($prezzo, 0, ',', '.') . '</p>
                            </div>
                        </td>
                    </tr>
                </table>
                
            </div>
            
            <!-- Footer -->
            <div style="background:#f8f8f8;padding:20px 35px;text-align:center;border-top:1px solid #eee;">
                <p style="margin:0 0 6px 0;color:#888;font-size:13px;">Conserva questa email come conferma</p>
                <p style="margin:0;color:#888;font-size:13px;">
                    <a href="mailto:info@dreamballoons.it" style="color:#F07B7B;text-decoration:none;">info@dreamballoons.it</a> &nbsp;•&nbsp; 
                    <a href="https://dreamballoons.it" style="color:#F07B7B;text-decoration:none;">dreamballoons.it</a>
                </p>
            </div>
            
        </div>
    </body>
    </html>';

    // EMAIL ADMIN - Versione compatta
    $message_admin = '
    <!DOCTYPE html>
    <html>
    <body style="margin:0;padding:20px;background:#f5f5f5;font-family:Arial,sans-serif;">
        <div style="max-width:500px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
            <div style="background:#1a1a1a;padding:20px;text-align:center;">
                <h2 style="margin:0;color:#fff;font-size:18px;">🎈 Nuova Prenotazione</h2>
                <p style="margin:8px 0 0;color:#F07B7B;font-size:14px;">' . $nome_pacchetto . '</p>
            </div>
            <div style="padding:25px;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tr><td style="padding:10px 0;color:#888;border-bottom:1px solid #eee;">Cliente</td><td style="padding:10px 0;font-weight:bold;text-align:right;border-bottom:1px solid #eee;">' . $nome . ' ' . $cognome . '</td></tr>
                    <tr><td style="padding:10px 0;color:#888;border-bottom:1px solid #eee;">Email</td><td style="padding:10px 0;text-align:right;border-bottom:1px solid #eee;"><a href="mailto:' . $email . '" style="color:#F07B7B;">' . $email . '</a></td></tr>
                    <tr><td style="padding:10px 0;color:#888;border-bottom:1px solid #eee;">Telefono</td><td style="padding:10px 0;font-weight:bold;text-align:right;border-bottom:1px solid #eee;">' . $telefono . '</td></tr>
                    <tr><td style="padding:10px 0;color:#888;border-bottom:1px solid #eee;">Cod. Fiscale</td><td style="padding:10px 0;text-align:right;border-bottom:1px solid #eee;">' . $codice_fiscale . '</td></tr>
                    <tr><td style="padding:10px 0;color:#888;border-bottom:1px solid #eee;">Data</td><td style="padding:10px 0;font-weight:bold;text-align:right;border-bottom:1px solid #eee;">' . $day . ', ' . $date_formatted . '</td></tr>
                    <tr><td style="padding:10px 0;color:#888;border-bottom:1px solid #eee;">Orario</td><td style="padding:10px 0;font-weight:bold;text-align:right;border-bottom:1px solid #eee;">' . $orario_label . '</td></tr>
                    <tr><td style="padding:10px 0;color:#888;">Persone</td><td style="padding:10px 0;font-weight:bold;text-align:right;">' . $totale . ' (A:' . $adulti . ' B:' . $bambini . ')</td></tr>
                </table>
                <div style="background:#F07B7B;border-radius:10px;padding:15px;text-align:center;margin-top:20px;">
                    <span style="color:#fff;font-size:14px;">TOTALE:</span>
                    <span style="color:#fff;font-size:24px;font-weight:bold;margin-left:10px;">€' . number_format($prezzo, 0, ',', '.') . '</span>
                </div>
            </div>
        </div>
    </body>
    </html>';

    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: Dream Balloons <noreply@dreamballoons.it>');
    
    // wp_mail('info@dreamballoons.it', 'Nuova Prenotazione ' . $nome_pacchetto . ' - ' . $nome . ' ' . $cognome, $message_admin, $headers);
    wp_mail($email, 'Conferma Prenotazione - Dream Balloons', $message_customer, $headers);

    wp_send_json_success(array('message' => 'Prenotazione confermata', 'prezzo' => $prezzo));
}
?>
