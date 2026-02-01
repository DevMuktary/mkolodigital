document.addEventListener('DOMContentLoaded', () => {
    const tinOptions = document.querySelectorAll('.tin-option');
    const requirementsSections = document.querySelectorAll('.requirements');
    const form = document.getElementById('tinForm');
    const resultBox = document.getElementById('resultBox');

    // Form fields
    const dobField = document.getElementById('dobField');
    const bvnInput = document.getElementById('bvn');
    const businessFields = document.getElementById('businessFields');
    const submitButton = form.querySelector('button[type="submit"]');

    // --- 1. Handle TIN Type Selection ---
    tinOptions.forEach(option => {
        option.addEventListener('click', () => {
            // UI updates
            tinOptions.forEach(opt => opt.classList.remove('selected'));
            option.classList.add('selected');

            const type = option.dataset.type;
            const price = option.dataset.price;
            const typeName = option.querySelector('.option-title').innerText;

            document.getElementById('selected-service-name').textContent = typeName;
            document.getElementById('selected-service-price').textContent = `₦${parseInt(price).toLocaleString()}`;
            document.getElementById('tinType').value = type;

            // Show correct requirements
            requirementsSections.forEach(req => req.style.display = 'none');
            document.getElementById(`${type}-req`).style.display = 'block';

            // Update form visibility
            updateFormFields(type);
        });
    });

    // --- 2. Function to show/hide form fields ---
    function updateFormFields(type) {
        // Reset all to hidden/not required
        dobField.style.display = 'none';
        bvnInput.required = false;
        businessFields.style.display = 'none';
        Object.values(businessFields.querySelectorAll('input, textarea')).forEach(el => el.required = false);

        // Enable fields based on type
        if (type === 'individual') {
            dobField.style.display = 'block';
            bvnInput.required = true;
        } else if (type === 'business' || type === 'company') {
            businessFields.style.display = 'block';
            Object.values(businessFields.querySelectorAll('input, textarea')).forEach(el => el.required = true);
        }
        submitButton.disabled = false;
    }
    
    // --- 3. Handle Form Submission ---
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        resultBox.textContent = 'Submitting request...';
        resultBox.className = 'result notice';

        const formData = new FormData(form);

        fetch('jtb-tin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            resultBox.textContent = data.message;
            if (data.status === 'success') {
                resultBox.className = 'result success';
                setTimeout(() => window.location.reload(), 2000);
            } else {
                resultBox.className = 'result error';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultBox.textContent = 'A client-side error occurred.';
            resultBox.className = 'result error';
        });
    });
});
