const precoInput = document.getElementById('preco');

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

precoInput.addEventListener('input', function () {
    this.value = aplicarMascaraPreco(this.value);
});