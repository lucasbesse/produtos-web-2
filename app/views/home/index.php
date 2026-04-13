<?php
$produtos = [
    [
        'id' => 1,
        'nome' => 'Notebook Dell',
        'descricao' => 'Notebook para estudos e trabalho.',
        'preco' => '3500,00'
    ],
    [
        'id' => 2,
        'nome' => 'Mouse Gamer',
        'descricao' => 'Mouse com sensor de alta precisão.',
        'preco' => '120,00'
    ],
    [
        'id' => 3,
        'nome' => 'Teclado Mecânico',
        'descricao' => 'Teclado com iluminação RGB.',
        'preco' => '250,00'
    ],
    [
        'id' => 4,
        'nome' => 'Monitor 24"',
        'descricao' => 'Monitor Full HD para escritório.',
        'preco' => '900,00'
    ],
    [
        'id' => 5,
        'nome' => 'Headset',
        'descricao' => 'Headset confortável para chamadas.',
        'preco' => '180,00'
    ],
    [
        'id' => 6,
        'nome' => 'Webcam HD',
        'descricao' => 'Webcam ideal para reuniões online.',
        'preco' => '210,00'
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja Virtual</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>

    <header class="topbar">
        <div class="topbar-content">
            <div class="logo">Minha Loja</div>

            <div class="search-area">
                <form class="search-form" onsubmit="return false;">
                    <input
                        type="text"
                        id="searchInput"
                        name="pesquisa"
                        placeholder="Pesquisar produtos..."
                    >
                    <button type="button">Buscar</button>
                </form>
            </div>

            <div class="auth-buttons">
                <a href="../login/login.php" class="btn-login">Login</a>
                <a href="../cadastro-cliente/cadastro-cliente.php" class="btn-register">Cadastre-se</a>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="hero-box">
            <h1>Bem-vindo à nossa loja virtual</h1>
            <p>Encontre produtos com praticidade, pesquise facilmente e faça seu cadastro para comprar.</p>
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

        function createProductCard(produto) {
            return `
                <div class="product-card">
                    <div class="product-image">
                        Imagem do produto
                    </div>

                    <div class="product-info">
                        <h3>${produto.nome}</h3>
                        <p>${produto.descricao}</p>
                        <div class="product-price">R$ ${produto.preco}</div>
                        <a href="produto.php?id=${produto.id}">Ver produto</a>
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
                    produto.descricao.toLowerCase().includes(termo) ||
                    String(produto.id).includes(termo)
                );
            });
        }

        function handleSearch() {
            const termo = searchInput.value;
            const produtosFiltrados = filterProducts(termo);
            renderProducts(produtosFiltrados);
        }

        searchInput.addEventListener('input', handleSearch);

        renderProducts(produtos);
    </script>

</body>
</html>