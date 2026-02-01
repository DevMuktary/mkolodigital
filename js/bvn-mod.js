document.addEventListener('DOMContentLoaded', () => {
    // --- 1. GET ALL THE FORM ELEMENTS ---
    const bankSelect = document.getElementById('bank');
    const modTypeSelect = document.getElementById('modType');
    const modificationFieldsContainer = document.getElementById('modificationFields');
    const displayPriceEl = document.getElementById('displayPrice');
    const submitButton = document.querySelector('#modForm button[type="submit"]');
    const modForm = document.getElementById('modForm');
    const resultBox = document.getElementById('resultBox');

    // --- 2. STORE CORRECT, BANK-DEPENDENT PRICES IN JAVASCRIPT ---
    // This structure now matches the PHP backend
    const prices = {
        'first_bank': { 'name': 6000, 'dob': 6000, 'phone': 6000, 'name_dob': 10000, 'name_phone': 9000, 'dob_phone': 9000 },
        'access_bank': { 'name': 6000, 'dob': 6000, 'phone': 6000, 'name_dob': 10000, 'name_phone': 9000, 'dob_phone': 9000 },
        'uba_bank': { 'name': 6000, 'dob': 6000, 'phone': 6000, 'name_dob': 10000, 'name_phone': 9000, 'dob_phone': 9000 },
        'other_banks': { 'name': 7000, 'dob': 7000, 'phone': 7000, 'name_dob': 11000, 'name_phone': 11000, 'dob_phone': 11000 }
    };

    // --- 3. FUNCTION TO UPDATE THE FORM AND PRICE ---
    function updateFormAndPrice() {
        const bank = bankSelect.value;
        const modType = modTypeSelect.value;
        
        modificationFieldsContainer.innerHTML = ''; // Clear previous fields
        let cost = 0;

        // Only proceed if both a bank and a modification type are selected
        if (bank && modType) {
            // Get the price from our new prices object
            cost = prices[bank]?.[modType] || 0;

            // Add specific input fields based on selection
            let fieldsHtml = '<div class="form-group"><label>Step 4: Modification Details</label>';
            if (modType.includes('name')) {
                fieldsHtml += '<input type="text" name="oldName" placeholder="Old Full Name" required class="form-control" style="margin-bottom: 1rem;">';
                fieldsHtml += '<input type="text" name="newName" placeholder="New Full Name" required class="form-control">';
            }
            if (modType.includes('phone')) {
                fieldsHtml += '<input type="tel" name="oldPhone" placeholder="Old Phone Number" required class="form-control" style="margin-bottom: 1rem;">';
                fieldsHtml += '<input type="tel" name="newPhone" placeholder="New Phone Number" required class="form-control">';
            }
            fieldsHtml += '</div>';
            modificationFieldsContainer.innerHTML = fieldsHtml;

            submitButton.disabled = false;
        } else {
            submitButton.disabled = true;
        }

        // Update the price display
        displayPriceEl.textContent = `₦${cost.toLocaleString()}`;
    }

    // --- 4. ADD EVENT LISTENERS ---
    bankSelect.addEventListener('change', updateFormAndPrice);
    modTypeSelect.addEventListener('change', updateFormAndPrice);

    // --- 5. HANDLE FORM SUBMISSION WITH AJAX ---
    modForm.addEventListener('submit', function(event) {
        event.preventDefault();
        resultBox.textContent = 'Submitting...';
        resultBox.className = 'result notice';

        const formData = new FormData(modForm);

        fetch('bvn-mod.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            resultBox.textContent = data.message;
            resultBox.className = (data.status === 'success') ? 'result success' : 'result error';
            if (data.status === 'success') {
                setTimeout(() => window.location.reload(), 2000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultBox.textContent = 'An unexpected error occurred.';
            resultBox.className = 'result error';
        });
    });
});
