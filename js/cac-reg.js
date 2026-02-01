document.addEventListener('DOMContentLoaded', () => {
    // --- Step 1: Handle Service Selection ---
    const serviceCards = document.querySelectorAll('.service-card');
    const selectedServiceName = document.getElementById('selected-service-name');
    const selectedServicePrice = document.getElementById('selected-service-price');
    const hiddenServiceTypeInput = document.getElementById('serviceType');
    const submitButton = document.querySelector('#cacForm button[type="submit"]');

    serviceCards.forEach(card => {
        card.querySelector('.select-service').addEventListener('click', () => {
            // Remove 'selected' class from all cards
            serviceCards.forEach(c => c.classList.remove('selected'));
            // Add 'selected' class to the clicked card
            card.classList.add('selected');

            const serviceName = card.dataset.service;
            const servicePrice = card.dataset.price;

            // Update the display
            selectedServiceName.textContent = serviceName;
            selectedServicePrice.textContent = `₦${parseInt(servicePrice).toLocaleString()}`;
            
            // Update the hidden form field
            hiddenServiceTypeInput.value = serviceName;

            // Enable the submit button
            submitButton.disabled = false;
        });
    });

    // --- Step 2: Handle Form Submission with File Uploads ---
    const cacForm = document.getElementById('cacForm');
    const resultBox = document.getElementById('resultBox');

    cacForm.addEventListener('submit', function(event) {
        event.preventDefault();

        resultBox.textContent = 'Processing your request...';
        resultBox.className = 'result notice';

        // FormData automatically handles file uploads from the form
        const formData = new FormData(cacForm);

        fetch('cac-reg.php', {
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
            resultBox.textContent = 'A client-side error occurred. Please try again.';
            resultBox.className = 'result error';
        });
    });
});
