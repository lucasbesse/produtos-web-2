<?php
session_start();

require_once __DIR__ . '/../../../config/Database.php';

$usuarioId = $_SESSION['usuario_id'] ?? null;
$usuarioTipo = $_SESSION['usuario_tipo'] ?? null;

$erroPedido = '';
$sucessoPedido = '';
$pedidoCriadoNumero = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finalize_purchase') {
    if (!$usuarioId || $usuarioTipo !== 'cliente') {
        $erroPedido = 'Você precisa estar logado como cliente para finalizar a compra.';
    } else {
        $formaPagamento = trim($_POST['forma_pagamento'] ?? '');
        $cartPayload = $_POST['cart_payload'] ?? '';

        $formasPermitidas = ['pix', 'cartao', 'boleto'];

        if (!in_array($formaPagamento, $formasPermitidas, true)) {
            $erroPedido = 'Selecione uma forma de pagamento.';
        } else {
            $cartItems = json_decode($cartPayload, true);

            if (!is_array($cartItems) || empty($cartItems)) {
                $erroPedido = 'Seu carrinho está vazio.';
            } else {
                try {
                    $database = new Database();
                    $conn = $database->getConnection();

                    $conn->beginTransaction();

                    $sqlCliente = "SELECT id
                                   FROM cliente
                                   WHERE id = :id
                                   LIMIT 1";

                    $stmtCliente = $conn->prepare($sqlCliente);
                    $stmtCliente->execute([
                        ':id' => $usuarioId
                    ]);

                    $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

                    if (!$cliente) {
                        throw new Exception('Cliente não encontrado.');
                    }

                    $sqlPedido = "INSERT INTO pedido
                                    (data_pedido, data_entrega, situacao, cliente_id)
                                  VALUES
                                    (:data_pedido, :data_entrega, :situacao, :cliente_id)
                                  RETURNING numero";

                    $stmtPedido = $conn->prepare($sqlPedido);
                    $stmtPedido->execute([
                        ':data_pedido' => date('Y-m-d'),
                        ':data_entrega' => null,
                        ':situacao' => 'NOVO',
                        ':cliente_id' => $usuarioId
                    ]);

                    $pedidoNumero = (int) $stmtPedido->fetchColumn();

                    foreach ($cartItems as $item) {
                        $produtoId = (int) ($item['id'] ?? 0);
                        $quantidade = (int) ($item['quantidade'] ?? 0);

                        if ($produtoId <= 0 || $quantidade <= 0) {
                            throw new Exception('Item do carrinho inválido.');
                        }

                        $sqlProduto = "SELECT
                                            p.id,
                                            p.nome,
                                            e.quantidade,
                                            e.preco
                                       FROM produto p
                                       INNER JOIN estoque e ON e.produto_id = p.id
                                       WHERE p.id = :produto_id
                                       LIMIT 1";

                        $stmtProduto = $conn->prepare($sqlProduto);
                        $stmtProduto->execute([
                            ':produto_id' => $produtoId
                        ]);

                        $produtoBanco = $stmtProduto->fetch(PDO::FETCH_ASSOC);

                        if (!$produtoBanco) {
                            throw new Exception('Um dos produtos do carrinho não foi encontrado.');
                        }

                        $estoqueAtual = (int) $produtoBanco['quantidade'];
                        $precoAtual = (float) $produtoBanco['preco'];

                        if ($estoqueAtual < $quantidade) {
                            throw new Exception(
                                'O produto "' . $produtoBanco['nome'] . '" possui apenas ' . $estoqueAtual . ' unidade(s) disponível(is).'
                            );
                        }

                        $sqlItemPedido = "INSERT INTO item_pedido
                                            (pedido_numero, produto_id, quantidade, preco)
                                          VALUES
                                            (:pedido_numero, :produto_id, :quantidade, :preco)";

                        $stmtItemPedido = $conn->prepare($sqlItemPedido);
                        $stmtItemPedido->execute([
                            ':pedido_numero' => $pedidoNumero,
                            ':produto_id' => $produtoId,
                            ':quantidade' => $quantidade,
                            ':preco' => $precoAtual
                        ]);

                        $sqlAtualizaEstoque = "UPDATE estoque
                                               SET quantidade = quantidade - :quantidade
                                               WHERE produto_id = :produto_id";

                        $stmtAtualizaEstoque = $conn->prepare($sqlAtualizaEstoque);
                        $stmtAtualizaEstoque->execute([
                            ':quantidade' => $quantidade,
                            ':produto_id' => $produtoId
                        ]);
                    }

                    $conn->commit();

                    $pedidoCriadoNumero = $pedidoNumero;
                    $sucessoPedido = 'Pedido realizado com sucesso! Número do pedido: #' . $pedidoNumero . '.';
                } catch (Throwable $e) {
                    if (isset($conn) && $conn->inTransaction()) {
                        $conn->rollBack();
                    }

                    $erroPedido = $e->getMessage() ?: 'Erro ao finalizar o pedido.';
                }
            }
        }
    }
}
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

        <?php if ($erroPedido !== ''): ?>
            <div class="message error-message">
                <?php echo htmlspecialchars($erroPedido); ?>
            </div>
        <?php endif; ?>

        <?php if ($sucessoPedido !== ''): ?>
            <div class="message success-message">
                <?php echo htmlspecialchars($sucessoPedido); ?>
            </div>
        <?php endif; ?>

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

                <div class="payment-section">
                    <h3>Forma de pagamento</h3>

                    <div class="payment-options">
                        <button type="button" class="payment-card" data-payment="pix">
                            <span class="payment-icon">⚡</span>
                            <span>Pix</span>
                        </button>

                        <button type="button" class="payment-card" data-payment="cartao">
                            <span class="payment-icon">💳</span>
                            <span>Cartão</span>
                        </button>

                        <button type="button" class="payment-card" data-payment="boleto">
                            <span class="payment-icon">🧾</span>
                            <span>Boleto</span>
                        </button>
                    </div>
                </div>

                <form method="POST" id="checkoutForm">
                    <input type="hidden" name="action" value="finalize_purchase">
                    <input type="hidden" name="forma_pagamento" id="selectedPaymentInput">
                    <input type="hidden" name="cart_payload" id="cartPayloadInput">

                    <button type="submit" class="btn-submit" id="checkoutButton" disabled>
                        Finalizar compra
                    </button>
                </form>

                <button type="button" class="btn-secondary" id="clearCartButton">
                    Limpar carrinho
                </button>
            </aside>
        </div>
    </main>

    <div class="modal-overlay" id="loginRequiredModal">
        <div class="confirm-modal">
            <button type="button" class="modal-close" id="closeLoginRequiredModal">×</button>

            <h2>Login necessário</h2>
            <p>Você precisa estar logado como cliente para continuar a compra.</p>

            <div class="confirm-actions">
                <button type="button" class="btn-secondary" id="cancelLoginRequiredButton">Fechar</button>
                <a href="../login/login.php" class="btn-submit modal-login-link">Continuar</a>
            </div>
        </div>
    </div>

    <script>
        const CURRENT_USER_ID = <?php echo json_encode($_SESSION['usuario_id'] ?? null); ?>;
        const CURRENT_USER_TYPE = <?php echo json_encode($_SESSION['usuario_tipo'] ?? null); ?>;
        const SHOULD_CLEAR_CART = <?php echo $sucessoPedido !== '' ? 'true' : 'false'; ?>;

        const STORAGE_KEY = CURRENT_USER_ID && CURRENT_USER_TYPE
            ? `cartItems_${CURRENT_USER_TYPE}_${CURRENT_USER_ID}`
            : 'cartItems_guest';

        const cartList = document.getElementById('cartList');
        const emptyCart = document.getElementById('emptyCart');
        const summaryItems = document.getElementById('summaryItems');
        const summarySubtotal = document.getElementById('summarySubtotal');
        const summaryTotal = document.getElementById('summaryTotal');
        const checkoutButton = document.getElementById('checkoutButton');
        const clearCartButton = document.getElementById('clearCartButton');
        const checkoutForm = document.getElementById('checkoutForm');
        const selectedPaymentInput = document.getElementById('selectedPaymentInput');
        const cartPayloadInput = document.getElementById('cartPayloadInput');
        const paymentCards = document.querySelectorAll('.payment-card');

        const loginRequiredModal = document.getElementById('loginRequiredModal');
        const closeLoginRequiredModal = document.getElementById('closeLoginRequiredModal');
        const cancelLoginRequiredButton = document.getElementById('cancelLoginRequiredButton');

        let selectedPaymentMethod = '';

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

        function updateCheckoutState() {
            const items = getCartItems();
            checkoutButton.disabled = !items.length || !selectedPaymentMethod;
        }

        function renderCart() {
            const items = getCartItems();

            if (!items.length) {
                cartList.style.display = 'none';
                emptyCart.style.display = 'block';
                clearCartButton.disabled = true;

                summaryItems.textContent = '0';
                summarySubtotal.textContent = 'R$ 0,00';
                summaryTotal.textContent = 'R$ 0,00';

                updateCheckoutState();
                return;
            }

            cartList.style.display = 'flex';
            emptyCart.style.display = 'none';
            clearCartButton.disabled = false;

            cartList.innerHTML = items.map(createCartCard).join('');

            const summary = calculateSummary(items);

            summaryItems.textContent = String(summary.totalItems);
            summarySubtotal.textContent = formatPrice(summary.totalValue);
            summaryTotal.textContent = formatPrice(summary.totalValue);

            updateCheckoutState();
        }

        function openLoginRequiredModal() {
            loginRequiredModal.classList.add('active');
            document.body.classList.add('modal-open');
        }

        function closeLoginRequiredModalFn() {
            loginRequiredModal.classList.remove('active');
            document.body.classList.remove('modal-open');
        }

        paymentCards.forEach((card) => {
            card.addEventListener('click', function () {
                paymentCards.forEach((item) => item.classList.remove('selected'));
                this.classList.add('selected');

                selectedPaymentMethod = this.dataset.payment;
                selectedPaymentInput.value = selectedPaymentMethod;

                updateCheckoutState();
            });
        });

        checkoutForm.addEventListener('submit', function (event) {
            const items = getCartItems();

            if (!items.length) {
                event.preventDefault();
                return;
            }

            if (!selectedPaymentMethod) {
                event.preventDefault();
                return;
            }

            if (!CURRENT_USER_ID || CURRENT_USER_TYPE !== 'cliente') {
                event.preventDefault();
                openLoginRequiredModal();
                return;
            }

            selectedPaymentInput.value = selectedPaymentMethod;
            cartPayloadInput.value = JSON.stringify(items);
        });

        clearCartButton.addEventListener('click', function () {
            const confirmed = confirm('Deseja limpar todo o carrinho?');

            if (confirmed) {
                clearCart();
            }
        });

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

        if (SHOULD_CLEAR_CART) {
            localStorage.removeItem(STORAGE_KEY);
        }

        renderCart();
    </script>
</body>
</html>