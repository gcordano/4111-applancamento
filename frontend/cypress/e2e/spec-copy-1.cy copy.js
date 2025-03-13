/// <reference types="cypress" />

describe('Login Simulado com JWT', () => {
  let jwt;

  beforeEach(() => {
    // Passo 1: Simular o login e obter o JWT
    cy.request({
      method: 'POST',
      url: 'https://bancarizador-api-hml.aticca.digital/authentication/fakeLogin',  // Endpoint para fakeLogin
      body: {
        id: 45,
        idCompany: 1
      }
    }).then((response) => {
      // Passo 2: Armazenar o JWT no ambiente do Cypress
      jwt = response.body.jwt;
      cy.wrap(jwt).as('jwt');
    });
  });

  it('Deve acessar a página de Home com o JWT', function() {
    // Passo 3: Fazer a requisição para acessar a página de Home, passando o JWT
    cy.request({
      method: 'GET',
      url: 'https://admin-hml.aticca.digital/home/',  // URL de destino
      headers: {
        Authorization: `Bearer ${this.jwt}`  // Passando o JWT no cabeçalho
      }
    }).then((response) => {
      // Passo 4: Verificar se o login foi bem-sucedido
      expect(response.status).to.eq(200);  // Espera-se que a resposta tenha status 200
      cy.visit('https://admin-hml.aticca.digital/home/');  // Navegar até a página da home

      // Passo 5: Verificar se a página foi carregada corretamente (exemplo: verificar se há um texto de boas-vindas)
      cy.contains('Você está acessando o Painel Administrativo')  // Verifica se a página contém o texto "Bem-vindo"
        .should('be.visible');  // Garante que o texto "Bem-vindo" está visível na página
    });
  });
});
