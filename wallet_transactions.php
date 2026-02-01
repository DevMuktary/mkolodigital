<?php
$pageTitle = "Wallet History";
include_once 'header.php';

// --- [ENHANCED] PAGINATION & FILTERING LOGIC ---

// 1. Define pagination variables
$limit = 15; // Number of transactions per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 2. Get filter parameters from the URL
$filter_type = $_GET['type'] ?? '';
$filter_search = $_GET['search'] ?? '';

// 3. Build the dynamic SQL query
$base_sql = "(SELECT 'credit' as type, amount, description, transaction_date FROM transactions WHERE user_id = ?)
             UNION ALL
             (SELECT 'debit' as type, amount_charged as amount, plan_name as description, created_at as transaction_date FROM vtu_transactions WHERE user_id = ? AND status = 'success')";

$where_clauses = [];
$params = [$_SESSION["id"], $_SESSION["id"]]; // Base params for user_id
$param_types = "ii";

if (!empty($filter_type)) {
    $where_clauses[] = "type = ?";
    $params[] = $filter_type;
    $param_types .= "s";
}
if (!empty($filter_search)) {
    $where_clauses[] = "description LIKE ?";
    $params[] = "%" . $filter_search . "%";
    $param_types .= "s";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// 4. Get the TOTAL count of transactions for pagination
$count_sql = "SELECT COUNT(*) as total FROM ({$base_sql}) as combined_transactions" . $where_sql;
$stmt_count = $conn->prepare($count_sql);
$stmt_count->bind_param($param_types, ...$params);
$stmt_count->execute();
$total_transactions = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_transactions / $limit);
$stmt_count->close();

// 5. Fetch the transactions for the CURRENT page
$all_transactions = [];
$fetch_sql = "SELECT * FROM ({$base_sql}) as combined_transactions" . $where_sql . " ORDER BY transaction_date DESC LIMIT ? OFFSET ?";
$param_types .= "ii";
$params[] = $limit;
$params[] = $offset;

$stmt_fetch = $conn->prepare($fetch_sql);
$stmt_fetch->bind_param($param_types, ...$params);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
while ($row = $result->fetch_assoc()) {
    $all_transactions[] = $row;
}
$stmt_fetch->close();
?>

<style>
    /* Using Tailwind via CDN, but adding some custom component styles here for clarity */
    .filter-form input, .filter-form select {
        @apply w-full md:w-auto bg-white border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-shadow;
    }
    .pagination-link {
        @apply inline-flex items-center justify-center w-10 h-10 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors;
    }
    .pagination-link.active {
        @apply bg-indigo-600 text-white font-bold shadow-sm pointer-events-none;
    }
</style>

<div class="page-header">
    <div class="page-header-title">
        <h1>Wallet Transaction History</h1>
        <p>A complete record of all your wallet activities.</p>
    </div>
</div>

<div class="card">
    <div class="card-header" style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
        <form method="GET" action="wallet_transactions.php" class="flex flex-col md:flex-row gap-4 w-full">
            <div class="flex-grow">
                <input type="text" name="search" placeholder="Search by description (e.g., MTN, Funding)..." value="<?php echo htmlspecialchars($filter_search); ?>">
            </div>
            <div class="flex-shrink-0">
                <select name="type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="credit" <?php if ($filter_type == 'credit') echo 'selected'; ?>>Credit</option>
                    <option value="debit" <?php if ($filter_type == 'debit') echo 'selected'; ?>>Debit</option>
                </select>
            </div>
            <div class="flex-shrink-0">
                <button type="submit" class="w-full md:w-auto bg-indigo-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-1/4">Date</th>
                        <th class="w-1/2">Description</th>
                        <th class="w-1/4 text-center">Type</th>
                        <th class="w-1/4 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_transactions)): ?>
                        <tr><td colspan="4" style="text-align: center; padding: 3rem; color: var(--text-light);">No transactions match your filters.</td></tr>
                    <?php else: foreach ($all_transactions as $trans): ?>
                        <tr>
                            <td class="text-sm text-gray-600"><?php echo date("d M Y, g:ia", strtotime($trans['transaction_date'])); ?></td>
                            <td class="font-medium text-gray-800"><?php echo htmlspecialchars($trans['description']); ?></td>
                            <td class="text-center">
                                <span class="status-badge <?php echo $trans['type'] == 'credit' ? 'completed' : 'rejected'; ?>">
                                    <?php echo ucfirst($trans['type']); ?>
                                </span>
                            </td>
                            <td class="font-semibold text-right <?php echo ($trans['type'] == 'credit' ? 'text-green-600' : 'text-red-600'); ?>">
                                <?php echo ($trans['type'] == 'credit' ? '+' : '-'); ?>
                                ₦<?php echo number_format($trans['amount'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <div class="card-footer" style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color);">
        <nav class="flex items-center justify-center gap-2">
            <?php
            // Build query string to preserve filters
            $query_params = ['search' => $filter_search, 'type' => $filter_type];
            ?>
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($query_params); ?>" class="pagination-link">&laquo;</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($query_params); ?>" class="pagination-link <?php if ($i == $page) echo 'active'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                 <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($query_params); ?>" class="pagination-link">&raquo;</a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

</div> <script src="js/dash.js"></script>
</body>
</html>
