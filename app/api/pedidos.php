<?php

#http://localhost:8080/produtos-web-2/app/api/pedidos.php?numero=1

require_once __DIR__ . '/../../config/Database.php';

header('Content-Type: application/json; charset=utf-8');

$numero = isset($_GET['numero']) ? (int) $_GET['numero'] : null;
$cliente = isset($_GET['cliente']) ? trim($_GET['cliente']) : null;

if (($numero === null || $numero <= 0) && ($cliente === null || $cliente === '')) {
    http_response_code(400);
    echo json_encode([
        'erro' => true,
        'mensagem' => 'Informe o número do pedido ou o nome do cliente.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $params = [];
    $where = [];

    if ($numero !== null && $numero > 0) {
        $where[] = 'p.numero = :numero';
        $params[':numero'] = $numero;
    }

    if ($cliente !== null && $cliente !== '') {
        $where[] = 'c.nome ILIKE :cliente';
        $params[':cliente'] = '%' . $cliente . '%';
    }

    $sql = "SELECT
                p.numero AS pedido_numero,
                p.data_pedido,
                p.data_entrega,
                p.situacao,
                c.id AS cliente_id,
                c.nome AS cliente_nome,
                ip.produto_id,
                ip.quantidade,
                ip.preco,
                pr.nome AS produto_nome,
                pr.descricao AS produto_descricao
            FROM pedido p
            INNER JOIN cliente c ON c.id = p.cliente_id
            INNER JOIN item_pedido ip ON ip.pedido_numero = p.numero
            INNER JOIN produto pr ON pr.id = ip.produto_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.numero DESC, pr.nome ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pedidos = [];

    foreach ($rows as $row) {
        $pedidoNumero = (int) $row['pedido_numero'];

        if (!isset($pedidos[$pedidoNumero])) {
            $pedidos[$pedidoNumero] = [
                'numero' => $pedidoNumero,
                'data_pedido' => $row['data_pedido'],
                'data_entrega' => $row['data_entrega'],
                'situacao' => $row['situacao'],
                'cliente' => [
                    'id' => (int) $row['cliente_id'],
                    'nome' => $row['cliente_nome']
                ],
                'itens' => []
            ];
        }

        $pedidos[$pedidoNumero]['itens'][] = [
            'produto_id' => (int) $row['produto_id'],
            'produto_nome' => $row['produto_nome'],
            'produto_descricao' => $row['produto_descricao'],
            'quantidade' => (int) $row['quantidade'],
            'preco' => (float) $row['preco'],
            'total_item' => (float) $row['preco'] * (int) $row['quantidade']
        ];
    }

    echo json_encode([
        'erro' => false,
        'quantidade_pedidos' => count($pedidos),
        'pedidos' => array_values($pedidos)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => true,
        'mensagem' => 'Erro interno ao consultar pedidos.',
        'detalhe' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}