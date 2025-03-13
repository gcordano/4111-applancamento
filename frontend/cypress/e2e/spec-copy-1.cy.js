/// <reference types="cypress" />

describe('Login Simulado com JWT e Acesso ao Home', () => {
  let jwt;

  beforeEach(() => {
    // Passo 1: Obter o JWT usando o fakeLogin
    cy.request({
      method: 'POST',
      url: 'https://bancarizador-api-hml.aticca.digital/authentication/fakeLogin',  // Endpoint para fakeLogin
      body: {
        id: 45,   // Substitua pelo ID desejado
        idCompany: 1  // Substitua pelo ID da empresa desejada
      }
    }).then((response) => {
      // Passo 2: Armazenar o JWT no ambiente do Cypress
      jwt = response.body.jwt;
      cy.wrap(jwt).as('jwt');  // Armazenar JWT como variável global
    });
  });

  it('Deve acessar a página de Home com o JWT', function() {
    // Passo 3: Usar o JWT para acessar diretamente a página /home
    cy.request({
      method: 'GET',
      url: 'https://admin-hml.aticca.digital/home/',  // URL de destino
      headers: {
        'Accept': 'application/json, text/plain, */*',
        'Accept-Encoding': 'gzip, deflate, br, zstd',
        'Accept-Language': 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
        'Cache-Control': 'no-store, no-cache, must-revalidate, no-store, max-age=0, no-cache',
        'Connection': 'keep-alive',
        'Cookie': `jwt=${this.jwt}; ci_session=s25vlk1nht1blb97dq308h4mk24r8qe8`,  // Enviar o JWT e o cookie de sessão
        'If-None-Match': '"wknrnqrpakeub"',  // Adicionar o cabeçalho If-None-Match
        'Referer': 'https://admin-hml.aticca.digital/',  // Adicionar o cabeçalho Referer
        'Authorization': `Bearer ${this.jwt}`, // Garantir que o JWT seja passado como Authorization também
        'Sec-Fetch-Dest': 'empty',
        'Sec-Fetch-Mode': 'cors',
        'Sec-Fetch-Site': 'same-site',
        'Sec-Fetch-User': '?1',
        'Upgrade-Insecure-Requests': '1',
        'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
        'Sec-CH-UA': '"Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
        'Sec-CH-UA-Mobile': '?0',
        'Sec-CH-UA-Platform': '"macOS"'  // Incluindo sec-ch-ua e outras propriedades
      }
    }).then((response) => {
      // Passo 4: Verificar se a resposta foi bem-sucedida
      expect(response.status).to.eq(200);  // Espera-se que a resposta tenha status 200
      cy.visit('https://admin-hml.aticca.digital/home/');  // Navegar até a página da home

      // Passo 5: Verificar se a página foi carregada corretamente (exemplo: verificar se há um texto de boas-vindas)
      cy.contains('Você está acessando o Painel Administrativo')  // Verifica se a página contém o texto "Você está acessando o Painel Administrativo"
        .should('be.visible');  // Garante que o texto esteja visível na página
    });
  });
});
