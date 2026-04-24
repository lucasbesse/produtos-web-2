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