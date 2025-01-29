import React, { useEffect, useState } from "react";
import axios from "axios";
import Header from "./Header";
import { Card, CardContent, CardActions, Button, Typography, Grid, Stack } from "@mui/material";

function FileList() {
  const [files, setFiles] = useState([]);

  useEffect(() => {
    const token = localStorage.getItem("token");

    if (!token) {
      alert("Você precisa estar logado para acessar esta página.");
      window.location.href = "/";
      return;
    }

    const fetchFiles = async () => {
      try {
        const response = await axios.get("http://localhost:8000/files.php", {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });
        setFiles(response.data);
      } catch (error) {
        console.error("Erro ao buscar arquivos:", error);
        alert("Erro ao carregar arquivos. Faça login novamente.");
        window.location.href = "/";
      }
    };

    fetchFiles();
  }, []);

  const handleLogout = () => {
    localStorage.removeItem("token");
    alert("Sessão encerrada com sucesso!");
    window.location.href = "/";
  };

  const handleDownload = (id) => {
    window.open(`http://localhost:8000/files.php?download=1&id=${id}`);
  };

  const handleCreateNewFile = () => {
    window.location.href = "/create";
  };

  const handleDelete = async (id) => {
    const confirm = window.confirm("Tem certeza que deseja excluir este arquivo?");
    if (confirm) {
      try {
        const token = localStorage.getItem("token");
        await axios.delete("http://localhost:8000/files.php", {
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
          data: { id },
        });
        setFiles(files.filter((file) => file.id !== id));
        alert("Arquivo deletado com sucesso!");
      } catch (error) {
        console.error("Erro ao deletar arquivo:", error);
        alert("Não foi possível deletar o arquivo.");
      }
    }
  };

  const handleEdit = (id) => {
    window.location.href = `/edit/${id}`;
  };

  const handleTransmit = async (id) => {
    try {
      const token = localStorage.getItem("token");
      const response = await axios.post("http://localhost:8000/transmit.php", { id }, {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });

      if (response.status === 200) {
        alert(`Arquivo ${id} transmitido com sucesso!`);
      } else {
        alert(`Erro ao transmitir o arquivo ${id}.`);
      }
    } catch (error) {
      console.error("Erro ao transmitir arquivo:", error);
      alert("Falha ao transmitir o arquivo.");
    }
  };

  return (
    <div style={styles.container}>
      <Header />
      <div style={styles.headerContainer}>
        <Typography variant="h4" sx={{ textAlign: "center", color: "#FFFFFF", marginBottom: 2 }}>
          Listagem 4111
        </Typography>
      </div>

      <Stack direction="row" spacing={2} justifyContent="space-between" sx={{ marginBottom: 2 }}>
        <Button variant="contained" color="success" onClick={handleCreateNewFile}>
          Lançar Novo Arquivo
        </Button>
        <Button variant="contained" color="error" onClick={handleLogout}>
          Logout
        </Button>
      </Stack>

      <Grid container spacing={2}>
        {files.map((file) => (
          <Grid item xs={12} sm={6} md={4} key={file.id}>
            <Card sx={{ backgroundColor: "#444B52", color: "#FFFFFF" }}>
              <CardContent>
                <Typography variant="h6">{file.name}</Typography>
              </CardContent>
              <CardActions>
                <Stack direction="row" spacing={1}>
                  <Button variant="contained" color="primary" size="small" onClick={() => handleDownload(file.id)}>
                    Gerar XML
                  </Button>
                  <Button variant="contained" color="warning" size="small" onClick={() => handleEdit(file.id)}>
                    Editar
                  </Button>
                  <Button variant="contained" color="error" size="small" onClick={() => handleDelete(file.id)}>
                    Deletar
                  </Button>
                  <Button variant="contained" color="secondary" size="small" onClick={() => handleTransmit(file.id)}>
                    Transmitir
                  </Button>
                </Stack>
              </CardActions>
            </Card>
          </Grid>
        ))}
      </Grid>
    </div>
  );
}

const styles = {
  container: {
    backgroundColor: "#32373C",
    color: "#FFFFFF",
    minHeight: "100vh",
    padding: "20px",
  },
  headerContainer: {
    marginBottom: "20px",
  },
};

export default FileList;
