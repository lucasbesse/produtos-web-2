<?php
session_start();

require_once __DIR__ . '/../../../config/Database.php';

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
        return 'Preço não definido';
    }

    return 'R$ ' . number_format($valor, 2, ',', '.');
}

$produtoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$produto = null;
$erro = '';

if ($produtoId <= 0) {
    $erro = 'Produto inválido.';
} else {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $sql = "SELECT
                    p.id,
                    p.nome,
                    p.descricao,
                    p.foto,
                    f.nome AS fornecedor_nome,
                    e.preco,
                    e.quantidade
                FROM produto p
                INNER JOIN fornecedor f ON f.id = p.fornecedor_id
                LEFT JOIN estoque e ON e.produto_id = p.id
                WHERE p.id = :id
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $produtoId
        ]);

        $produto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produto) {
            $erro = 'Produto não encontrado.';
        }
    } catch (PDOException $e) {
        $erro = 'Erro ao carregar o produto.';
    }
}

$imagemProduto = $produto ? montarImagemProduto($produto['foto']) : null;
$quantidadeDisponivel = $produto && $produto['quantidade'] !== null ? (int) $produto['quantidade'] : 0;
$disponivel = $quantidadeDisponivel > 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Produto - Loja Virtual</title>
    <link rel="stylesheet" href="./detalhe-produto.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-content">
            <a href="../home/index.php" class="logo">Minha Loja</a>

            <div class="header-actions">
                <a href="../home/index.php" class="btn-back">Voltar para a loja</a>
            </div>
        </div>
    </header>

    <main class="page-content">
        <?php if ($erro !== ''): ?>
            <div class="message error-message">
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php elseif ($produto): ?>
            <div class="breadcrumb">
                <a href="../home/index.php">Home</a>
                <span>/</span>
                <span><?php echo htmlspecialchars($produto['nome']); ?></span>
            </div>

            <section class="product-detail-card">
                <div class="product-image-column">
                    <div class="product-image-box <?php echo $imagemProduto ? 'has-image' : ''; ?>">
                        <?php if ($imagemProduto): ?>
                            <img
                                src="data:<?php echo htmlspecialchars($imagemProduto['mime']); ?>;base64,<?php echo $imagemProduto['base64']; ?>"
                                alt="<?php echo htmlspecialchars($produto['nome']); ?>"
                            >
                        <?php else: ?>
                            <div class="product-image-placeholder">Imagem do produto</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="product-info-column">
                    <div class="product-status-row">
                        <?php if ($disponivel): ?>
                            <span class="status available">Disponível</span>
                        <?php else: ?>
                            <span class="status unavailable">Indisponível</span>
                        <?php endif; ?>
                    </div>

                    <h1 class="product-title"><?php echo htmlspecialchars($produto['nome']); ?></h1>

                    <div class="product-price">
                        <?php echo formatarMoedaBr($produto['preco'] !== null ? (float) $produto['preco'] : null); ?>
                    </div>

                    <div class="product-meta">
                        <div><strong>Fornecedor:</strong> <?php echo htmlspecialchars($produto['fornecedor_nome']); ?></div>
                        <div><strong>Quantidade disponível:</strong> <?php echo $quantidadeDisponivel; ?></div>
                    </div>

                    <div class="buy-box">
                        <button
                            type="button"
                            class="btn-add-cart"
                            <?php echo !$disponivel ? 'disabled' : ''; ?>
                            onclick="adicionarAoCarrinho()"
                        >
                            <span class="cart-icon">🛒</span>
                            <span>Adicionar ao carrinho</span>
                        </button>

                        <?php if (!$disponivel): ?>
                            <p class="stock-warning">Este produto está sem estoque no momento.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="description-card">
                <h2>Descrição do produto</h2>

                <p>
                    <?php echo nl2br(htmlspecialchars($produto['descricao'] ?? 'Sem descrição cadastrada.')); ?>
                </p>
            </section>
        <?php endif; ?>
    </main>

    <script>
        function mostrarAvisoCarrinho() {
            alert('A funcionalidade de carrinho será implementada na próxima etapa.');
        }
    </script>

    <script>
        const currentProduct = {
            id: <?php echo (int) $produto['id']; ?>,
            nome: <?php echo json_encode($produto['nome']); ?>,
            descricao: <?php echo json_encode($produto['descricao'] ?? 'Sem descrição'); ?>,
            preco: <?php echo json_encode($produto['preco'] !== null ? (float) $produto['preco'] : 0); ?>,
            quantidadeDisponivel: <?php echo (int) $quantidadeDisponivel; ?>,
            imagemBase64: <?php echo json_encode($imagemProduto['base64'] ?? null); ?>,
            imagemMime: <?php echo json_encode($imagemProduto['mime'] ?? null); ?>
        };

        function adicionarAoCarrinho() {
            const STORAGE_KEY = 'cartItems';
            const raw = localStorage.getItem(STORAGE_KEY);
            let items = [];

            if (raw) {
                try {
                    items = JSON.parse(raw);
                    if (!Array.isArray(items)) {
                        items = [];
                    }
                } catch (error) {
                    items = [];
                }
            }

            const existingIndex = items.findIndex((item) => item.id === currentProduct.id);

            if (existingIndex >= 0) {
                const currentQuantity = Number(items[existingIndex].quantidade || 0);

                if (currentQuantity >= currentProduct.quantidadeDisponivel) {
                    alert(`Quantidade máxima disponível: ${currentProduct.quantidadeDisponivel}`);
                    return;
                }

                items[existingIndex].quantidade = currentQuantity + 1;
            } else {
                items.push({
                    ...currentProduct,
                    quantidade: 1
                });
            }

            localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
            window.location.href = '../carrinho/carrinho.php';
        }
    </script>
</body>
</html>