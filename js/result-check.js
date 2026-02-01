document.addEventListener('DOMContentLoaded', () => {
    // Store prices in a JS object to make the form interactive
    const prices = {
        'NECO': { 'Result': 5000, 'PIN': 3000 },
        'NABTEB': { 'Result': 5000, 'PIN': 3000 },
        'WAEC': { 'Result': 5000, 'PIN': 3000 }
    };

    const examTypeSelect = document.getElementById('examType');
    const serviceTypeSelect = document.getElementById('serviceType');
    const displayPrice = document.getElementById('displayPrice');
    const resultForm = document.getElementById('resultForm');
    const resultBox = document.getElementById('resultBox');

    // --- 1. Update price display when selections change ---
    function updatePrice() {
        const exam = examTypeSelect.value;
        const service = serviceTypeSelect.value;
        
        if (exam && service) {
            const price = prices[exam][service];
            displayPrice.textContent = `₦${price.toLocaleString()}`;
        } else {
            displayPrice.textContent = '₦0.00';
        }
    }

    examTypeSelect.addEventListener('change', updatePrice);
    serviceTypeSelect.addEventListener('change', updatePrice);

    // --- 2. Handle form submission ---
    resultForm.addEventListener('submit', function(event) {
        event.preventDefault();
        resultBox.textContent = 'Submitting...';
        resultBox.className = 'result notice';

        const formData = new FormData(resultForm);

        fetch('result-check.php', {
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
