const body = document.body;

const searchInput = document.getElementById('searchInput');
const productCards = document.querySelectorAll('.product-card');
const emptySearch = document.getElementById('emptySearch');

const editModal = document.getElementById('editModal');
const closeEditModal = document.getElementById('closeEditModal');
const editButtons = document.querySelectorAll('.edit-product-button');

const editProdutoId = document.getElementById('editProdutoId');
const editNome = document.getElementById('editNome');
const editDescricao = document.getElementById('editDescricao');
const editQuantidade = document.getElementById('editQuantidade');
const editPreco = document.getElementById('editPreco');

const deleteModal = document.getElementById('deleteModal');
const closeDeleteModal = document.getElementById('closeDeleteModal');
const cancelDeleteButton = document.getElementById('cancelDeleteButton');
const deleteButtons = document.querySelectorAll('.delete-product-button');
const deleteProdutoId = document.getElementById('deleteProdutoId');
const deleteMessage = document.getElementById('deleteMessage');

function apenasNumeros(valor) {
    return valor.replace(/\D/g, '');
}

function aplicarMascaraPreco(valor) {
    valor = apenasNumeros(valor);

    if (!valor) {
        return '';
    }

    while (valor.length < 3) {
        valor = '0' + valor;
    }

    const centavos = valor.slice(-2);
    let inteiro = valor.slice(0, -2);

    inteiro = inteiro.replace(/^0+(?=\d)/, '');

    return `${inteiro},${centavos}`;
}

editPreco.addEventListener('input', function () {
    this.value = aplicarMascaraPreco(this.value);
});

function openModal(modal) {
    modal.classList.add('active');
    body.classList.add('modal-open');
}

function closeModal(modal) {
    modal.classList.remove('active');

    if (!editModal.classList.contains('active') && !deleteModal.classList.contains('active')) {
        body.classList.remove('modal-open');
    }
}

editButtons.forEach((button) => {
    button.addEventListener('click', function () {
        editProdutoId.value = this.dataset.id;
        editNome.value = this.dataset.nome;
        editDescricao.value = this.dataset.descricao;
        editQuantidade.value = this.dataset.quantidade;
        editPreco.value = this.dataset.preco;

        openModal(editModal);
    });
});

deleteButtons.forEach((button) => {
    button.addEventListener('click', function () {
        deleteProdutoId.value = this.dataset.id;
        deleteMessage.textContent = `Tem certeza que deseja excluir o produto "${this.dataset.nome}"?`;

        openModal(deleteModal);
    });
});

closeEditModal.addEventListener('click', () => closeModal(editModal));
closeDeleteModal.addEventListener('click', () => closeModal(deleteModal));
cancelDeleteButton.addEventListener('click', () => closeModal(deleteModal));

deleteModal.addEventListener('click', function (event) {
    if (event.target === deleteModal) {
        closeModal(deleteModal);
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeModal(editModal);
        closeModal(deleteModal);
    }
});

searchInput.addEventListener('input', function () {
    const termo = this.value.trim().toLowerCase();
    let visibleCount = 0;

    productCards.forEach((card) => {
        const productName = card.dataset.productName || '';
        const matches = productName.includes(termo);

        card.style.display = matches ? 'flex' : 'none';

        if (matches) {
            visibleCount++;
        }
    });

    emptySearch.style.display = visibleCount === 0 ? 'block' : 'none';
});