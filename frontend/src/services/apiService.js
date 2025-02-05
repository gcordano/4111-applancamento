import axios from "axios";

const API_URL = process.env.REACT_APP_API_URL;

/**
 * 🔹 Obtém a lista de CNPJs e suas contas vinculadas.
 * @returns {Array} Lista de CNPJs e contas
 */
export const fetchCnpjsEContas = async () => {
  try {
    const response = await axios.get(`${API_URL}/src/routes/movimentacao.php?route=getCnpjsEContas`);
    return response.data;
  } catch (error) {
    console.error("Erro ao buscar CNPJs e contas:", error);
    return [];
  }
};

/**
 * 🔹 Obtém a lista de arquivos cadastrados
 * @returns {Array} Lista de arquivos
 */
export const fetchFiles = async () => {
  try {
    const response = await axios.get(`${API_URL}/src/routes/movimentacao.php?route=getFiles`);
    return response.data;
  } catch (error) {
    console.error("Erro ao buscar arquivos:", error);
    return [];
  }
};
