const senha = document.getElementById('senha')
const mostrarSenha = document.getElementById('mostrar-senha')

mostrarSenha?.addEventListener('click', () => {
    const visivel = senha.type === 'password'
    senha.type = visivel ? 'text' : 'password'
    mostrarSenha.textContent = visivel ? 'Ocultar' : 'Mostrar'
    mostrarSenha.setAttribute('aria-pressed', String(visivel))
})

