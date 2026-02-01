<?php
session_start();
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once "../db.php";

// [REMOVED] The check for is_beta_tester is removed. All logged-in admins can access this page.

// Fetch all VTU products and group them
$products = [
    'airtime' => [],
    'data' => []
];
$sql = "SELECT id, service_type, network, category, plan_name, selling_price, status FROM vtu_products ORDER BY network, service_type, category, selling_price";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $products[$row['service_type']][$row['network']][$row['category']][] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VTU Settings - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #dc3545; --background-color: #f8f9fa; --card-bg: #ffffff;
            --text-color: #495057; --heading-color: #212529; --border-color: #dee2e6;
            --success-color: #198754; --disabled-color: #6c757d;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 1rem; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;}
        .page-header h1 { color: var(--heading-color); margin: 0; font-size: 1.8rem; }
        .page-header a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .tabs { display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 2rem; overflow-x: auto; }
        .tab-link { padding: 1rem 1.5rem; cursor: pointer; font-weight: 600; color: var(--text-color); border: none; background: none; font-size: 1rem; white-space: nowrap; }
        .tab-link.active { color: var(--primary-color); border-bottom: 2px solid var(--primary-color); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .network-section { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 2rem; }
        .network-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; }
        .network-header h2 { margin: 0; font-size: 1.5rem; }
        .network-body { padding: 1rem; }
        .category-header { font-weight: 600; font-size: 1.1rem; color: var(--heading-color); padding-bottom: 0.5rem; margin-top: 1rem; margin-bottom: 1rem; border-bottom: 1px solid #eee; }
        .plan-row { display: grid; grid-template-columns: 1fr 120px 80px 100px; gap: 1rem; align-items: center; padding: 1rem 0; border-bottom: 1px solid #f0f0f0; }
        .plan-row:last-child { border-bottom: none; }
        .plan-name { font-weight: 500; }
        .price-input { width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; text-align: right; }
        .price-input:focus { outline: none; border-color: var(--primary-color); }
        .btn-save {
            padding: 0.5rem 1rem; border: none; background-color: var(--primary-color); color: white;
            border-radius: 6px; cursor: pointer; font-weight: 500; visibility: hidden;
            opacity: 0; transition: opacity 0.2s, visibility 0.2s;
        }
        .btn-save.visible { visibility: visible; opacity: 1; }
        .status-toggle-group { display: flex; justify-content: flex-end; }
        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--success-color); }
        input:checked + .slider:before { transform: translateX(26px); }
        #toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background-color: #333; color: #fff; padding: 1rem 2rem; border-radius: 8px; z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.5s, visibility 0.5s; }
        #toast.show { opacity: 1; visibility: visible; }
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .plan-row {
                grid-template-columns: 1fr; gap: 1rem; padding: 1rem; border: 1px solid var(--border-color);
                border-radius: 8px; margin-bottom: 1rem;
            }
            .plan-row:last-child { margin-bottom: 0; }
            .plan-row > span, .plan-row > div { width: 100%; justify-content: space-between; display: flex; align-items: center; }
            .price-input-group::before { content: 'Price (₦):'; font-weight: 500; }
            .status-toggle-group::before { content: 'Status:'; font-weight: 500; }
            .price-input { width: 120px; }
            .btn-save { width: 80px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1><i class="fas fa-wifi"></i> VTU Service Settings</h1>
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </header>
        <div class="tabs">
            <button class="tab-link active" onclick="openTab(event, 'data-tab')">Data Plans</button>
            <button class="tab-link" onclick="openTab(event, 'airtime-tab')">Airtime Types</button>
        </div>
        <div id="data-tab" class="tab-content active">
            <?php foreach ($products['data'] as $network => $categories): ?>
                <div class="network-section">
                    <div class="network-header"><h2><?php echo $network; ?> Data</h2></div>
                    <div class="network-body">
                        <?php foreach ($categories as $category => $plans): ?>
                            <h3 class="category-header"><?php echo $category; ?></h3>
                            <?php foreach ($plans as $plan): ?>
                                <div class="plan-row" data-id="<?php echo $plan['id']; ?>">
                                    <span class="plan-name"><?php echo htmlspecialchars($plan['plan_name']); ?></span>
                                    <div class="price-input-group"><input type="number" class="price-input" value="<?php echo $plan['selling_price']; ?>" step="0.01"></div>
                                    <button class="btn-save">Save</button>
                                    <div class="status-toggle-group">
                                        <label class="switch">
                                            <input type="checkbox" class="status-toggle" <?php echo ($plan['status'] == 'active') ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div id="airtime-tab" class="tab-content">
             <?php foreach ($products['airtime'] as $network => $categories): ?>
                <div class="network-section">
                    <div class="network-header"><h2><?php echo $network; ?> Airtime</h2></div>
                    <div class="network-body">
                        <?php foreach ($categories as $category => $types): ?>
                            <?php foreach ($types as $type): ?>
                                <div class="plan-row" data-id="<?php echo $type['id']; ?>">
                                    <span class="plan-name"><?php echo htmlspecialchars($type['plan_name']); ?></span>
                                    <span></span><span></span>
                                    <div class="status-toggle-group">
                                        <label class="switch">
                                            <input type="checkbox" class="status-toggle" <?php echo ($type['status'] == 'active') ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div id="toast"></div>
    <script>
        function openTab(evt, tabName) {
            let i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) { tabcontent[i].style.display = "none"; }
            tablinks = document.getElementsByClassName("tab-link");
            for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = "show";
            setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 3000);
        }
        function updatePlanOnServer(id, price, status, callback) {
            const formData = new FormData();
            formData.append('action', 'update_plan');
            formData.append('plan_id', id);
            if (price !== null) formData.append('price', price);
            formData.append('status', status);
            fetch('vtu_settings_ajax.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => callback(data))
            .catch(error => callback({ status: 'error', message: 'Network error' }));
        }
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.price-input').forEach(input => {
                input.addEventListener('input', () => {
                    const row = input.closest('.plan-row');
                    const saveBtn = row.querySelector('.btn-save');
                    saveBtn.classList.add('visible');
                });
            });
            document.querySelectorAll('.btn-save').forEach(button => {
                button.addEventListener('click', () => {
                    const row = button.closest('.plan-row');
                    const id = row.dataset.id;
                    const price = row.querySelector('.price-input').value;
                    const status = row.querySelector('.status-toggle').checked ? 'active' : 'inactive';
                    button.textContent = '...';
                    updatePlanOnServer(id, price, status, (data) => {
                        if (data.status === 'success') {
                            showToast('Price updated!');
                            button.classList.remove('visible');
                        } else {
                            showToast('Error: ' + data.message);
                        }
                        button.textContent = 'Save';
                    });
                });
            });
            document.querySelectorAll('.status-toggle').forEach(toggle => {
                toggle.addEventListener('change', () => {
                    const row = toggle.closest('.plan-row');
                    const id = row.dataset.id;
                    const priceInput = row.querySelector('.price-input');
                    const price = priceInput ? priceInput.value : null;
                    const status = toggle.checked ? 'active' : 'inactive';
                    updatePlanOnServer(id, price, status, (data) => {
                        if (data.status === 'success') {
                            showToast('Status updated!');
                        } else {
                            showToast('Error: ' + data.message);
                            toggle.checked = !toggle.checked;
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
