import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { fetchFiles } from "../services/apiService";
import Header from "./Header";
import { 
  Card,
  CardContent,
  CardActions,
  Button,
  Typography,
  Box,
  Stack,
  CircularProgress,
  IconButton
} from "@mui/material";
import { Brightness4, Brightness7 } from "@mui/icons-material";

const createUrl = process.env.REACT_APP_CREATE_URL || "/create";
const editUrl = process.env.REACT_APP_EDIT_URL || "/edit";

function FileList() {
  /// Recupera o tema salvo no localStorage (padrão: true - dark mode)
  const [isDarkMode, setIsDarkMode] = useState(() => {
    const saved = localStorage.getItem("isDarkMode");
    return saved ? JSON.parse(saved) : true; // true para o modo escuro por padrão
  });

  // Função para alternar o tema
  const handleToggleTheme = () => {
    setIsDarkMode((prevMode) => {
      const newMode = !prevMode;
      localStorage.setItem("isDarkMode", JSON.stringify(newMode)); // Salva a escolha do tema
      return newMode;
    });
  };

  const [files, setFiles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  // Buscar arquivos ao montar o componente
  useEffect(() => {
    const token = localStorage.getItem("token");
    if (!token) {
      setTimeout(() => navigate("/"), 500);
      return;
    }

    fetchFiles()
      .then((data) => {
        if (Array.isArray(data)) {
          setFiles(data);
        } else {
          setFiles([]);
        }
        setLoading(false);
      })
      .catch((error) => {
        setError("Erro ao carregar arquivos. Tente novamente.");
        setLoading(false);
      });
  }, [navigate]);

  // Funções de manipulação de arquivos
  const handleDelete = async (id) => {
    if (!window.confirm("Deletar este arquivo?")) {
      return;
    }
    try {
      const response = await fetch(
        `${process.env.REACT_APP_API_URL}/src/routes/movimentacao.php?route=delete&id=${id}`,
        {
          method: "PUT",
          headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
        }
      );

      if (!response.ok) {
        throw new Error(`Erro na requisição: ${response.status}`);
      }

      const result = await response.json();
      if (result.message === "Arquivo inativado com sucesso!") {
        setFiles((prevFiles) => prevFiles.filter((file) => file.guid !== id));
        alert(result.message);
      } else {
        alert("Erro ao inativar arquivo no banco.");
      }
    } catch (error) {
      alert("Erro ao inativar arquivo. Tente novamente.");
    }
  };

  const handleGenerateXML = async (id, fileName) => {
    try {
      const response = await fetch(
        `${process.env.REACT_APP_API_URL}/src/routes/movimentacao.php?route=generateXML&id=${id}`,
        {
          method: "GET",
          headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
        }
      );

      if (!response.ok) {
        throw new Error("Erro ao gerar XML");
      }

      const result = await response.blob();
      const link = document.createElement("a");
      link.href = URL.createObjectURL(result);
      link.download = fileName; // Usa o nome formatado enviado pelo backend
      link.click();
    } catch (error) {
      alert("Erro ao gerar XML: " + error.message);
    }
  };

  const handleTransmit = async (id) => {
    try {
      const response = await fetch(
        `${process.env.REACT_APP_API_URL}/src/routes/movimentacao.php?route=transmit`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
          body: JSON.stringify({ id }),
        }
      );
      const result = await response.json();
      if (result.transmitido) {
        alert("Transmissão finalizada com sucesso!");
        // Atualiza o estado para desabilitar o botão de transmissão para este arquivo
        setFiles((prevFiles) =>
          prevFiles.map((file) =>
            file.guid === id ? { ...file, transmitido: true } : file
          )
        );
      } else {
        alert("Erro na transmissão: " + result.message);
      }
    } catch (error) {
      alert("Erro ao transmitir o arquivo. Tente novamente.");
    }
  };

  // Ordena os arquivos com base na data extraída do nome (formato: 4111_YYYYMMDD.xml)
  const sortedFiles = files.sort((a, b) =>
    b.name.substr(5, 8).localeCompare(a.name.substr(5, 8))
  );

  return (
    <Box sx={isDarkMode ? darkStyles.container : lightStyles.container}>
      <Header showTitle={true} isDarkMode={isDarkMode} />

      <Stack direction="row" spacing={2} justifyContent="space-between" sx={{ mb: 2 }}>
        <Button
          sx={isDarkMode ? darkStyles.gradientButton : lightStyles.gradientButton}
          onClick={() => navigate(createUrl)}
        >
          Novo Arquivo
        </Button>

        {/* Botão de alternância de tema usando ícones do MUI */}
        <IconButton onClick={handleToggleTheme}>
          {isDarkMode ? <Brightness7 /> : <Brightness4 />}
        </IconButton>
      </Stack>

      {loading && (
        <Box sx={isDarkMode ? darkStyles.loadingContainer : lightStyles.loadingContainer}>
          <CircularProgress color="inherit" />
          <Typography variant="h6" sx={{ mt: 1 }}>
            Carregando arquivos...
          </Typography>
        </Box>
      )}

      {error && (
        <Box sx={isDarkMode ? darkStyles.errorContainer : lightStyles.errorContainer}>
          <Typography variant="h6" color="error">
            {error}
          </Typography>
        </Box>
      )}

      {!loading && !error && sortedFiles.length > 0 ? (
        <Box
          sx={{
            display: "grid",
            gridTemplateColumns: "repeat(3, 1fr)",
            gap: 2,
          }}
        >
          {sortedFiles.map((file) => (
            <Card
              key={file.guid}
              sx={isDarkMode ? darkStyles.card : lightStyles.card}
            >
              <CardContent>
                <Typography variant="h6" sx={{ fontSize: "1.2rem", fontWeight: "bold" }}>
                  {file.name}
                </Typography>
              </CardContent>
              <CardActions sx={{ justifyContent: "flex-end" }}>
                <Stack direction="row" spacing={1} sx={{ flexWrap: "nowrap", gap: 1 }}>
                  <Button
                    sx={isDarkMode ? darkStyles.gradientButton : lightStyles.gradientButton}
                    size="small"
                    onClick={() => navigate(`${editUrl}/${file.guid}`)}
                  >
                    Editar
                  </Button>
                  <Button
                    sx={isDarkMode ? darkStyles.gradientButton : lightStyles.gradientButton}
                    size="small"
                    onClick={() => handleGenerateXML(file.guid, file.name)}
                  >
                    XML
                  </Button>
                  <Button
                    sx={isDarkMode ? darkStyles.gradientButton : lightStyles.gradientButton}
                    size="small"
                    onClick={() => handleTransmit(file.guid)}
                    disabled={file.transmitido}
                  >
                    Transmitir
                  </Button>
                  <Button
                    sx={isDarkMode ? darkStyles.gradientButton : lightStyles.gradientButton}
                    size="small"
                    onClick={() => handleDelete(file.guid)}
                  >
                    Deletar
                  </Button>
                </Stack>
              </CardActions>
            </Card>
          ))}
        </Box>
      ) : (
        !loading &&
        !error && (
          <Typography
            variant="h6"
            align="center"
            sx={{
              color: isDarkMode ? "#FFFFFF" : "#000000",
              mt: 2,
            }}
          >
            Nenhum arquivo encontrado.
          </Typography>
        )
      )}
    </Box>
  );
}

