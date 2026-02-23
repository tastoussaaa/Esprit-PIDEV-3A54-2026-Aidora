document.addEventListener('DOMContentLoaded', function() {
    console.log('consultation-slots.js loaded');
    
    const dateInput = document.querySelector('[name*="dateConsultation"]');
    const timeSlotSelect = document.querySelector('[name*="timeSlot"]');
    const slotsInfo = document.getElementById('slots-info');

    console.log('Date input found:', dateInput);
    console.log('Time slot select found:', timeSlotSelect);
    console.log('Slots info element found:', slotsInfo);

    if (!dateInput || !timeSlotSelect) {
        console.warn('Required form elements not found');
        return;
    }

    // Store all available time slots
    const allSlots = Array.from(timeSlotSelect.options)
        .slice(1) // Skip placeholder
        .map(option => ({
            value: option.value,
            text: option.text
        }));

    console.log('All slots:', allSlots);

    /**
     * Update available time slots based on selected date
     */
    async function updateAvailableSlots() {
        const date = dateInput.value;
        console.log('Date selected:', date);
        
        if (!date) {
            // Reset to show all slots
            resetTimeSlotOptions();
            if (slotsInfo) {
                slotsInfo.innerHTML = 'Sélectionnez d\'abord une date pour voir les créneaux disponibles.';
            }
            return;
        }

        try {
            const url = `/consultation/api/available-slots?date=${date}`;
            console.log('Fetching:', url);
            
            const response = await fetch(url);
            const data = await response.json();

            console.log('Response:', data);

            if (response.ok) {
                updateTimeSlotOptions(data.available_slots);
                if (slotsInfo) {
                    displaySlotsInfo(data);
                }
            } else {
                console.error('Error:', data.error);
                resetTimeSlotOptions();
                if (slotsInfo) {
                    slotsInfo.innerHTML = 'Erreur lors du chargement des créneaux.';
                }
            }
        } catch (error) {
            console.error('Fetch error:', error);
            resetTimeSlotOptions();
            if (slotsInfo) {
                slotsInfo.innerHTML = 'Erreur lors du chargement des créneaux.';
            }
        }
    }

    /**
     * Update time slot options with only available slots
     */
    function updateTimeSlotOptions(availableSlots) {
        // Keep the placeholder option
        const placeholder = timeSlotSelect.options[0];
        
        // Clear existing options except placeholder
        while (timeSlotSelect.options.length > 1) {
            timeSlotSelect.remove(1);
        }

        // Add available slots
        availableSlots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot;
            option.text = slot;
            timeSlotSelect.appendChild(option);
        });

        // Clear current selection if it's not available
        if (!availableSlots.includes(timeSlotSelect.value)) {
            timeSlotSelect.value = '';
        }
    }

    /**
     * Reset time slot options to show all
     */
    function resetTimeSlotOptions() {
        // Keep the placeholder option
        const placeholder = timeSlotSelect.options[0];
        
        // Clear existing options except placeholder
        while (timeSlotSelect.options.length > 1) {
            timeSlotSelect.remove(1);
        }

        // Add all slots back
        allSlots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot.value;
            option.text = slot.text;
            timeSlotSelect.appendChild(option);
        });
    }

    /**
     * Display slots information
     */
    function displaySlotsInfo(data) {
        if (!slotsInfo) return;
        
        const allSlotsList = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'];
        
        let html = '<div style="margin-bottom: 8px;"><strong>Disponibles (' + data.available_slots.length + '/' + allSlotsList.length + '):</strong></div>';
        
        if (data.available_slots.length > 0) {
            data.available_slots.forEach(slot => {
                html += `<span class="slot-available">${slot}</span>`;
            });
        } else {
            html += '<span style="color: #8b0000;">Aucun créneau disponible ce jour</span>';
        }

        if (data.unavailable_slots.length > 0) {
            html += '<div style="margin-top: 12px;"><strong>Occupés (' + data.unavailable_slots.length + '):</strong></div>';
            data.unavailable_slots.forEach(slot => {
                html += `<span class="slot-unavailable">${slot}</span>`;
            });
        }

        slotsInfo.innerHTML = html;
    }

    // Listen for date changes
    dateInput.addEventListener('change', function() {
        console.log('Date change event triggered');
        updateAvailableSlots();
    });

    // Initial load - if date is already selected, update slots
    if (dateInput.value) {
        console.log('Initial load with date:', dateInput.value);
        updateAvailableSlots();
    }
});
