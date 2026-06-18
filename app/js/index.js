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

function truncateText(text, maxLength = 60) {
    if (!text) {
        return 'Sem descrição';
    }

    return text.length > maxLength
        ? text.slice(0, maxLength).trim() + '...'
        : text;
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
                
                <p>${escapeHtml(truncateText(produto.descricao, 150))}</p>
                <div class="product-price">${precoTexto}</div>
                <a href="../detalhe-produto/detalhe-produto.php?id=${produto.id}">Ver produto</a>
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

const cartBadge = document.getElementById('cartBadge');

const body = document.body;
const CURRENT_USER_ID = body.dataset.userId || null;
const CURRENT_USER_TYPE = body.dataset.userType || null;

function getCartStorageKey() {
    return CURRENT_USER_ID && CURRENT_USER_TYPE
        ? `cartItems_${CURRENT_USER_TYPE}_${CURRENT_USER_ID}`
        : 'cartItems_guest';
}

function mergeGuestCartToLoggedUserCart() {
    if (!CURRENT_USER_ID || CURRENT_USER_TYPE !== 'cliente') {
        return;
    }

    const guestKey = 'cartItems_guest';
    const userKey = getCartStorageKey();

    const guestRaw = localStorage.getItem(guestKey);
    if (!guestRaw) {
        return;
    }

    let guestItems = [];
    let userItems = [];

    try {
        guestItems = JSON.parse(guestRaw);
        if (!Array.isArray(guestItems)) {
            guestItems = [];
        }
    } catch (error) {
        guestItems = [];
    }

    try {
        const userRaw = localStorage.getItem(userKey);
        userItems = userRaw ? JSON.parse(userRaw) : [];
        if (!Array.isArray(userItems)) {
            userItems = [];
        }
    } catch (error) {
        userItems = [];
    }

    if (!guestItems.length) {
        localStorage.removeItem(guestKey);
        return;
    }

    const merged = [...userItems];

    guestItems.forEach((guestItem) => {
        const existingIndex = merged.findIndex((item) => item.id === guestItem.id);

        if (existingIndex >= 0) {
            const quantidadeAtual = Number(merged[existingIndex].quantidade || 0);
            const quantidadeGuest = Number(guestItem.quantidade || 0);
            const quantidadeDisponivel = Number(merged[existingIndex].quantidadeDisponivel || guestItem.quantidadeDisponivel || 0);

            let novaQuantidade = quantidadeAtual + quantidadeGuest;

            if (quantidadeDisponivel > 0 && novaQuantidade > quantidadeDisponivel) {
                novaQuantidade = quantidadeDisponivel;
            }

            merged[existingIndex].quantidade = novaQuantidade;
        } else {
            merged.push(guestItem);
        }
    });

    localStorage.setItem(userKey, JSON.stringify(merged));
    localStorage.removeItem(guestKey);
}

function updateCartBadge() {
    if (!cartBadge) {
        return;
    }

    const STORAGE_KEY = getCartStorageKey();
    const raw = localStorage.getItem(STORAGE_KEY);

    if (!raw) {
        cartBadge.style.display = 'none';
        return;
    }

    let items = [];

    try {
        items = JSON.parse(raw);
        if (!Array.isArray(items)) {
            items = [];
        }
    } catch (error) {
        items = [];
    }

    const totalItems = items.reduce((total, item) => {
        return total + Number(item.quantidade || 0);
    }, 0);

    if (totalItems > 0) {
        cartBadge.textContent = totalItems > 99 ? '99+' : String(totalItems);
        cartBadge.style.display = 'flex';
    } else {
        cartBadge.style.display = 'none';
    }
}

mergeGuestCartToLoggedUserCart();
renderProducts(produtos);
updateCartBadge();