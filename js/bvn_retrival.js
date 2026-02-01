document.addEventListener('DOMContentLoaded', () => {
    const verifyTypeSelect = document.getElementById('verifyType');
    const identifierLabel = document.getElementById('identifierLabel');
    const identifierInput = document.getElementById('identifier');
    const bvnForm = document.getElementById('bvnForm');
    const resultBox = document.getElementById('resultBox');
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');

    // --- 1. Update form label based on selection ---
    verifyTypeSelect.addEventListener('change', () => {
        if (verifyTypeSelect.value === 'phone') {
            identifierLabel.textContent = 'Enter Phone Number';
            identifierInput.placeholder = 'Enter your phone number';
            identifierInput.type = 'tel';
        } else if (verifyTypeSelect.value === 'account') {
            identifierLabel.textContent = 'Enter Bank Account Number';
            identifierInput.placeholder = 'Enter your NUBAN account number';
            identifierInput.type = 'text';
        } else {
            identifierLabel.textContent = 'Enter Phone Number';
            identifierInput.placeholder = 'Enter your phone number';
        }
    });

    // --- 2. Handle form submission ---
    bvnForm.addEventListener('submit', function(event) {
        event.preventDefault();
        resultBox.textContent = 'Submitting...';
        resultBox.className = 'result notice';

        const formData = new FormData(bvnForm);
        // Manually append select values
        formData.append('verifyType', document.getElementById('verifyType').value);
        formData.append('retrievalType', document.getElementById('retrievalType').value);


        fetch('bvn-retrieval.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                resultBox.textContent = '';
                resultBox.className = 'result';
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 2000);
            } else {
                resultBox.textContent = data.message;
                resultBox.className = 'result error';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultBox.textContent = 'An unexpected error occurred.';
            resultBox.className = 'result error';
        });
    });

    // --- 3. Toast notification function ---
    function showToast(message, type) {
        toastMessage.textContent = message;
        toast.className = `toast show ${type}`;
        setTimeout(() => {
            toast.className = 'toast';
        }, 3000);
    }
});
