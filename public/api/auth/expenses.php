<?php
// GET  api/auth/expenses.php?month=YYYY-MM     — lấy danh sách chi tiêu trong tháng
// POST api/auth/expenses.php                   — thêm khoản chi tiêu mới
// DELETE api/auth/expenses.php?id=N            — xóa khoản chi tiêu
require_once dirname(__DIR__, 3) . '/lib/shop_helpers.php';

try {
    $userId = getCurrentUserId();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
        $stmt = $pdo->prepare('
            SELECT id, category, amount, description, expense_date, created_at
            FROM personal_expenses
            WHERE user_id = ? AND DATE_FORMAT(expense_date, "%Y-%m") = ?
            ORDER BY expense_date DESC, id DESC
        ');
        $stmt->execute([$userId, $month]);
        $rows = $stmt->fetchAll();

        // Tổng theo danh mục
        $totals = [];
        $grand  = 0;
        foreach ($rows as $r) {
            $totals[$r['category']] = ($totals[$r['category']] ?? 0) + (float)$r['amount'];
            $grand += (float)$r['amount'];
        }

        apiRespond(true, "OK", [
            "month"   => $month,
            "items"   => $rows,
            "totals"  => $totals,
            "total"   => $grand,
        ]);

    } elseif ($method === 'POST') {
        $input = readJsonBody();
        $categories = ['food','transport','entertainment','shopping','utilities','health','education','other'];
        $cat  = in_array($input['category'] ?? '', $categories, true) ? $input['category'] : 'other';
        $amt  = toPositiveNumber($input['amount'] ?? null);
        $desc = mb_substr(trim($input['description'] ?? ''), 0, 255);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['expense_date'] ?? '') ? $input['expense_date'] : date('Y-m-d');

        if (!$amt) throw new ApiException("Số tiền phải lớn hơn 0.", 400);

        $stmt = $pdo->prepare('INSERT INTO personal_expenses (user_id, category, amount, description, expense_date) VALUES (?,?,?,?,?)');
        $stmt->execute([$userId, $cat, $amt, $desc, $date]);
        $newId = (int)$pdo->lastInsertId();

        apiRespond(true, "Đã thêm khoản chi tiêu.", ["id" => $newId], 201);

    } elseif ($method === 'DELETE') {
        $id = toPositiveInt($_GET['id'] ?? null);
        if (!$id) throw new ApiException("Thiếu id.", 400);
        $stmt = $pdo->prepare('DELETE FROM personal_expenses WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        if ($stmt->rowCount() === 0) throw new ApiException("Không tìm thấy khoản chi tiêu.", 404);
        apiRespond(true, "Đã xóa khoản chi tiêu.");

    } else {
        apiRespond(false, "Method Not Allowed", null, 405);
    }

} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), null, $e->getCode());
} catch (Throwable $e) {
    error_log('expenses: ' . $e->getMessage());
    apiRespond(false, "Lỗi server.", null, 500);
}
