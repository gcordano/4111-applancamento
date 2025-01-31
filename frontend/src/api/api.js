import axios from 'axios';

// Pegando o valor da API do .env ou usando um padrão caso não esteja definido
const apiUrl = process.env.REACT_APP_API_URL;

// Criando a instância do Axios com baseURL configurável
const api = axios.create({
  baseURL: apiUrl,
});

export default api;
