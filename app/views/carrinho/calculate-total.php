<?php
require_once __DIR__ . '/../../../config/Database.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['items']) || !is_array($input['items'])) {
    echo json_encode(['total' => 0]);
    exit;
}

$total = 0;

try {
    $database = new Database();
    $conn = $database->getConnection();

    foreach ($input['items'] as $item) {
        $produtoId = (int) ($item['id'] ?? 0);
        $quantidade = (int) ($item['quantidade'] ?? 0);

        if ($produtoId <= 0 || $quantidade <= 0) {
            continue;
        }

        $sql = "SELECT preco
                FROM estoque
                WHERE produto_id = :produto_id
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':produto_id' => $produtoId
        ]);

        $preco = (float) $stmt->fetchColumn();
        $total += $preco * $quantidade;
    }

    echo json_encode(['total' => $total]);
} catch (Throwable $e) {
    echo json_encode([
        'total' => 0,
        'erro' => $e->getMessage()
    ]);
}