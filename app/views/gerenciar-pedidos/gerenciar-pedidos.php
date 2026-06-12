<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/Database.php';

if (
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['usuario_tipo']) ||
    $_SESSION['usuario_tipo'] !== 'fornecedor'
) {
    session_unset();
    session_destroy();
    header('Location: ../login/login.php');
    exit;
}

function montarImagemProduto($foto): ?array
{
    if ($foto === null) {
        return null;
    }

    if (is_resource($foto)) {
        $foto = stream_get_contents($foto);
    }

    if ($foto === false || $foto === '') {
        return null;
    }

    $mime = 'image/jpeg';

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeDetectado = $finfo->buffer($foto);

        if ($mimeDetectado) {
            $mime = $mimeDetectado;
        }
    }

    return [
        'mime' => $mime,
        'base64' => base64_encode($foto)
    ];
}

function formatarMoedaBr(?float $valor): string
{
    if ($valor === null) {
        return 'Não definido';
    }

    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarDataBr(?string $data): string
{
    if (!$data) {
        return '-';
    }

    $timestamp = strtotime($data);
    if (!$timestamp) {
        return $data;
    }

    return date('d/m/Y', $timestamp);
}

$fornecedorId = (int) $_SESSION['usuario_id'];
$erro = '';
$sucesso = '';
$pedidosAgrupados = [];

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
        $pedidoNumero = (int) ($_POST['pedido_numero'] ?? 0);
        $novoStatus = trim($_POST['situacao'] ?? '');

        $statusPermitidos = ['NOVO', 'ENTREGUE', 'CANCELADO'];

        if ($pedidoNumero <= 0) {
            $erro = 'Pedido inválido.';
        } elseif (!in_array($novoStatus, $statusPermitidos, true)) {
            $erro = 'Status inválido.';
        } else {
            $sqlValidaPedido = "SELECT DISTINCT p.numero
                                FROM pedido p
                                INNER JOIN item_pedido ip ON ip.pedido_numero = p.numero
                                INNER JOIN produto pr ON pr.id = ip.produto_id
                                WHERE p.numero = :pedido_numero
                                  AND pr.fornecedor_id = :fornecedor_id
                                LIMIT 1";

            $stmtValidaPedido = $conn->prepare($sqlValidaPedido);
            $stmtValidaPedido->execute([
                ':pedido_numero' => $pedidoNumero,
                ':fornecedor_id' => $fornecedorId
            ]);

            $pedidoValido = $stmtValidaPedido->fetch(PDO::FETCH_ASSOC);

            if (!$pedidoValido) {
                $erro = 'Pedido não encontrado para este fornecedor.';
            } else {
                $dataEntrega = null;

                if ($novoStatus === 'ENTREGUE') {
                    $dataEntrega = date('Y-m-d');
                }

                $sqlUpdate = "UPDATE pedido
                              SET situacao = :situacao,
                                  data_entrega = :data_entrega
                              WHERE numero = :numero";

                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    ':situacao' => $novoStatus,
                    ':data_entrega' => $dataEntrega,
                    ':numero' => $pedidoNumero
                ]);

                $sucesso = 'Status do pedido atualizado com sucesso.';
            }
        }
    }

    $sql = "SELECT
                p.numero AS pedido_numero,
                p.data_pedido,
                p.data_entrega,
                p.situacao,
                c.nome AS cliente_nome,
                pr.id AS produto_id,
                pr.nome AS produto_nome,
                pr.descricao AS produto_descricao,
                pr.foto,
                ip.quantidade,
                ip.preco
            FROM pedido p
            INNER JOIN cliente c ON c.id = p.cliente_id
            INNER JOIN item_pedido ip ON ip.pedido_numero = p.numero
            INNER JOIN produto pr ON pr.id = ip.produto_id
            WHERE pr.fornecedor_id = :fornecedor_id
            ORDER BY p.numero DESC, pr.nome ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':fornecedor_id' => $fornecedorId
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $pedidoNumero = (int) $row['pedido_numero'];

        if (!isset($pedidosAgrupados[$pedidoNumero])) {
            $pedidosAgrupados[$pedidoNumero] = [
                'pedido_numero' => $pedidoNumero,
                'data_pedido' => $row['data_pedido'],
                'data_entrega' => $row['data_entrega'],
                'situacao' => $row['situacao'],
                'cliente_nome' => $row['cliente_nome'],
                'itens' => []
            ];
        }

        $imagem = montarImagemProduto($row['foto']);

        $pedidosAgrupados[$pedidoNumero]['itens'][] = [
            'produto_id' => (int) $row['produto_id'],
            'produto_nome' => $row['produto_nome'],
            'produto_descricao' => $row['produto_descricao'] ?? 'Sem descrição',
            'quantidade' => (int) $row['quantidade'],
            'preco' => (float) $row['preco'],
            'total_item' => (float) $row['preco'] * (int) $row['quantidade'],
            'imagem' => $imagem
        ];
    }
} catch (PDOException $e) {
    $erro = 'Erro ao carregar os pedidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pedidos - Loja Virtual</title>
    <link rel="stylesheet" href="./gerenciar-pedidos.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-content">
            <a href="../gerenciar-estoque/gerenciar-estoque.php" class="logo">Minha Loja</a>

            <div class="header-actions">
                <a href="../cadastro-produto/cadastro-produto.php" class="btn-primary">Cadastrar produto</a>
                <a href="../gerenciar-estoque/gerenciar-estoque.php" class="btn-back">Gerenciar estoque</a>
                <div class="user-menu-wrapper">
                    <button type="button" class="user-button" id="userMenuButton">
                        <span class="user-avatar">👤</span>
                    </button>

                    <div class="user-popup" id="userPopup">
                        <div class="user-popup-header">
                            <div class="user-popup-avatar">👤</div>

                            <div class="user-popup-info">
                                <strong><?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário'); ?></strong>
                                <span>
                                    <?php echo ($_SESSION['usuario_tipo'] ?? '') === 'fornecedor' ? 'Fornecedor' : 'Cliente'; ?>
                                </span>
                            </div>
                        </div>

                        <a href="../home/index.php?logout=1" class="logout-button">Sair</a>
                </div>
            </div>
            </div>
        </div>
    </header>

    <main class="page-content">
        <div class="page-header">
            <h1>Gerenciar pedidos</h1>
            <p>Visualize os pedidos recebidos e atualize o status.</p>
        </div>

        <?php if ($erro !== ''): ?>
            <div class="message error-message">
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso !== ''): ?>
            <div class="message success-message">
                <?php echo htmlspecialchars($sucesso); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($pedidosAgrupados)): ?>
            <div class="empty-state">
                <h2>Nenhum pedido recebido ainda.</h2>
                <p>Quando clientes comprarem seus produtos, os pedidos aparecerão aqui.</p>
            </div>
        <?php else: ?>
            <section class="orders-panel">
                <div class="orders-list">
                    <?php foreach ($pedidosAgrupados as $pedido): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-header-info">
                                    <h2>Pedido #<?php echo (int) $pedido['pedido_numero']; ?></h2>
                                    <div class="order-meta">
                                        <div><strong>Cliente:</strong> <?php echo htmlspecialchars($pedido['cliente_nome']); ?></div>
                                        <div><strong>Data do pedido:</strong> <?php echo htmlspecialchars(formatarDataBr($pedido['data_pedido'])); ?></div>
                                        <div><strong>Data de entrega:</strong> <?php echo htmlspecialchars(formatarDataBr($pedido['data_entrega'])); ?></div>
                                    </div>
                                </div>

                                <form method="POST" class="status-form">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="pedido_numero" value="<?php echo (int) $pedido['pedido_numero']; ?>">

                                    <label for="situacao_<?php echo (int) $pedido['pedido_numero']; ?>">Status do pedido</label>
                                    <select
                                        name="situacao"
                                        id="situacao_<?php echo (int) $pedido['pedido_numero']; ?>"
                                        class="status-select"
                                    >
                                        <option value="NOVO" <?php echo $pedido['situacao'] === 'NOVO' ? 'selected' : ''; ?>>NOVO</option>
                                        <option value="ENTREGUE" <?php echo $pedido['situacao'] === 'ENTREGUE' ? 'selected' : ''; ?>>ENTREGUE</option>
                                        <option value="CANCELADO" <?php echo $pedido['situacao'] === 'CANCELADO' ? 'selected' : ''; ?>>CANCELADO</option>
                                    </select>

                                    <button type="submit" class="btn-save-status">Salvar</button>
                                </form>
                            </div>

                            <div class="order-items">
                                <?php foreach ($pedido['itens'] as $item): ?>
                                    <a
                                        href="../detalhe-produto/detalhe-produto.php?id=<?php echo (int) $item['produto_id']; ?>"
                                        class="order-item-card"
                                    >
                                        <div class="product-left">
                                            <div class="product-image <?php echo $item['imagem'] ? 'has-image' : ''; ?>">
                                                <?php if ($item['imagem']): ?>
                                                    <img
                                                        src="data:<?php echo htmlspecialchars($item['imagem']['mime']); ?>;base64,<?php echo $item['imagem']['base64']; ?>"
                                                        alt="<?php echo htmlspecialchars($item['produto_nome']); ?>"
                                                    >
                                                <?php else: ?>
                                                    Imagem do produto
                                                <?php endif; ?>
                                            </div>

                                            <div class="product-main-info">
                                                <h3><?php echo htmlspecialchars($item['produto_nome']); ?></h3>
                                                <p><?php echo htmlspecialchars($item['produto_descricao']); ?></p>
                                            </div>
                                        </div>

                                        <div class="product-right">
                                            <div class="product-meta">
                                                <div><strong>Quantidade:</strong> <?php echo (int) $item['quantidade']; ?></div>
                                                <div><strong>Preço unitário:</strong> <?php echo htmlspecialchars(formatarMoedaBr($item['preco'])); ?></div>
                                                <div><strong>Total:</strong> <?php echo htmlspecialchars(formatarMoedaBr($item['total_item'])); ?></div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
<script src="../../js/gerenciar-pedido.js"></script>
</html>