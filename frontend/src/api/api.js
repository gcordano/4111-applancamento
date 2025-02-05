import axios from "axios";

const API_URL = process.env.REACT_APP_API_URL;

/**
 * 🔹 Função para fazer login do usuário
 * @param {string} email - E-mail do usuário
 * @param {string} password - Senha do usuário
 * @returns {object} Resposta do servidor (token ou mensagem de erro)
 */
export const loginUser = async (email, password) => {
  try {
    const response = await axios.post(`${API_URL}/index.php?route=login`, {
      email,
      password,
    });

    // 🔹 Verifica se a resposta contém um token
    if (response.data?.token) {
      return response.data; // ✅ Retorna { message, token }
    } else {
      return { message: response.data?.message || "Erro ao fazer login" };
    }
  } catch (error) {
    console.error("Erro ao fazer login:", error.response?.data || error.message);
    return { message: "Erro ao conectar com o servidor." };
  }
};

/**
 * 🔹 Função para buscar todos os arquivos
 * @returns {object} Lista de arquivos
 */
export const fetchFiles = async () => {
  try {
    const response = await axios.get(`${API_URL}/index.php?route=files`);
    return response.data;
  } catch (error) {
    console.error("Erro ao buscar arquivos:", error);
    return { message: "Erro ao buscar arquivos." };
  }
};

/**
 * 🔹 Função para buscar um arquivo específico pelo ID
 * @param {number} id - ID do arquivo
 * @returns {object} Detalhes do arquivo
 */
export const fetchFileById = async (id) => {
  try {
    const response = await axios.get(`${API_URL}/index.php?route=file&id=${id}`);
    return response.data;
  } catch (error) {
    console.error("Erro ao buscar arquivo:", error);
    return { message: "Erro ao buscar arquivo." };
  }
};

// 🔹 Exportação correta
const api = {
  loginUser,
  fetchFiles,
  fetchFileById,
};

export default api;

