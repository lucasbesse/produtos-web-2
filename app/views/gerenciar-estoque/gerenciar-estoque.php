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

function formatarMoedaBr(?float $valor): string
{
    if ($valor === null) {
        return 'Não definido';
    }

    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function normalizarPrecoParaBanco(string $valor): ?float
{
    $valor = trim($valor);

    if ($valor === '') {
        return null;
    }

    $valor = str_replace(' ', '', $valor);

    $temVirgula = strpos($valor, ',') !== false;
    $temPonto = strpos($valor, '.') !== false;

    if ($temVirgula && $temPonto) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif ($temVirgula) {
        $valor = str_replace(',', '.', $valor);
    }

    if (!is_numeric($valor)) {
        return null;
    }

    return (float) $valor;
}

$erro = '';
$sucesso = '';
$fornecedorId = (int) $_SESSION['usuario_id'];

$modalEditData = null;

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_product') {
            $produtoId = (int) ($_POST['produto_id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $quantidade = trim($_POST['quantidade'] ?? '');
            $preco = trim($_POST['preco'] ?? '');
            $fotoBytes = null;

            $modalEditData = [
                'id' => $produtoId,
                'nome' => $nome,
                'descricao' => $descricao,
                'quantidade' => $quantidade,
                'preco' => $preco
            ];

            if ($produtoId <= 0) {
                $erro = 'Produto inválido.';
            } elseif ($nome === '') {
                $erro = 'Preencha o nome do produto.';
            } elseif ($quantidade === '' || $preco === '') {
                $erro = 'Preencha quantidade e preço.';
            } elseif (!ctype_digit($quantidade)) {
                $erro = 'A quantidade deve ser um número inteiro positivo.';
            } else {
                $precoNormalizado = normalizarPrecoParaBanco($preco);

                if ($precoNormalizado === null || $precoNormalizado < 0) {
                    $erro = 'Preço inválido.';
                }
            }

            if ($erro === '' && isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                    $erro = 'Erro ao fazer upload da imagem.';
                } else {
                    $tmpName = $_FILES['foto']['tmp_name'];
                    $mimeType = mime_content_type($tmpName);

                    $tiposPermitidos = [
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'image/gif'
                    ];

                    if (!in_array($mimeType, $tiposPermitidos, true)) {
                        $erro = 'Envie uma imagem JPG, PNG, WEBP ou GIF.';
                    } else {
                        $fotoBytes = file_get_contents($tmpName);

                        if ($fotoBytes === false) {
                            $erro = 'Não foi possível ler a imagem enviada.';
                        }
                    }
                }
            }

            if ($erro === '') {
                $sqlProduto = "SELECT id
                               FROM produto
                               WHERE id = :produto_id
                                 AND fornecedor_id = :fornecedor_id
                               LIMIT 1";

                $stmtProduto = $conn->prepare($sqlProduto);
                $stmtProduto->execute([
                    ':produto_id' => $produtoId,
                    ':fornecedor_id' => $fornecedorId
                ]);

                $produtoValido = $stmtProduto->fetch(PDO::FETCH_ASSOC);

                if (!$produtoValido) {
                    $erro = 'Produto não encontrado para este fornecedor.';
                } else {
                    $conn->beginTransaction();

                    if ($fotoBytes !== null) {
                        $sqlUpdateProduto = "UPDATE produto
                                             SET nome = :nome,
                                                 descricao = :descricao,
                                                 foto = :foto
                                             WHERE id = :produto_id
                                               AND fornecedor_id = :fornecedor_id";

                        $stmtUpdateProduto = $conn->prepare($sqlUpdateProduto);
                        $stmtUpdateProduto->bindValue(':nome', $nome, PDO::PARAM_STR);

                        if ($descricao !== '') {
                            $stmtUpdateProduto->bindValue(':descricao', $descricao, PDO::PARAM_STR);
                        } else {
                            $stmtUpdateProduto->bindValue(':descricao', null, PDO::PARAM_NULL);
                        }

                        $stmtUpdateProduto->bindValue(':foto', $fotoBytes, PDO::PARAM_LOB);
                        $stmtUpdateProduto->bindValue(':produto_id', $produtoId, PDO::PARAM_INT);
                        $stmtUpdateProduto->bindValue(':fornecedor_id', $fornecedorId, PDO::PARAM_INT);
                        $stmtUpdateProduto->execute();
                    } else {
                        $sqlUpdateProduto = "UPDATE produto
                                             SET nome = :nome,
                                                 descricao = :descricao
                                             WHERE id = :produto_id
                                               AND fornecedor_id = :fornecedor_id";

                        $stmtUpdateProduto = $conn->prepare($sqlUpdateProduto);
                        $stmtUpdateProduto->execute([
                            ':nome' => $nome,
                            ':descricao' => $descricao !== '' ? $descricao : null,
                            ':produto_id' => $produtoId,
                            ':fornecedor_id' => $fornecedorId
                        ]);
                    }

                    $sqlEstoque = "SELECT id
                                   FROM estoque
                                   WHERE produto_id = :produto_id
                                   LIMIT 1";

                    $stmtEstoque = $conn->prepare($sqlEstoque);
                    $stmtEstoque->execute([
                        ':produto_id' => $produtoId
                    ]);

                    $estoqueExistente = $stmtEstoque->fetch(PDO::FETCH_ASSOC);

                    if ($estoqueExistente) {
                        $sqlUpdateEstoque = "UPDATE estoque
                                             SET quantidade = :quantidade,
                                                 preco = :preco
                                             WHERE produto_id = :produto_id";

                        $stmtUpdateEstoque = $conn->prepare($sqlUpdateEstoque);
                        $stmtUpdateEstoque->execute([
                            ':quantidade' => (int) $quantidade,
                            ':preco' => $precoNormalizado,
                            ':produto_id' => $produtoId
                        ]);
                    } else {
                        $sqlInsertEstoque = "INSERT INTO estoque (quantidade, preco, produto_id)
                                             VALUES (:quantidade, :preco, :produto_id)";

                        $stmtInsertEstoque = $conn->prepare($sqlInsertEstoque);
                        $stmtInsertEstoque->execute([
                            ':quantidade' => (int) $quantidade,
                            ':preco' => $precoNormalizado,
                            ':produto_id' => $produtoId
                        ]);
                    }

                    $conn->commit();

                    header('Location: ./gerenciar-estoque.php?sucesso=editado');
                    exit;
                }
            }
        }

        if ($action === 'delete_product') {
            $produtoId = (int) ($_POST['produto_id'] ?? 0);

            if ($produtoId <= 0) {
                $erro = 'Produto inválido.';
            } else {
                $sqlProduto = "SELECT id, nome
                               FROM produto
                               WHERE id = :produto_id
                                 AND fornecedor_id = :fornecedor_id
                               LIMIT 1";

                $stmtProduto = $conn->prepare($sqlProduto);
                $stmtProduto->execute([
                    ':produto_id' => $produtoId,
                    ':fornecedor_id' => $fornecedorId
                ]);

                $produtoValido = $stmtProduto->fetch(PDO::FETCH_ASSOC);

                if (!$produtoValido) {
                    $erro = 'Produto não encontrado para este fornecedor.';
                } else {
                    $conn->beginTransaction();

                    $sqlDeleteEstoque = "DELETE FROM estoque WHERE produto_id = :produto_id";
                    $stmtDeleteEstoque = $conn->prepare($sqlDeleteEstoque);
                    $stmtDeleteEstoque->execute([
                        ':produto_id' => $produtoId
                    ]);

                    $sqlDeleteProduto = "DELETE FROM produto
                                         WHERE id = :produto_id
                                           AND fornecedor_id = :fornecedor_id";

                    $stmtDeleteProduto = $conn->prepare($sqlDeleteProduto);
                    $stmtDeleteProduto->execute([
                        ':produto_id' => $produtoId,
                        ':fornecedor_id' => $fornecedorId
                    ]);

                    $conn->commit();

                    header('Location: ./gerenciar-estoque.php?sucesso=excluido');
                    exit;
                }
            }
        }
    }

    $sqlProdutos = "SELECT
                        p.id,
                        p.nome,
                        p.descricao,
                        p.foto,
                        e.id AS estoque_id,
                        e.quantidade,
                        e.preco
                    FROM produto p
                    LEFT JOIN estoque e ON e.produto_id = p.id
                    WHERE p.fornecedor_id = :fornecedor_id
                    ORDER BY p.id DESC";

    $stmtProdutos = $conn->prepare($sqlProdutos);
    $stmtProdutos->execute([
        ':fornecedor_id' => $fornecedorId
    ]);

    $produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_GET['sucesso'])) {
        if ($_GET['sucesso'] === 'editado') {
            $sucesso = 'Produto e estoque salvos com sucesso.';
        }

        if ($_GET['sucesso'] === 'excluido') {
            $sucesso = 'Produto excluído com sucesso.';
        }
    }
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $erro = 'Erro ao carregar ou salvar os dados.';
    $produtos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Estoque - Loja Virtual</title>
    <link rel="stylesheet" href="./gerenciar-estoque.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-content">
            <a href="../home/index.php" class="logo">Minha Loja</a>

            <div class="header-actions">
                <a href="../cadastro-produto/cadastro-produto.php" class="btn-primary">Cadastrar produto</a>
                <a href="../home/index.php" class="btn-back">Voltar</a>
            </div>
        </div>
    </header>

    <main class="page-content">
        <div class="page-header">
            <div>
                <h1>Gerenciar produtos e estoque</h1>
                <p>Edite, exclua e atualize o estoque dos seus produtos.</p>
            </div>

            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Pesquisar produto por nome...">
            </div>
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

        <?php if (empty($produtos)): ?>
            <div class="empty-state">
                <h2>Você ainda não possui produtos cadastrados.</h2>
                <p>Cadastre um produto primeiro para depois gerenciar o estoque.</p>
                <a href="../cadastro-produto/cadastro-produto.php" class="btn-primary empty-button">Cadastrar produto</a>
            </div>
        <?php else: ?>
            <section class="products-panel">
                <div class="products-list" id="productsList">
                    <?php foreach ($produtos as $produto): ?>
                        <?php
                            $quantidadeAtual = $produto['quantidade'] !== null ? (int) $produto['quantidade'] : 0;
                            $statusTexto = $quantidadeAtual > 0 ? 'Disponível' : 'Indisponível';
                            $statusClasse = $quantidadeAtual > 0 ? 'status-available' : 'status-unavailable';
                            $precoCampo = $produto['preco'] !== null
                                ? number_format((float) $produto['preco'], 2, ',', '.')
                                : '';
                        ?>
                        <div class="product-card" data-product-name="<?php echo htmlspecialchars(mb_strtolower($produto['nome']), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="product-left">
                                <div class="product-image">
                                    <?php if (!empty($produto['foto'])): ?>
                                        Imagem
                                    <?php else: ?>
                                        Sem imagem
                                    <?php endif; ?>
                                </div>

                                <div class="product-main-info">
                                    <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
                                    <p><?php echo htmlspecialchars($produto['descricao'] ?? 'Sem descrição'); ?></p>
                                </div>
                            </div>

                            <div class="product-right">
                                <div class="product-meta">
                                    <div><strong>Preço:</strong> <?php echo formatarMoedaBr($produto['preco'] !== null ? (float) $produto['preco'] : null); ?></div>
                                    <div><strong>Quantidade:</strong> <?php echo $quantidadeAtual; ?></div>
                                    <div>
                                        <strong>Status:</strong>
                                        <span class="status-badge <?php echo $statusClasse; ?>">
                                            <?php echo $statusTexto; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="product-actions">
                                    <button
                                        type="button"
                                        class="edit-product-button"
                                        data-id="<?php echo (int) $produto['id']; ?>"
                                        data-nome="<?php echo htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-descricao="<?php echo htmlspecialchars($produto['descricao'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-quantidade="<?php echo $quantidadeAtual; ?>"
                                        data-preco="<?php echo htmlspecialchars($precoCampo, ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        Editar
                                    </button>

                                    <button
                                        type="button"
                                        class="delete-product-button"
                                        data-id="<?php echo (int) $produto['id']; ?>"
                                        data-nome="<?php echo htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="empty-search" id="emptySearch" style="display: none;">
                    Nenhum produto encontrado.
                </div>
            </section>
        <?php endif; ?>
    </main>

    <div class="modal-overlay" id="editModal">
        <div class="stock-modal">
            <button type="button" class="modal-close" id="closeEditModal">×</button>

            <h2>Editar produto</h2>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="produto_id" id="editProdutoId">

                <div class="form-group">
                    <label for="editNome">Nome do produto *</label>
                    <input type="text" id="editNome" name="nome" required>
                </div>

                <div class="form-group">
                    <label for="editDescricao">Descrição</label>
                    <textarea id="editDescricao" name="descricao" rows="4"></textarea>
                </div>

                <div class="form-group">
                    <label for="editQuantidade">Quantidade *</label>
                    <input type="number" id="editQuantidade" name="quantidade" min="0" required>
                </div>

                <div class="form-group">
                    <label for="editPreco">Preço *</label>
                    <input type="text" id="editPreco" name="preco" placeholder="0,00" required>
                </div>

                <div class="form-group">
                    <label for="editFoto">Imagem do produto</label>
                    <input
                        type="file"
                        id="editFoto"
                        name="foto"
                        accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"
                    >
                    <small class="field-help">Se selecionar uma nova imagem, ela substituirá a atual.</small>
                </div>

                <button type="submit" class="btn-submit">Salvar alterações</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="deleteModal">
        <div class="confirm-modal">
            <button type="button" class="modal-close" id="closeDeleteModal">×</button>

            <h2>Excluir produto</h2>
            <p id="deleteMessage">Tem certeza que deseja excluir este produto?</p>

            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="produto_id" id="deleteProdutoId">

                <div class="confirm-actions">
                    <button type="button" class="btn-secondary" id="cancelDeleteButton">Cancelar</button>
                    <button type="submit" class="btn-danger">Excluir</button>
                </div>
            </form>
        </div>
    </div>

    <script src="./gerenciar-estoque.js"></script>

    <script>
        <?php if ($modalEditData): ?>
            editProdutoId.value = <?php echo json_encode((string) $modalEditData['id']); ?>;
            editNome.value = <?php echo json_encode($modalEditData['nome']); ?>;
            editDescricao.value = <?php echo json_encode($modalEditData['descricao']); ?>;
            editQuantidade.value = <?php echo json_encode($modalEditData['quantidade']); ?>;
            editPreco.value = <?php echo json_encode($modalEditData['preco']); ?>;
            openModal(editModal);
        <?php endif; ?>
    </script>
</body>
</html>