import React, { useState, useEffect } from "react";
import axios from "axios";
import Header from "./Header";
import { useParams } from "react-router-dom";
import { Card, CardContent, CardActions, Button, Typography, Grid, TextField, MenuItem, Select, FormControl, InputLabel } from "@mui/material";

function EditFile() {
  const { id } = useParams();
  const [tipoRemessa, setTipoRemessa] = useState("I");
  const [saldoDia1, setSaldoDia1] = useState("");
  const [saldoDia2, setSaldoDia2] = useState("");
  const [loading, setLoading] = useState(true);

  const cnpj = "44478623";
  const conta1 = "3097000003";
  const conta2 = "4193000009";

  useEffect(() => {
    const fetchFile = async () => {
      const token = localStorage.getItem("token");
      try {
        const response = await axios.get(`http://localhost:8000/files.php?id=${id}`, {
          headers: { Authorization: `Bearer ${token}` },
        });

        const fileData = response.data.content;
        setSaldoDia1(fileData?.contas?.[0]?.saldoDia || "");
        setSaldoDia2(fileData?.contas?.[1]?.saldoDia || "");
        setTipoRemessa(fileData?.tipoRemessa || "I");
        setLoading(false);
      } catch (error) {
        console.error("Erro ao buscar arquivo:", error);
        alert("Erro ao carregar arquivo.");
      }
    };

    fetchFile();
  }, [id]);

  const handleSave = async () => {
    const token = localStorage.getItem("token");
    const data = {
      cnpj,
      contas: [
        { codigoConta: conta1, saldoDia: saldoDia1 },
        { codigoConta: conta2, saldoDia: saldoDia2 },
      ],
      tipoRemessa,
    };

    try {
      await axios.put(`http://localhost:8000/files.php?id=${id}`, data, {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });
      alert("Arquivo atualizado com sucesso!");
      window.location.href = "/files";
    } catch (error) {
      console.error("Erro ao atualizar arquivo:", error);
      alert("Não foi possível atualizar o arquivo.");
    }
  };

  if (loading) {
    return (
      <div style={styles.loadingContainer}>
        <Typography variant="h5" color="white">
          Carregando...
        </Typography>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      <Header />
      <Typography variant="h4" sx={{ textAlign: "center", color: "#FFFFFF", marginBottom: 2 }}>
        Editar Arquivo
      </Typography>

      <Card sx={{ maxWidth: 600, margin: "0 auto", backgroundColor: "#444B52", color: "#FFFFFF", padding: 3 }}>
        <CardContent>
          <form onSubmit={(e) => e.preventDefault()}>
            <Grid container spacing={3}>
              
              {/* Tipo de Remessa */}
              <Grid item xs={12}>
                <FormControl fullWidth>
                  <InputLabel style={{ color: "#FFFFFF" }}>Tipo de Remessa</InputLabel>
                  <Select
                    value={tipoRemessa}
                    onChange={(e) => setTipoRemessa(e.target.value)}
                    style={styles.select}
                    required
                  >
                    <MenuItem value="I">I (Primeira remessa do documento)</MenuItem>
                    <MenuItem value="S">S (Substituir documento enviado e aceito)</MenuItem>
                  </Select>
                </FormControl>
              </Grid>

              {/* CNPJ */}
              <Grid item xs={12}>
                <TextField label="CNPJ" value={cnpj} fullWidth disabled variant="outlined" InputProps={{ style: styles.inputDisabled }} />
              </Grid>

              {/* Contas */}
              <Grid item xs={12}>
                <Typography variant="h6">Contas</Typography>
              </Grid>

              <Grid item xs={12} md={6}>
                <TextField label="Código Conta 1" value={conta1} fullWidth disabled variant="outlined" InputProps={{ style: styles.inputDisabled }} />
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  label="Saldo do Dia"
                  type="number"
                  value={saldoDia1}
                  onChange={(e) => setSaldoDia1(e.target.value)}
                  fullWidth
                  required
                />
              </Grid>

              <Grid item xs={12} md={6}>
                <TextField label="Código Conta 2" value={conta2} fullWidth disabled variant="outlined" InputProps={{ style: styles.inputDisabled }} />
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  label="Saldo do Dia"
                  type="number"
                  value={saldoDia2}
                  onChange={(e) => setSaldoDia2(e.target.value)}
                  fullWidth
                  required
                />
              </Grid>
            </Grid>
          </form>
        </CardContent>

        <CardActions>
          <Button type="submit" fullWidth variant="contained" color="primary" onClick={handleSave}>
            Salvar Alterações
          </Button>
        </CardActions>
      </Card>
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
  select: {
    backgroundColor: "#FFFFFF",
    color: "#000000",
  },
  inputDisabled: {
    backgroundColor: "#e0e0e0",
    color: "#555",
  },
  loadingContainer: {
    display: "flex",
    justifyContent: "center",
    alignItems: "center",
    height: "100vh",
    backgroundColor: "#32373C",
  },
};

export default EditFile;
