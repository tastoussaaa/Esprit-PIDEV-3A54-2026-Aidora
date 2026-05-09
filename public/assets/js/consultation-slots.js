/**
 * Consultation Slots JavaScript
 * Handles time slot selection functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.querySelector('input[name*="dateConsultation"]');
    const timeSlotSelect = document.querySelector('select[name*="timeSlot"]');
    const slotsHelper = document.getElementById('slots-helper');
    
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            console.log('Date selected:', selectedDate);
            
            // Show helper if exists
            if (slotsHelper) {
                slotsHelper.style.display = 'block';
            }
            
            // If time slot select exists, you could trigger loading available slots here
            if (timeSlotSelect) {
                // Example: Load available slots via AJAX
                // loadAvailableSlots(selectedDate);
            }
        });
    }
    
    // Function to load available slots (can be implemented with AJAX)
    function loadAvailableSlots(date) {
        // This function can be extended to fetch available time slots from the server
        console.log('Loading slots for date:', date);
    }
    
    // Expose function globally if needed
    window.loadAvailableSlots = loadAvailableSlots;
});
