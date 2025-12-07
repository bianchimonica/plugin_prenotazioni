<!-- ============================================
     BOOKING SYSTEM - TEMPLATE HTML
     Versione: 3.2
============================================ -->

<?php $tipo_pacchetto = isset($tipo) ? $tipo : 'gruppo'; ?>

<div id="booking-system" class="booking-wrapper" data-tipo="<?php echo esc_attr($tipo_pacchetto); ?>">

    <!-- STEP 1: SELEZIONE DATA -->
    <div id="step-date" class="booking-step active">
        
        <h2 class="step-title">Scegli un giorno</h2>
        
        <div class="material-card">
            <div class="calendar-header">
                <button class="nav-btn" id="prev-month" aria-label="Mese precedente">
                    <svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                </button>
                <h3 id="current-month">Caricamento...</h3>
                <button class="nav-btn" id="next-month" aria-label="Mese successivo">
                    <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                </button>
            </div>
            
            <div class="calendar-grid" id="calendar"></div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="indicator alba"></div>
                    <span>Alba</span>
                </div>
                <div class="legend-item">
                    <div class="indicator tramonto"></div>
                    <span>Tramonto</span>
                </div>
            </div>
        </div>
        
        <div class="step-navigation">
            <button class="btn-primary" id="btn-next-time" disabled>
                Continua
                <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/></svg>
            </button>
        </div>
    </div>

    <!-- STEP 2: SELEZIONE ORARIO -->
    <div id="step-time" class="booking-step">
        <button class="btn-back" id="btn-back-date">
            <svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/></svg>
            Indietro
        </button>
        
        <h2 class="step-title">Scegli l'orario</h2>
        
        <div class="info-message" id="time-info"></div>
        
        <div class="material-card">
            <div class="time-options">
                <div class="time-option" data-time="alba" id="option-alba">
                    <div class="time-icon">🌅</div>
                    <div class="time-label">Alba</div>
                    <div class="time-slots" id="alba-slots-info"></div>
                </div>
                
                <div class="time-option" data-time="tramonto" id="option-tramonto">
                    <div class="time-icon">🌇</div>
                    <div class="time-label">Tramonto</div>
                    <div class="time-slots" id="tramonto-slots-info"></div>
                </div>
            </div>
        </div>
        
        <div class="step-navigation">
            <button class="btn-primary" id="btn-next-tickets" disabled>
                Continua
                <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/></svg>
            </button>
        </div>
    </div>

    <!-- STEP 3: SELEZIONE BIGLIETTI (solo gruppo) -->
    <?php if ($tipo_pacchetto == 'gruppo'): ?>
    <div id="step-tickets" class="booking-step">
        <button class="btn-back" id="btn-back-time">
            <svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/></svg>
            Indietro
        </button>
        
        <h2 class="step-title">Seleziona i biglietti</h2>
        
        <div class="material-card">
            <div class="ticket-type">
                <div class="ticket-info">
                    <span class="ticket-name">Adulto</span>
                    <span class="ticket-desc">Età: 13+ anni</span>
                </div>
                <div class="ticket-counter">
                    <button class="counter-btn" data-type="adulto" data-action="minus">−</button>
                    <span class="counter-value" id="count-adulto">0</span>
                    <button class="counter-btn" data-type="adulto" data-action="plus">+</button>
                </div>
            </div>

            <div class="ticket-type">
                <div class="ticket-info">
                    <span class="ticket-name">Bambino</span>
                    <span class="ticket-desc">Età: 3-12 anni</span>
                </div>
                <div class="ticket-counter">
                    <button class="counter-btn" data-type="bambino" data-action="minus">−</button>
                    <span class="counter-value" id="count-bambino">0</span>
                    <button class="counter-btn" data-type="bambino" data-action="plus">+</button>
                </div>
            </div>

            <div class="total-tickets" id="total-display">
                Totale biglietti: <strong>0/6</strong>
            </div>

            <div class="price-display" id="price-display">
                Totale: <strong>€0</strong>
            </div>

            <div class="error-message" id="error-message" style="display: none;"></div>

            <div class="summary">
                <h3>Riepilogo</h3>
                <div class="summary-item">
                    <span>Data:</span>
                    <strong id="summary-date">-</strong>
                </div>
                <div class="summary-item">
                    <span>Orario:</span>
                    <strong id="summary-time">-</strong>
                </div>
                <div class="summary-item">
                    <span>Adulti:</span>
                    <strong id="summary-adulto">0</strong>
                </div>
                <div class="summary-item">
                    <span>Bambini:</span>
                    <strong id="summary-bambino">0</strong>
                </div>
            </div>
        </div>

        <div class="step-navigation">
            <button class="btn-primary" id="btn-next-customer" disabled>
                Continua
                <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/></svg>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- STEP 4: DATI CLIENTE -->
    <div id="step-customer" class="booking-step">
        <button class="btn-back" id="btn-back-tickets">
            <svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/></svg>
            Indietro
        </button>
        
        <h2 class="step-title">I tuoi dati</h2>
        
        <div class="material-card">
            <form id="customer-form">
                <div class="form-group">
                    <label for="nome">Nome *</label>
                    <input type="text" id="nome" name="nome" required autocomplete="given-name">
                </div>

                <div class="form-group">
                    <label for="cognome">Cognome *</label>
                    <input type="text" id="cognome" name="cognome" required autocomplete="family-name">
                </div>

                <div class="form-group">
                    <label for="codice_fiscale">Codice Fiscale *</label>
                    <input type="text" id="codice_fiscale" name="codice_fiscale" required maxlength="16" pattern="[A-Za-z0-9]{16}" style="text-transform: uppercase;" autocomplete="off">
                    <small>16 caratteri alfanumerici</small>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="telefono">Telefono *</label>
                    <input type="tel" id="telefono" name="telefono" required pattern="[0-9+\s\-]{9,15}" autocomplete="tel">
                    <small>Es: +39 123 456 7890</small>
                </div>

                <div class="summary">
                    <h3>Riepilogo finale</h3>
                    <div class="summary-item">
                        <span>Pacchetto:</span>
                        <strong id="final-summary-package"><?php echo ($tipo_pacchetto == 'privato') ? 'Volo Privato per Due' : 'Volo di Gruppo'; ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Data:</span>
                        <strong id="final-summary-date">-</strong>
                    </div>
                    <div class="summary-item">
                        <span>Orario:</span>
                        <strong id="final-summary-time">-</strong>
                    </div>
                    <div class="summary-item">
                        <span>Persone:</span>
                        <strong id="final-summary-persone"><?php echo ($tipo_pacchetto == 'privato') ? '2' : '0'; ?></strong>
                    </div>
                    <div class="summary-item summary-total">
                        <span>Totale:</span>
                        <strong id="final-summary-prezzo">€<?php echo ($tipo_pacchetto == 'privato') ? number_format(PREZZO_PRIVATO, 0, ',', '.') : '0'; ?></strong>
                    </div>
                </div>
            </form>
        </div>

        <div class="step-navigation">
            <button class="btn-primary" id="btn-complete">
                Conferma Prenotazione
                <svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" fill="currentColor"/></svg>
            </button>
        </div>
    </div>

</div>
