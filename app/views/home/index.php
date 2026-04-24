<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/Database.php';

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    session_unset();
    session_destroy();
    header('Location: ./index.php');
    exit;
}

function normalizarImagemProduto($foto): ?array
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

$usuarioLogado = isset($_SESSION['usuario_id']);
$usuarioNome = $_SESSION['usuario_nome'] ?? '';
$usuarioTipo = $_SESSION['usuario_tipo'] ?? '';

$produtos = [];

try {
    $database = new Database();
    $conn = $database->getConnection();

    $sql = "SELECT
                p.id,
                p.nome,
                p.descricao,
                p.foto,
                e.preco,
                e.quantidade
            FROM produto p
            LEFT JOIN estoque e ON e.produto_id = p.id
            ORDER BY p.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $imagem = normalizarImagemProduto($row['foto']);

        $produtos[] = [
            'id' => (int) $row['id'],
            'nome' => $row['nome'],
            'descricao' => $row['descricao'] ?? 'Sem descrição',
            'preco' => $row['preco'] !== null ? number_format((float) $row['preco'], 2, ',', '.') : null,
            'quantidade' => $row['quantidade'] !== null ? (int) $row['quantidade'] : 0,
            'tem_imagem' => $imagem !== null,
            'imagem_base64' => $imagem['base64'] ?? null,
            'imagem_mime' => $imagem['mime'] ?? null
        ];
    }
} catch (PDOException $e) {
    $produtos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Loja Virtual</title>
    <link rel="stylesheet" href="./index.css">
</head>
<body>

    <header class="topbar">
        <div class="topbar-content">
            <a href="./index.php" class="logo">Minha Loja</a>

            <div class="search-area">
                <form class="search-form" onsubmit="return false;">
                    <input
                        type="text"
                        id="searchInput"
                        name="pesquisa"
                        placeholder="Pesquisar produtos..."
                    >
                    <button type="button" id="searchButton">Buscar</button>
                </form>
            </div>

            <div class="header-right">
                <?php if ($usuarioLogado): ?>
                    <nav class="main-nav">
                        <?php if ($usuarioTipo === 'cliente'): ?>
                            <a href="#" onclick="return false;">
                                <span class="menu-icon">🛒</span>
                                <span>Carrinho</span>
                            </a>

                            <a href="#" onclick="return false;">
                                <span class="menu-icon">📦</span>
                                <span>Minhas compras</span>
                            </a>
                        <?php elseif ($usuarioTipo === 'fornecedor'): ?>
                            <a href="../cadastro-produto/cadastro-produto.php">
                                <span class="menu-icon">➕</span>
                                <span>Cadastrar produto</span>
                            </a>

                            <a href="../gerenciar-estoque/gerenciar-estoque.php">
                                <span class="menu-icon">📦</span>
                                <span>Gerenciar estoque</span>
                            </a>
                        <?php endif; ?>
                    </nav>

                    <div class="user-menu-wrapper">
                        <button type="button" class="user-button" id="userMenuButton">
                            <span class="user-avatar">👤</span>
                        </button>

                        <div class="user-popup" id="userPopup">
                            <div class="user-popup-header">
                                <div class="user-popup-avatar">👤</div>

                                <div class="user-popup-info">
                                    <strong><?php echo htmlspecialchars($usuarioNome); ?></strong>
                                    <span>
                                        <?php echo $usuarioTipo === 'cliente' ? 'Cliente' : 'Fornecedor'; ?>
                                    </span>
                                </div>
                            </div>

                            <a href="./index.php?logout=1" class="logout-button">Sair</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="auth-buttons">
                        <a href="../login/login.php" class="btn-login">Login</a>
                        <a href="../cadastro-cliente/cadastro-cliente.php" class="btn-register">Cadastre-se</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="hero-box">
            <?php if ($usuarioLogado): ?>
                <h1>Bem-vindo, <?php echo htmlspecialchars($usuarioNome); ?>!</h1>

                <?php if ($usuarioTipo === 'cliente'): ?>
                    <p>Veja os produtos disponíveis e acompanhe suas compras.</p>
                <?php else: ?>
                    <p>Gerencie seus produtos e estoques pela sua conta de fornecedor.</p>
                <?php endif; ?>
            <?php else: ?>
                <h1>Bem-vindo à nossa loja virtual</h1>
                <p>Encontre produtos com praticidade, pesquise facilmente e faça seu cadastro para comprar.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="products-section">
        <h2>Produtos em destaque</h2>
        <div id="productsContainer" class="products-grid"></div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 - Loja Virtual</p>
    </footer>

    <script>
        const produtos = <?php echo json_encode($produtos, JSON_UNESCAPED_UNICODE); ?>;
        const productsContainer = document.getElementById('productsContainer');
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');
        const userMenuButton = document.getElementById('userMenuButton');
        const userPopup = document.getElementById('userPopup');

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function buildProductImage(produto) {
            if (produto.tem_imagem && produto.imagem_base64 && produto.imagem_mime) {
                return `
                    <img
                        src="data:${produto.imagem_mime};base64,${produto.imagem_base64}"
                        alt="${escapeHtml(produto.nome)}"
                    >
                `;
            }

            return `Imagem do produto`;
        }

        function createProductCard(produto) {
            const precoTexto = produto.preco ? `R$ ${produto.preco}` : 'Preço não definido';
            const imageClass = produto.tem_imagem ? 'product-image has-image' : 'product-image';

            return `
                <div class="product-card">
                    <div class="${imageClass}">
                        ${buildProductImage(produto)}
                    </div>

                    <div class="product-info">
                        <div class="s-between">
                            <h3>${escapeHtml(produto.nome)}</h3>
                            ${produto.quantidade == 0 ? '<span class="status-badge status-unavailable">Indisponível</span>' : ''}
                        </div>                        
                        
                        <p>${escapeHtml(produto.descricao ?? 'Sem descrição')}</p>
                        <div class="product-price">${precoTexto}</div>
                        <a href="#" onclick="return false;">Ver produto</a>
                    </div>
                </div>
            `;
        }

        function renderProducts(listaProdutos) {
            if (!listaProdutos.length) {
                productsContainer.className = '';
                productsContainer.innerHTML = `
                    <div class="empty-message">
                        Nenhum produto encontrado.
                    </div>
                `;
                return;
            }

            productsContainer.className = 'products-grid';
            productsContainer.innerHTML = listaProdutos.map(createProductCard).join('');
        }

        function filterProducts(searchTerm) {
            const termo = searchTerm.trim().toLowerCase();

            if (!termo) {
                return produtos;
            }

            return produtos.filter((produto) => {
                return (
                    produto.nome.toLowerCase().includes(termo) ||
                    String(produto.id).includes(termo) ||
                    (produto.descricao && produto.descricao.toLowerCase().includes(termo))
                );
            });
        }

        function handleSearch() {
            const termo = searchInput.value;
            const produtosFiltrados = filterProducts(termo);
            renderProducts(produtosFiltrados);
        }

        searchInput.addEventListener('input', handleSearch);
        searchButton.addEventListener('click', handleSearch);

        if (userMenuButton && userPopup) {
            userMenuButton.addEventListener('click', function (event) {
                event.stopPropagation();
                userPopup.classList.toggle('active');
            });

            document.addEventListener('click', function (event) {
                if (!userPopup.contains(event.target) && !userMenuButton.contains(event.target)) {
                    userPopup.classList.remove('active');
                }
            });
        }

        renderProducts(produtos);
    </script>

</body>
</html>