// Estilos para o tema DARK
const darkStyles = {
  container: {
    backgroundColor: "#262626",
    color: "#FFFFFF",
    minHeight: "100vh",
    padding: "20px",
  },
  loadingContainer: {
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
    justifyContent: "center",
    marginTop: "20px",
  },
  errorContainer: {
    textAlign: "center",
    marginTop: "20px",
  },
  card: {
    backgroundColor: "#1C1C1C",
    color: "#FFFFFF",
    borderRadius: "8px",
    boxShadow: "0px 4px 6px rgba(0, 0, 0, 0.2)",
    display: "flex",
    flexDirection: "column",
    justifyContent: "space-between",
    padding: "10px",
    minWidth: "340px",
  },
  gradientButton: {
    background: "linear-gradient(to right, #A8C545, #B8D954)",
    color: "#FFFFFF",
    fontWeight: "bold",
    "&:hover": {
      background: "linear-gradient(to right, #A8C545, #B8D954)",
      filter: "brightness(1.05)",
    },
    "&.Mui-disabled": {
      opacity: 0.5,
      color: "#FFFFFF",
    },
  },
};

// Estilos para o tema WHITE
const lightStyles = {
  container: {
    backgroundColor: "#CCCCCC",
    color: "#000000",
    minHeight: "100vh",
    padding: "20px",
  },
  loadingContainer: {
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
    justifyContent: "center",
    marginTop: "20px",
  },
  errorContainer: {
    textAlign: "center",
    marginTop: "20px",
  },
  card: {
    backgroundColor: "#F2F2F2",
    color: "#000000",
    borderRadius: "8px",
    boxShadow: "0px 4px 6px rgba(0, 0, 0, 0.1)",
    display: "flex",
    flexDirection: "column",
    justifyContent: "space-between",
    padding: "10px",
    minWidth: "340px",
  },
  gradientButton: {
    background: "linear-gradient(to right, #A8C545, #B8D954)",
    color: "#FFFFFF",
    fontWeight: "bold",
    "&:hover": {
      background: "linear-gradient(to right, #A8C545, #B8D954)",
      filter: "brightness(1.05)",
    },
    "&.Mui-disabled": {
      opacity: 0.5,
      color: "#FFFFFF",
    },
  },
};

export default FileList;
