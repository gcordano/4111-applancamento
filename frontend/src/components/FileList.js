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
  CircularProgress
} from "@mui/material";

const createUrl = process.env.REACT_APP_CREATE_URL || "/create";
const editUrl = process.env.REACT_APP_EDIT_URL || "/edit";

function FileList() {
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
    <Box sx={styles.container}>
      <Header showTitle={true} />

      {/* Botão para Criar Novo Arquivo */}
      <Stack direction="row" spacing={2} justifyContent="space-between" sx={{ mb: 2 }}>
        <Button variant="contained" color="success" onClick={() => navigate(createUrl)}>
          Novo Arquivo
        </Button>
      </Stack>

      {/* Indicador de carregamento */}
      {loading && (
        <Box sx={styles.loadingContainer}>
          <CircularProgress color="inherit" />
          <Typography variant="h6">Carregando arquivos...</Typography>
        </Box>
      )}

      {/* Mensagem de erro */}
      {error && (
        <Box sx={styles.errorContainer}>
          <Typography variant="h6" color="error">
            {error}
          </Typography>
        </Box>
      )}

      {/* Listagem de arquivos em layout de grade */}
      {!loading && !error && sortedFiles.length > 0 ? (
        <Box
          sx={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(280px, 1fr))",
            gap: 2,
          }}
        >
          {sortedFiles.map((file) => (
            <Card key={file.guid} sx={styles.card}>
              <CardContent>
                <Typography variant="h6" sx={{ fontSize: "1rem" }}>
                  {file.name}
                </Typography>
              </CardContent>
              <CardActions>
                <Stack direction="row" spacing={1} sx={{ flexWrap: "wrap", gap: 1 }}>
                  <Button
                    variant="contained"
                    color="primary"
                    size="small"
                    onClick={() => navigate(`${editUrl}/${file.guid}`)}
                  >
                    Editar
                  </Button>
                  <Button
                    variant="contained"
                    color="info"
                    size="small"
                    onClick={() => handleGenerateXML(file.guid, file.name)}
                  >
                    Gerar XML
                  </Button>
                  <Button
                    variant="contained"
                    color="secondary"
                    size="small"
                    onClick={() => handleTransmit(file.guid)}
                    disabled={file.transmitido}
                  >
                    Transmitir
                  </Button>
                  <Button
                    variant="contained"
                    color="error"
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
          <Typography variant="h6" color="white" align="center">
            Nenhum arquivo encontrado.
          </Typography>
        )
      )}
    </Box>
  );
}

const styles = {
  container: {
    backgroundColor: "#32373C",
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
    backgroundColor: "#444B52",
    color: "#FFFFFF",
    borderRadius: "8px",
    boxShadow: "0px 4px 6px rgba(0, 0, 0, 0.2)",
    display: "flex",
    flexDirection: "column",
    justifyContent: "space-between",
    padding: "10px",
  },
};

export default FileList;
