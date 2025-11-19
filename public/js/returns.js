/**
 * Shows the modal for returning an equipment item.
 * Made global with `window.` to be accessible from inline `onclick` attributes.
 *
 * ✅ CORRECTED: Now uses classList.toggle('hidden') to work with Tailwind CSS.
 *
 * @param {number} transactionId The ID of the transaction to be returned.
 * @param {string} equipmentName The name of the equipment.
 */
window.showReturnModal = function(transactionId, equipmentName) {
    const modal = document.getElementById('return-modal');
    if (!modal) {
        console.error('Error: The modal with ID "return-modal" was not found in the DOM.');
        return;
    }

    // Set up the form action and hidden values
    const form = modal.querySelector('#return-form');
    if (form) {
        // Assuming your route is named 'returns.store' and can be generated this way
        // If not, you might need a different way to build the URL.
        // For now, we will leave the action as it is in the Blade file.
        form.querySelector('#return-transaction-id').value = transactionId;
    }

    // Set display values in the modal
    modal.querySelector('#return-item-name').textContent = `อุปกรณ์: ${equipmentName}`;

    // Reset form state to default
    modal.querySelector('input[name="return_condition"][value="good"]').checked = true;
    const problemWrapper = modal.querySelector('#problem-description-wrapper');
    const problemTextarea = modal.querySelector('#problem_description');
    
    // Hide the problem description wrapper initially
    problemWrapper.classList.add('hidden');
    problemTextarea.required = false;
    problemTextarea.value = ''; // Clear previous text

    // Add event listener to show/hide problem description
    modal.querySelectorAll('input[name="return_condition"]').forEach(radio => {
        radio.onchange = function() {
            // Use classList.toggle or add/remove for visibility
            if (this.value === 'defective') {
                problemWrapper.classList.remove('hidden'); // Show the wrapper
                problemTextarea.required = true;
            } else {
                problemWrapper.classList.add('hidden'); // Hide the wrapper
                problemTextarea.required = false;
            }
        };
    });
    
    // Assumes you have a global showModal function from another main script
    if (typeof showModal === 'function') {
        showModal('return-modal');
    } else {
        console.error('The global function showModal() is not defined.');
    }
}