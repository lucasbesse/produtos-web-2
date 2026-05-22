<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/Database.php';

if (
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['usuario_tipo']) ||
    $_SESSION['usuario_tipo'] !== 'cliente'
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

$clienteId = (int) $_SESSION['usuario_id'];
$compras = [];
$erro = '';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $sql = "SELECT
                ped.numero AS pedido_numero,
                ped.data_pedido,
                ped.situacao,
                ip.produto_id,
                ip.quantidade,
                ip.preco,
                pr.nome AS produto_nome,
                pr.descricao AS produto_descricao,
                pr.foto
            FROM pedido ped
            INNER JOIN item_pedido ip ON ip.pedido_numero = ped.numero
            INNER JOIN produto pr ON pr.id = ip.produto_id
            WHERE ped.cliente_id = :cliente_id
            ORDER BY ped.numero DESC, pr.nome ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':cliente_id' => $clienteId
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $imagem = montarImagemProduto($row['foto']);

        $compras[] = [
            'pedido_numero' => (int) $row['pedido_numero'],
            'data_pedido' => $row['data_pedido'],
            'situacao' => $row['situacao'],
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
    $erro = 'Erro ao carregar suas compras.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Compras - Loja Virtual</title>
    <link rel="stylesheet" href="./minhas-compras.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-content">
            <a href="../home/index.php" class="logo">Minha Loja</a>

            <div class="header-actions">
                <a href="../carrinho/carrinho.php" class="btn-primary">Carrinho</a>
                <a href="../home/index.php" class="btn-back">Voltar</a>
            </div>
        </div>
    </header>

    <main class="page-content">
        <div class="page-header">
            <h1>Minhas compras</h1>
            <p>Acompanhe os produtos que você já comprou.</p>
        </div>

        <?php if ($erro !== ''): ?>
            <div class="message error-message">
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <?php if (!$erro && empty($compras)): ?>
            <div class="empty-state">
                <h2>Você ainda não realizou nenhuma compra.</h2>
                <p>Quando fizer pedidos, eles aparecerão aqui.</p>
                <a href="../home/index.php" class="btn-primary empty-button">Ir para a loja</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($compras)): ?>
            <section class="products-panel">
                <div class="products-list">
                    <?php foreach ($compras as $compra): ?>
                        <a
                            href="../detalhe-produto/detalhe-produto.php?id=<?php echo (int) $compra['produto_id']; ?>"
                            class="product-card"
                        >
                            <div class="product-left">
                                <div class="product-image <?php echo $compra['imagem'] ? 'has-image' : ''; ?>">
                                    <?php if ($compra['imagem']): ?>
                                        <img
                                            src="data:<?php echo htmlspecialchars($compra['imagem']['mime']); ?>;base64,<?php echo $compra['imagem']['base64']; ?>"
                                            alt="<?php echo htmlspecialchars($compra['produto_nome']); ?>"
                                        >
                                    <?php else: ?>
                                        Imagem do produto
                                    <?php endif; ?>
                                </div>

                                <div class="product-main-info">
                                    <h3><?php echo htmlspecialchars($compra['produto_nome']); ?></h3>
                                    <p><?php echo htmlspecialchars($compra['produto_descricao']); ?></p>

                                    <div class="purchase-info">
                                        <div><strong>Pedido:</strong> #<?php echo (int) $compra['pedido_numero']; ?></div>
                                        <div><strong>Data:</strong> <?php echo htmlspecialchars(formatarDataBr($compra['data_pedido'])); ?></div>
                                        <div>
                                            <strong>Status:</strong>
                                            <span class="status-badge">
                                                <?php echo htmlspecialchars($compra['situacao']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="product-right">
                                <div class="product-meta">
                                    <div><strong>Quantidade:</strong> <?php echo (int) $compra['quantidade']; ?></div>
                                    <div><strong>Preço unitário:</strong> <?php echo htmlspecialchars(formatarMoedaBr($compra['preco'])); ?></div>
                                    <div><strong>Total do item:</strong> <?php echo htmlspecialchars(formatarMoedaBr($compra['total_item'])); ?></div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>