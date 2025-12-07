/* ============================================
   BOOKING SYSTEM - JAVASCRIPT
   Versione: 3.1
============================================ */

(function() {
    'use strict';
    
    let availableDates = [];
    let currentMonth = new Date();
    currentMonth.setDate(1);

    const wrapper = document.getElementById('booking-system');
    const TIPO_PACCHETTO = wrapper ? wrapper.dataset.tipo : 'gruppo';
    const IS_PRIVATO = TIPO_PACCHETTO === 'privato';

    const bookingState = {
        selectedDate: null,
        selectedTime: null,
        dateData: null,
        availableSlots: 6, // Posti disponibili per l'orario selezionato
        tickets: { adulto: IS_PRIVATO ? 2 : 0, bambino: 0 },
        customer: { nome: '', cognome: '', codice_fiscale: '', email: '', telefono: '' }
    };

    const MAX_TICKETS = 6;
    const PREZZI = (typeof bookingAjax !== 'undefined' && bookingAjax.prezzi) ? bookingAjax.prezzi : {
        gruppo_adulto: 280, gruppo_bambino: 280, privato: 1100
    };
    
    const monthNames = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    const dayNames = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];

    function formatDateLocal(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function showStep(stepId) {
        document.querySelectorAll('.booking-step').forEach(step => step.classList.remove('active'));
        document.getElementById(stepId).classList.add('active');
    }

    function calculatePrice() {
        if (IS_PRIVATO) return PREZZI.privato;
        return (bookingState.tickets.adulto * PREZZI.gruppo_adulto) + (bookingState.tickets.bambino * PREZZI.gruppo_bambino);
    }

    function formatPrice(price) {
        return '€' + price.toLocaleString('it-IT');
    }

    // Calcola il limite massimo di biglietti (minimo tra MAX_TICKETS e posti disponibili)
    function getMaxAllowedTickets() {
        return Math.min(MAX_TICKETS, bookingState.availableSlots);
    }

    async function loadAvailability() {
        try {
            const response = await fetch(bookingAjax.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_booking_availability', tipo: TIPO_PACCHETTO })
            });
            const data = await response.json();
            if (data.success) {
                availableDates = data.data.dates;
                renderCalendar();
            }
        } catch (error) {
            console.error('Errore:', error);
        }
    }

    function renderCalendar() {
        const calendar = document.getElementById('calendar');
        const monthTitle = document.getElementById('current-month');
        
        monthTitle.textContent = monthNames[currentMonth.getMonth()] + ' ' + currentMonth.getFullYear();
        calendar.innerHTML = '';

        dayNames.forEach(day => {
            const header = document.createElement('div');
            header.className = 'calendar-day-header';
            header.textContent = day;
            calendar.appendChild(header);
        });

        const firstDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1);
        const lastDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0);
        
        let startDay = firstDay.getDay();
        startDay = (startDay === 0) ? 6 : startDay - 1;
        
        for (let i = 0; i < startDay; i++) {
            const empty = document.createElement('div');
            empty.className = 'calendar-day empty';
            calendar.appendChild(empty);
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        for (let day = 1; day <= lastDay.getDate(); day++) {
            const dateObj = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
            const dateStr = formatDateLocal(dateObj);
            
            const dayEl = document.createElement('div');
            dayEl.className = 'calendar-day';
            
            const dateData = availableDates.find(d => d.date === dateStr);
            
            if (dateObj < today) {
                dayEl.classList.add('disabled');
                dayEl.innerHTML = day;
            } else if (dateData) {
                const albaRemaining = parseInt(dateData.alba_slots) - parseInt(dateData.alba_booked);
                const tramontoRemaining = parseInt(dateData.tramonto_slots) - parseInt(dateData.tramonto_booked);
                const albaPrivato = parseInt(dateData.alba_privato) || 0;
                const tramontoPrivato = parseInt(dateData.tramonto_privato) || 0;
                
                let albaAvailable, tramontoAvailable;
                
                if (IS_PRIVATO) {
                    albaAvailable = dateData.alba_available == 1 && albaRemaining == parseInt(dateData.alba_slots) && !albaPrivato;
                    tramontoAvailable = dateData.tramonto_available == 1 && tramontoRemaining == parseInt(dateData.tramonto_slots) && !tramontoPrivato;
                } else {
                    albaAvailable = dateData.alba_available == 1 && albaRemaining > 0 && !albaPrivato;
                    tramontoAvailable = dateData.tramonto_available == 1 && tramontoRemaining > 0 && !tramontoPrivato;
                }
                
                if (albaAvailable || tramontoAvailable) {
                    dayEl.classList.add('available');
                    dayEl.dataset.date = dateStr;
                    dayEl.dataset.dateData = JSON.stringify(dateData);
                    
                    const indicators = [];
                    if (albaAvailable) indicators.push('<div class="indicator alba"></div>');
                    if (tramontoAvailable) indicators.push('<div class="indicator tramonto"></div>');
                    
                    dayEl.innerHTML = `${day}${indicators.length > 0 ? '<div class="day-indicators">' + indicators.join('') + '</div>' : ''}`;
                    
                    dayEl.addEventListener('click', function() {
                        document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
                        this.classList.add('selected');
                        bookingState.selectedDate = dateStr;
                        bookingState.dateData = JSON.parse(this.dataset.dateData);
                        document.getElementById('btn-next-time').disabled = false;
                    });
                } else {
                    dayEl.classList.add('disabled');
                    dayEl.innerHTML = `${day}<br><small style="color:#f44336;font-size:9px;">Pieno</small>`;
                }
            } else {
                dayEl.classList.add('disabled');
                dayEl.innerHTML = day;
            }
            
            calendar.appendChild(dayEl);
        }
    }

    function initCalendarNavigation() {
        document.getElementById('prev-month').addEventListener('click', () => {
            currentMonth.setMonth(currentMonth.getMonth() - 1);
            renderCalendar();
        });
        document.getElementById('next-month').addEventListener('click', () => {
            currentMonth.setMonth(currentMonth.getMonth() + 1);
            renderCalendar();
        });
    }

    function initDateToTimeStep() {
        document.getElementById('btn-next-time').addEventListener('click', () => {
            const dateData = bookingState.dateData;
            const date = new Date(bookingState.selectedDate + 'T00:00:00');
            document.getElementById('time-info').innerHTML = `<strong>Data:</strong> ${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
            
            const albaRemaining = parseInt(dateData.alba_slots) - parseInt(dateData.alba_booked);
            const tramontoRemaining = parseInt(dateData.tramonto_slots) - parseInt(dateData.tramonto_booked);
            const albaPrivato = parseInt(dateData.alba_privato) || 0;
            const tramontoPrivato = parseInt(dateData.tramonto_privato) || 0;
            
            let albaAvailable, tramontoAvailable;
            if (IS_PRIVATO) {
                albaAvailable = dateData.alba_available == 1 && albaRemaining == parseInt(dateData.alba_slots) && !albaPrivato;
                tramontoAvailable = dateData.tramonto_available == 1 && tramontoRemaining == parseInt(dateData.tramonto_slots) && !tramontoPrivato;
            } else {
                albaAvailable = dateData.alba_available == 1 && albaRemaining > 0 && !albaPrivato;
                tramontoAvailable = dateData.tramonto_available == 1 && tramontoRemaining > 0 && !tramontoPrivato;
            }
            
            const albaOption = document.getElementById('option-alba');
            const tramontoOption = document.getElementById('option-tramonto');
            
            // Salva i posti rimanenti nei data attributes
            albaOption.dataset.remaining = albaRemaining;
            tramontoOption.dataset.remaining = tramontoRemaining;
            
            if (albaAvailable) {
                albaOption.classList.remove('disabled');
                document.getElementById('alba-slots-info').innerHTML = IS_PRIVATO ? '<small style="color:#4CAF50;">Disponibile</small>' : `<small style="color:#4CAF50;">${albaRemaining} posti</small>`;
            } else {
                albaOption.classList.add('disabled');
                document.getElementById('alba-slots-info').innerHTML = albaPrivato ? '<small style="color:#f44336;">Privato</small>' : '<small style="color:#f44336;">Non disponibile</small>';
            }
            
            if (tramontoAvailable) {
                tramontoOption.classList.remove('disabled');
                document.getElementById('tramonto-slots-info').innerHTML = IS_PRIVATO ? '<small style="color:#4CAF50;">Disponibile</small>' : `<small style="color:#4CAF50;">${tramontoRemaining} posti</small>`;
            } else {
                tramontoOption.classList.add('disabled');
                document.getElementById('tramonto-slots-info').innerHTML = tramontoPrivato ? '<small style="color:#f44336;">Privato</small>' : '<small style="color:#f44336;">Non disponibile</small>';
            }
            
            albaOption.classList.remove('selected');
            tramontoOption.classList.remove('selected');
            bookingState.selectedTime = null;
            document.getElementById('btn-next-tickets').disabled = true;
            
            showStep('step-time');
        });
    }

    function initTimeSelection() {
        document.querySelectorAll('.time-option').forEach(option => {
            option.addEventListener('click', function() {
                if (this.classList.contains('disabled')) return;
                
                document.querySelectorAll('.time-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                bookingState.selectedTime = this.dataset.time;
                
                // Salva i posti disponibili per questo orario
                bookingState.availableSlots = parseInt(this.dataset.remaining) || MAX_TICKETS;
                
                document.getElementById('btn-next-tickets').disabled = false;
            });
        });
    }

    function initTicketCounters() {
        if (IS_PRIVATO) return;
        document.querySelectorAll('.counter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const type = this.dataset.type;
                const action = this.dataset.action;
                const total = bookingState.tickets.adulto + bookingState.tickets.bambino;
                const maxAllowed = getMaxAllowedTickets();
                
                if (action === 'plus' && total < maxAllowed) {
                    bookingState.tickets[type]++;
                } else if (action === 'minus' && bookingState.tickets[type] > 0) {
                    bookingState.tickets[type]--;
                }
                updateTicketDisplay();
            });
        });
    }

    function updateTicketDisplay() {
        if (IS_PRIVATO) return;
        const total = bookingState.tickets.adulto + bookingState.tickets.bambino;
        const maxAllowed = getMaxAllowedTickets();
        
        document.getElementById('count-adulto').textContent = bookingState.tickets.adulto;
        document.getElementById('count-bambino').textContent = bookingState.tickets.bambino;
        document.getElementById('total-display').innerHTML = `Totale biglietti: <strong>${total}/${maxAllowed}</strong>`;
        document.getElementById('price-display').innerHTML = `Totale: <strong>${formatPrice(calculatePrice())}</strong>`;
        document.getElementById('summary-adulto').textContent = bookingState.tickets.adulto;
        document.getElementById('summary-bambino').textContent = bookingState.tickets.bambino;
        
        // Disabilita + se raggiunto il limite
        document.querySelectorAll('[data-action="plus"]').forEach(btn => btn.disabled = total >= maxAllowed);
        document.querySelectorAll('[data-action="minus"]').forEach(btn => btn.disabled = bookingState.tickets[btn.dataset.type] === 0);
        
        document.getElementById('btn-next-customer').disabled = total === 0;
        
        const errorMsg = document.getElementById('error-message');
        if (total >= maxAllowed) {
            errorMsg.textContent = maxAllowed < MAX_TICKETS ? `Solo ${maxAllowed} posti disponibili` : 'Massimo 6 biglietti';
            errorMsg.style.display = 'block';
        } else {
            errorMsg.style.display = 'none';
        }
    }

    function initStepNavigation() {
        document.getElementById('btn-back-date').addEventListener('click', () => showStep('step-date'));
        
        document.getElementById('btn-next-tickets').addEventListener('click', () => {
            const date = new Date(bookingState.selectedDate + 'T00:00:00');
            const dateFormatted = `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
            const timeFormatted = bookingState.selectedTime.charAt(0).toUpperCase() + bookingState.selectedTime.slice(1);
            
            if (IS_PRIVATO) {
                document.getElementById('final-summary-date').textContent = dateFormatted;
                document.getElementById('final-summary-time').textContent = timeFormatted;
                showStep('step-customer');
            } else {
                // Reset biglietti quando si entra nello step
                bookingState.tickets.adulto = 0;
                bookingState.tickets.bambino = 0;
                updateTicketDisplay();
                
                document.getElementById('summary-date').textContent = dateFormatted;
                document.getElementById('summary-time').textContent = timeFormatted;
                showStep('step-tickets');
            }
        });

        const btnBackTime = document.getElementById('btn-back-time');
        if (btnBackTime) btnBackTime.addEventListener('click', () => showStep('step-time'));

        const btnNextCustomer = document.getElementById('btn-next-customer');
        if (btnNextCustomer) {
            btnNextCustomer.addEventListener('click', () => {
                const date = new Date(bookingState.selectedDate + 'T00:00:00');
                document.getElementById('final-summary-date').textContent = `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
                document.getElementById('final-summary-time').textContent = bookingState.selectedTime.charAt(0).toUpperCase() + bookingState.selectedTime.slice(1);
                document.getElementById('final-summary-persone').textContent = bookingState.tickets.adulto + bookingState.tickets.bambino;
                document.getElementById('final-summary-prezzo').textContent = formatPrice(calculatePrice());
                showStep('step-customer');
            });
        }

        document.getElementById('btn-back-tickets').addEventListener('click', () => showStep(IS_PRIVATO ? 'step-time' : 'step-tickets'));
    }

    function showConfirmationScreen(success, errorMessage = '', prezzo = 0) {
        const date = new Date(bookingState.selectedDate + 'T00:00:00');
        const dayNames = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
        const dateFormatted = `${dayNames[date.getDay()]} ${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
        const totale = IS_PRIVATO ? 2 : (bookingState.tickets.adulto + bookingState.tickets.bambino);
        const nomePacchetto = IS_PRIVATO ? 'Volo Privato per Due' : 'Volo di Gruppo';
        
        if (success) {
            wrapper.innerHTML = `
                <div class="confirmation-screen">
                    <div class="confirmation-icon success"><svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" fill="currentColor"/></svg></div>
                    <h2 class="confirmation-title success">Prenotazione effettuata!</h2>
                    <p class="confirmation-subtitle">Riceverai un'email di conferma a:<br><strong>${bookingState.customer.email}</strong></p>
                    <div class="confirmation-details">
                        <h3>Riepilogo</h3>
                        <div class="detail-row"><span class="detail-label">Pacchetto</span><span class="detail-value">${nomePacchetto}</span></div>
                        <div class="detail-row"><span class="detail-label">Data</span><span class="detail-value">${dateFormatted}</span></div>
                        <div class="detail-row"><span class="detail-label">Orario</span><span class="detail-value">${bookingState.selectedTime === 'alba' ? '🌅 Alba' : '🌇 Tramonto'}</span></div>
                        <div class="detail-row"><span class="detail-label">Persone</span><span class="detail-value">${totale}</span></div>
                        <div class="detail-row total"><span class="detail-label">Totale</span><span class="detail-value">${formatPrice(prezzo)}</span></div>
                    </div>
                    <button class="btn-primary" onclick="location.reload()">Nuova prenotazione</button>
                </div>`;
        } else {
            wrapper.innerHTML = `
                <div class="confirmation-screen">
                    <div class="confirmation-icon error"><svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" fill="currentColor"/></svg></div>
                    <h2 class="confirmation-title error">Prenotazione non riuscita</h2>
                    <p class="confirmation-subtitle">${errorMessage}</p>
                    <button class="btn-primary" onclick="location.reload()">Riprova</button>
                </div>`;
        }
    }

    function showLoadingOverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'booking-loading-overlay';
        overlay.innerHTML = `
            <div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.95);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999;">
                <img src="https://www.dreamballoons.it/wp-content/uploads/2025/03/provagif.gif" alt="Caricamento..." style="width:120px;height:120px;margin-bottom:20px;">
                <p style="font-size:16px;color:#666;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">Conferma in corso...</p>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    function hideLoadingOverlay() {
        const overlay = document.getElementById('booking-loading-overlay');
        if (overlay) overlay.remove();
    }

    function initBookingSubmission() {
        document.getElementById('btn-complete').addEventListener('click', () => {
            const form = document.getElementById('customer-form');
            if (!form.checkValidity()) { form.reportValidity(); return; }

            bookingState.customer.nome = document.getElementById('nome').value.trim();
            bookingState.customer.cognome = document.getElementById('cognome').value.trim();
            bookingState.customer.codice_fiscale = document.getElementById('codice_fiscale').value.trim().toUpperCase();
            bookingState.customer.email = document.getElementById('email').value.trim();
            bookingState.customer.telefono = document.getElementById('telefono').value.trim();

            const btn = document.getElementById('btn-complete');
            btn.disabled = true;
            
            // Mostra loading overlay con gif
            showLoadingOverlay();

            fetch(bookingAjax.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'process_booking',
                    date: bookingState.selectedDate,
                    time: bookingState.selectedTime,
                    tipo: TIPO_PACCHETTO,
                    adulti: IS_PRIVATO ? 2 : bookingState.tickets.adulto,
                    bambini: IS_PRIVATO ? 0 : bookingState.tickets.bambino,
                    nome: bookingState.customer.nome,
                    cognome: bookingState.customer.cognome,
                    codice_fiscale: bookingState.customer.codice_fiscale,
                    email: bookingState.customer.email,
                    telefono: bookingState.customer.telefono
                })
            })
            .then(r => r.json())
            .then(data => {
                hideLoadingOverlay();
                if (data.success) showConfirmationScreen(true, '', data.data.prezzo);
                else showConfirmationScreen(false, data.data ? data.data.message : 'Errore');
            })
            .catch(() => {
                hideLoadingOverlay();
                showConfirmationScreen(false, 'Errore di connessione');
            });
        });
    }

    function init() {
        if (!document.getElementById('calendar')) return;
        loadAvailability();
        initCalendarNavigation();
        initDateToTimeStep();
        initTimeSelection();
        initTicketCounters();
        initStepNavigation();
        initBookingSubmission();
        if (!IS_PRIVATO) updateTicketDisplay();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
