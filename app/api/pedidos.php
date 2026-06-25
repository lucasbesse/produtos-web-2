<?php

require_once __DIR__ . '/../../config/Database.php';

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getRouteSegments(): array
{
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';

    if ($pathInfo !== '') {
        $path = trim($pathInfo, '/');
    } else {
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $scriptBase = basename($scriptName);

        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($requestPath, $scriptDir)) {
            $requestPath = substr($requestPath, strlen($scriptDir));
        }

        $requestPath = ltrim($requestPath, '/');

        if (str_starts_with($requestPath, $scriptBase)) {
            $requestPath = substr($requestPath, strlen($scriptBase));
        }

        $requestPath = ltrim($requestPath, '/');

        if (str_starts_with($requestPath, 'pedidos/')) {
            $requestPath = substr($requestPath, strlen('pedidos/'));
        } elseif ($requestPath === 'pedidos') {
            $requestPath = '';
        }

        $path = trim($requestPath, '/');
    }

    if ($path === '') {
        return [];
    }

    return array_values(array_filter(explode('/', $path), fn ($segment) => $segment !== ''));
}

$segments = getRouteSegments();

/*
Rotas aceitas:

1) Buscar por número do pedido
   /app/api/pedidos.php/1
   ou, com rewrite:
   /app/api/pedidos/1

2) Buscar por nome do cliente
   /app/api/pedidos.php/cliente/Maria
   ou, com rewrite:
   /app/api/pedidos/cliente/Maria
*/

if (empty($segments)) {
    jsonResponse([
        'erro' => true,
        'mensagem' => 'Informe a rota corretamente.',
        'exemplos' => [
            '/app/api/pedidos.php/1',
            '/app/api/pedidos.php/cliente/Maria'
        ]
    ], 400);
}

$modoBusca = null;
$numero = null;
$cliente = null;

if (count($segments) === 1 && ctype_digit($segments[0])) {
    $modoBusca = 'numero';
    $numero = (int) $segments[0];
} elseif (
    count($segments) >= 2 &&
    strtolower($segments[0]) === 'cliente' &&
    trim(urldecode($segments[1])) !== ''
) {
    $modoBusca = 'cliente';
    $cliente = trim(urldecode($segments[1]));
} else {
    jsonResponse([
        'erro' => true,
        'mensagem' => 'Rota inválida.',
        'exemplos' => [
            '/app/api/pedidos.php/1',
            '/app/api/pedidos.php/cliente/Maria'
        ]
    ], 400);
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $params = [];
    $where = [];

    if ($modoBusca === 'numero') {
        $where[] = 'p.numero = :numero';
        $params[':numero'] = $numero;
    }

    if ($modoBusca === 'cliente') {
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

    $pedidos = array_values($pedidos);

    if ($modoBusca === 'numero') {
        if (empty($pedidos)) {
            jsonResponse([
                'erro' => true,
                'mensagem' => 'Pedido não encontrado.'
            ], 404);
        }

        jsonResponse([
            'erro' => false,
            'pedido' => $pedidos[0]
        ]);
    }

    jsonResponse([
        'erro' => false,
        'quantidade_pedidos' => count($pedidos),
        'pedidos' => $pedidos
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'erro' => true,
        'mensagem' => 'Erro interno ao consultar pedidos.',
        'detalhe' => $e->getMessage()
    ], 500);
}