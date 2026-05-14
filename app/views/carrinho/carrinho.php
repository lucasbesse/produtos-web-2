<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho - Loja Virtual</title>
    <link rel="stylesheet" href="./carrinho.css">
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
        <div class="page-header">
            <div>
                <h1>Meu carrinho</h1>
                <p>Confira os produtos adicionados antes de finalizar a compra.</p>
            </div>
        </div>

        <div class="content-grid">
            <section class="cart-panel">
                <div id="cartList" class="cart-list"></div>

                <div id="emptyCart" class="empty-state" style="display: none;">
                    <h2>Seu carrinho está vazio.</h2>
                    <p>Adicione produtos para continuar.</p>
                    <a href="../home/index.php" class="btn-primary empty-button">Voltar para a loja</a>
                </div>
            </section>

            <aside class="summary-panel">
                <h2>Resumo da compra</h2>

                <div class="summary-row">
                    <span>Itens</span>
                    <strong id="summaryItems">0</strong>
                </div>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <strong id="summarySubtotal">R$ 0,00</strong>
                </div>

                <div class="summary-total">
                    <span>Total</span>
                    <strong id="summaryTotal">R$ 0,00</strong>
                </div>

                <button type="button" class="btn-submit" id="checkoutButton" onclick="alert('O fechamento do pedido será implementado na próxima etapa.')">
                    Finalizar compra
                </button>

                <button type="button" class="btn-secondary" id="clearCartButton">
                    Limpar carrinho
                </button>
            </aside>
        </div>
    </main>

    <script>
        const STORAGE_KEY = 'cartItems';

        const cartList = document.getElementById('cartList');
        const emptyCart = document.getElementById('emptyCart');
        const summaryItems = document.getElementById('summaryItems');
        const summarySubtotal = document.getElementById('summarySubtotal');
        const summaryTotal = document.getElementById('summaryTotal');
        const checkoutButton = document.getElementById('checkoutButton');
        const clearCartButton = document.getElementById('clearCartButton');

        function getCartItems() {
            const raw = localStorage.getItem(STORAGE_KEY);

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

        function saveCartItems(items) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        }

        function formatPrice(value) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(Number(value || 0));
        }

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function truncateText(text, maxLength = 90) {
            if (!text) {
                return 'Sem descrição';
            }

            return text.length > maxLength
                ? text.slice(0, maxLength).trim() + '...'
                : text;
        }

        function buildImage(item) {
            if (item.imagemBase64 && item.imagemMime) {
                return `
                    <img
                        src="data:${item.imagemMime};base64,${item.imagemBase64}"
                        alt="${escapeHtml(item.nome)}"
                    >
                `;
            }

            return 'Imagem do produto';
        }

        function updateQuantity(productId, change) {
            const items = getCartItems();

            const updated = items.map((item) => {
                if (item.id !== productId) {
                    return item;
                }

                let nextQuantity = Number(item.quantidade || 0) + change;

                if (nextQuantity < 1) {
                    nextQuantity = 1;
                }

                if (item.quantidadeDisponivel && nextQuantity > Number(item.quantidadeDisponivel)) {
                    alert(`Quantidade máxima disponível: ${item.quantidadeDisponivel}`);
                    nextQuantity = Number(item.quantidadeDisponivel);
                }

                return {
                    ...item,
                    quantidade: nextQuantity
                };
            });

            saveCartItems(updated);
            renderCart();
        }

        function removeItem(productId) {
            const items = getCartItems().filter((item) => item.id !== productId);
            saveCartItems(items);
            renderCart();
        }

        function clearCart() {
            localStorage.removeItem(STORAGE_KEY);
            renderCart();
        }

        function calculateSummary(items) {
            let totalItems = 0;
            let totalValue = 0;

            items.forEach((item) => {
                const quantity = Number(item.quantidade || 0);
                const price = Number(item.preco || 0);

                totalItems += quantity;
                totalValue += quantity * price;
            });

            return {
                totalItems,
                totalValue
            };
        }

        function createCartCard(item) {
            const quantity = Number(item.quantidade || 0);
            const unitPrice = Number(item.preco || 0);
            const totalPrice = quantity * unitPrice;

            return `
                <div class="cart-card">
                    <div class="cart-left">
                        <div class="product-image ${item.imagemBase64 ? 'has-image' : ''}">
                            ${buildImage(item)}
                        </div>

                        <div class="product-main-info">
                            <h3>${escapeHtml(item.nome)}</h3>
                            <p>${escapeHtml(truncateText(item.descricao, 90))}</p>

                            <div class="product-meta">
                                <div><strong>Preço unitário:</strong> ${formatPrice(unitPrice)}</div>
                                <div><strong>Disponível:</strong> ${item.quantidadeDisponivel ?? 0}</div>
                            </div>
                        </div>
                    </div>

                    <div class="cart-right">
                        <div class="quantity-box">
                            <button type="button" class="qty-button" onclick="updateQuantity(${item.id}, -1)">−</button>
                            <span class="qty-value">${quantity}</span>
                            <button type="button" class="qty-button" onclick="updateQuantity(${item.id}, 1)">+</button>
                        </div>

                        <div class="total-box">
                            <span>Total</span>
                            <strong>${formatPrice(totalPrice)}</strong>
                        </div>

                        <button type="button" class="remove-button" onclick="removeItem(${item.id})">
                            Remover
                        </button>
                    </div>
                </div>
            `;
        }

        function renderCart() {
            const items = getCartItems();

            if (!items.length) {
                cartList.style.display = 'none';
                emptyCart.style.display = 'block';
                checkoutButton.disabled = true;
                clearCartButton.disabled = true;

                summaryItems.textContent = '0';
                summarySubtotal.textContent = 'R$ 0,00';
                summaryTotal.textContent = 'R$ 0,00';
                return;
            }

            cartList.style.display = 'flex';
            emptyCart.style.display = 'none';
            checkoutButton.disabled = false;
            clearCartButton.disabled = false;

            cartList.innerHTML = items.map(createCartCard).join('');

            const summary = calculateSummary(items);

            summaryItems.textContent = String(summary.totalItems);
            summarySubtotal.textContent = formatPrice(summary.totalValue);
            summaryTotal.textContent = formatPrice(summary.totalValue);
        }

        clearCartButton.addEventListener('click', function () {
            const confirmed = confirm('Deseja limpar todo o carrinho?');

            if (confirmed) {
                clearCart();
            }
        });

        renderCart();
    </script>
</body>
</html>