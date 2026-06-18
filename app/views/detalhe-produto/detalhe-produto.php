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

$usuarioId = $_SESSION['usuario_id'] ?? null;
$usuarioTipo = $_SESSION['usuario_tipo'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Produto - Loja Virtual</title>
    <link rel="stylesheet" href="./detalhe-produto.css">
    <style>
        body.modal-open {
            overflow: hidden;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background-color: rgba(0,0,0,0.35);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1000;
        }

        .modal-overlay.active {
            display: flex;
        }

        .confirm-modal {
            width: 100%;
            max-width: 420px;
            background-color: #fff;
            border-radius: 16px;
            padding: 24px;
            position: relative;
            box-shadow: 0 12px 30px rgba(0,0,0,0.16);
        }

        .modal-close {
            position: absolute;
            top: 10px;
            right: 12px;
            border: none;
            background: transparent;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            color: #666;
        }

        .confirm-modal h2 {
            font-size: 24px;
            margin-bottom: 12px;
        }

        .confirm-modal p {
            color: #666;
            line-height: 1.5;
            margin-bottom: 22px;
        }

        .confirm-actions {
            display: flex;
            gap: 10px;
        }

        .btn-secondary,
        .modal-login-link {
            flex: 1;
            border: none;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-secondary {
            background-color: #f2f2f2;
            color: #333;
        }

        .modal-login-link {
            background-color: #3483fa;
            color: #fff;
        }

        @media (max-width: 700px) {
            .confirm-actions {
                flex-direction: column;
            }
        }
    </style>
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

    <div class="modal-overlay" id="loginRequiredModal">
        <div class="confirm-modal">
            <button type="button" class="modal-close" id="closeLoginRequiredModal">×</button>

            <h2>Login necessário</h2>
            <p>Produto adicionado ao carrinho temporário. Faça login como cliente para continuar a compra.</p>

            <div class="confirm-actions">
                <button type="button" class="btn-secondary" id="cancelLoginRequiredButton">Fechar</button>
                <a href="../login/login.php" class="modal-login-link">Continuar</a>
            </div>
        </div>
    </div>

    <script>
        const CURRENT_USER_ID = <?php echo json_encode($usuarioId); ?>;
        const CURRENT_USER_TYPE = <?php echo json_encode($usuarioTipo); ?>;

        const currentProduct = {
            id: <?php echo (int) $produto['id']; ?>,
            nome: <?php echo json_encode($produto['nome']); ?>,
            descricao: <?php echo json_encode($produto['descricao'] ?? 'Sem descrição'); ?>,
            preco: <?php echo json_encode($produto['preco'] !== null ? (float) $produto['preco'] : 0); ?>,
            quantidadeDisponivel: <?php echo (int) $quantidadeDisponivel; ?>,
            imagemBase64: <?php echo json_encode($imagemProduto['base64'] ?? null); ?>,
            imagemMime: <?php echo json_encode($imagemProduto['mime'] ?? null); ?>
        };

        const loginRequiredModal = document.getElementById('loginRequiredModal');
        const closeLoginRequiredModal = document.getElementById('closeLoginRequiredModal');
        const cancelLoginRequiredButton = document.getElementById('cancelLoginRequiredButton');

        function getCartStorageKey() {
            return CURRENT_USER_ID && CURRENT_USER_TYPE
                ? `cartItems_${CURRENT_USER_TYPE}_${CURRENT_USER_ID}`
                : 'cartItems_guest';
        }

        function getCartItemsByKey(storageKey) {
            const raw = localStorage.getItem(storageKey);

            if (!raw) {
                return [];
            }

            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        function saveCartItemsByKey(storageKey, items) {
            localStorage.setItem(storageKey, JSON.stringify(items));
        }

        function openLoginRequiredModal() {
            loginRequiredModal.classList.add('active');
            document.body.classList.add('modal-open');
        }

        function closeLoginRequiredModalFn() {
            loginRequiredModal.classList.remove('active');
            document.body.classList.remove('modal-open');
        }

        function adicionarOuAtualizarItemNoCarrinho(storageKey, product) {
            const items = getCartItemsByKey(storageKey);
            const existingIndex = items.findIndex((item) => item.id === product.id);

            if (existingIndex >= 0) {
                const currentQuantity = Number(items[existingIndex].quantidade || 0);

                if (currentQuantity >= product.quantidadeDisponivel) {
                    alert(`Quantidade máxima disponível: ${product.quantidadeDisponivel}`);
                    return false;
                }

                items[existingIndex].quantidade = currentQuantity + 1;
            } else {
                items.push({
                    ...product,
                    quantidade: 1
                });
            }

            saveCartItemsByKey(storageKey, items);
            return true;
        }

        function adicionarAoCarrinho() {
            const storageKey = getCartStorageKey();
            const success = adicionarOuAtualizarItemNoCarrinho(storageKey, currentProduct);

            if (!success) {
                return;
            }

            if (CURRENT_USER_ID && CURRENT_USER_TYPE === 'cliente') {
                window.location.href = '../carrinho/carrinho.php';
                return;
            }

            openLoginRequiredModal();
        }

        closeLoginRequiredModal.addEventListener('click', closeLoginRequiredModalFn);
        cancelLoginRequiredButton.addEventListener('click', closeLoginRequiredModalFn);

        loginRequiredModal.addEventListener('click', function (event) {
            if (event.target === loginRequiredModal) {
                closeLoginRequiredModalFn();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeLoginRequiredModalFn();
            }
        });
    </script>
</body>
</html>