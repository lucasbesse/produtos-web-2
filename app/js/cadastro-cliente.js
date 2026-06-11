const telefoneInput = document.getElementById('telefone');
const cartaoInput = document.getElementById('cartao_credito');
const cepInput = document.getElementById('cep');
const ruaInput = document.getElementById('rua');
const bairroInput = document.getElementById('bairro');
const cidadeInput = document.getElementById('cidade');
const estadoInput = document.getElementById('estado');
const cepStatus = document.getElementById('cep-status');

function apenasNumeros(valor) {
    return valor.replace(/\D/g, '');
}

function aplicarMascaraTelefone(valor) {
    valor = apenasNumeros(valor).slice(0, 11);

    if (valor.length <= 10) {
        return valor
            .replace(/^(\d{2})(\d)/g, '($1) $2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    }

    return valor
        .replace(/^(\d{2})(\d)/g, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2');
}

function aplicarMascaraCartao(valor) {
    valor = apenasNumeros(valor).slice(0, 16);
    return valor.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
}

function aplicarMascaraCep(valor) {
    valor = apenasNumeros(valor).slice(0, 8);
    return valor.replace(/^(\d{5})(\d)/, '$1-$2');
}

telefoneInput.addEventListener('input', function () {
    this.value = aplicarMascaraTelefone(this.value);
});

cartaoInput.addEventListener('input', function () {
    this.value = aplicarMascaraCartao(this.value);
});

cepInput.addEventListener('input', function () {
    this.value = aplicarMascaraCep(this.value);
    cepStatus.textContent = '';
});

async function buscarCep() {
    const cepLimpo = apenasNumeros(cepInput.value);

    if (cepLimpo.length !== 8) {
        cepStatus.textContent = 'Digite um CEP válido.';
        return;
    }

    cepStatus.textContent = 'Buscando CEP...';

    try {
        const response = await fetch(`https://viacep.com.br/ws/${cepLimpo}/json/`);

        if (!response.ok) {
            throw new Error('CEP inválido');
        }

        const data = await response.json();

        if (data.erro) {
            cepStatus.textContent = 'CEP não encontrado.';
            return;
        }

        ruaInput.value = data.logradouro || '';
        bairroInput.value = data.bairro || '';
        cidadeInput.value = data.localidade || '';
        estadoInput.value = data.uf || '';

        cepStatus.textContent = 'Endereço preenchido automaticamente.';
    } catch (error) {
        cepStatus.textContent = 'Não foi possível consultar o CEP.';
    }
}

cepInput.addEventListener('blur', buscarCep);