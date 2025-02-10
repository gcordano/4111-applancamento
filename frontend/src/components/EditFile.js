import React, { useState, useEffect } from "react";
import axios from "axios";
import Header from "./Header";
import { useParams, useNavigate } from "react-router-dom";
import { Box, TextField, Button, Typography, FormControl, InputLabel, Select, MenuItem, CircularProgress } from "@mui/material";

const apiUrl = process.env.REACT_APP_API_URL;

function EditFile() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [fileName, setFileName] = useState("");
  const [tipoRemessa, setTipoRemessa] = useState("I");
  const [cnpj, setCnpj] = useState("");
  const [contas, setContas] = useState([]);
  const [saldoDia1, setSaldoDia1] = useState("");
  const [saldoDia2, setSaldoDia2] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchFile = async () => {
      try {
        const response = await axios.get(`${apiUrl}/src/routes/movimentacao.php?route=getFile&id=${id}`, {
          headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
        });

        if (response.data) {
          setFileName(response.data.name || "Documento Sem Nome");
          setCnpj(response.data.cnpj || "");  // 🔹 Define o CNPJ
          setTipoRemessa(response.data.tipo_remessa || "I"); // 🔹 Define o Tipo de Remessa

          if (response.data.contas && response.data.contas.length === 2) {
            setContas(response.data.contas);
            setSaldoDia1(response.data.contas[0].saldo);
            setSaldoDia2(response.data.contas[1].saldo);
          }
        }

        setLoading(false);
      } catch (error) {
        alert("Erro ao carregar arquivo.");
        setLoading(false);
      }
    };

    fetchFile();
  }, [id]);

  const handleSave = async () => {
    const token = localStorage.getItem("token");
  
    const data = {
      id_conta_1: contas[0].numero,
      saldo_conta_1: saldoDia1,
      id_conta_2: contas[1].numero,
      saldo_conta_2: saldoDia2,
      tipo_remessa: tipoRemessa,
    };
  
    try {
        await axios.put(`${apiUrl}/src/routes/movimentacao.php?route=update&id=${id}`, data, {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });
      alert("Arquivo atualizado com sucesso!");
      navigate("/files");
    } catch (error) {
      alert("Erro ao atualizar o arquivo.");
    }
  };

  if (loading) {
    return (
      <Box sx={styles.loadingContainer}>
        <CircularProgress color="inherit" />
        <Typography variant="h6">Carregando arquivo...</Typography>
      </Box>
    );
  }

  return (
    <Box sx={styles.container}>

      {/* Header fixo no topo */}
      <Box sx={styles.headerWrapper}>
        <Header />
      </Box>

      <Typography variant="h4" sx={styles.title}>
        Editar Documento {fileName}
      </Typography>
  
      <Box component="form" sx={styles.form}>
        
        {/* Tipo de Remessa */}
        <FormControl fullWidth sx={styles.formGroup}>
          <InputLabel>Tipo de Remessa</InputLabel>
          <Select value={tipoRemessa} onChange={(e) => setTipoRemessa(e.target.value)} required sx={styles.input}>
            <MenuItem value="I">I (Primeira remessa do documento)</MenuItem>
            <MenuItem value="S">S (Substituir documento enviado e aceito)</MenuItem>
          </Select>
        </FormControl>
  
        {/* CNPJ Fixo */}
        <TextField 
          fullWidth 
          label="CNPJ" 
          value={cnpj} 
          disabled 
          sx={styles.inputDisabled} 
        />
  
        <Typography variant="h6" sx={styles.subtitle}>Contas</Typography>
  
        {/* Contas e Saldos */}
        {contas.length === 2 && (
          <>
            <Box sx={styles.row}>
              <TextField 
                fullWidth 
                label="Conta 1" 
                value={contas[0].numero} 
                disabled 
                sx={styles.inputDisabled} 
              />
              <TextField
                fullWidth
                type="number"
                label="Saldo do Dia"
                value={saldoDia1}
                onChange={(e) => setSaldoDia1(e.target.value)}
                required
                sx={styles.input}
              />
            </Box>
  
            <Box sx={styles.row}>
              <TextField 
                fullWidth 
                label="Conta 2" 
                value={contas[1].numero} 
                disabled 
                sx={styles.inputDisabled} 
              />
              <TextField
                fullWidth
                type="number"
                label="Saldo do Dia"
                value={saldoDia2}
                onChange={(e) => setSaldoDia2(e.target.value)}
                required
                sx={styles.input}
              />
            </Box>
          </>
        )}
  
        <Button
          variant="contained"
          color="primary"
          fullWidth
          sx={styles.submitButton}
          onClick={handleSave}
        >
          Salvar Alterações
        </Button>
      </Box>
    </Box>
  );  
}

const styles = {
  container: {
    backgroundColor: "#32373C",
    color: "#FFFFFF",
    minHeight: "100vh",
    padding: "20px",
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
  },
  headerWrapper: {
    width: "100%",
    marginBottom: "20px", // 🔹 Espaço entre Header e Conteúdo
  },
  title: {
    textAlign: "center",
    marginBottom: "20px",
    fontSize: "2rem",
    color: "#FFFFFF",
  },
  form: {
    maxWidth: "600px",
    margin: "0 auto",
    backgroundColor: "#444B52",
    padding: "25px",
    borderRadius: "10px",
    boxShadow: "0px 4px 6px rgba(0, 0, 0, 0.1)",
  },
  formGroup: {
    marginBottom: "15px",
  },
  subtitle: {
    marginBottom: "15px",
  },
  row: {
    display: "flex",
    gap: "15px", // 🔹 Espaço entre Conta e Saldo
    flexDirection: "row",
    marginBottom: "15px",
  },
  input: {
    backgroundColor: "#FFFFFF", // 🔹 Fundo branco
    color: "#000000", // 🔹 Texto preto
    borderRadius: "5px",
  },
  inputDisabled: {
    backgroundColor: "#E0E0E0", // 🔹 Cinza claro para inputs desativados (CNPJ e contas)
    color: "#000000",
    borderRadius: "5px",
  },
  submitButton: {
    marginTop: "20px",
    padding: "12px",
    fontSize: "1rem",
    fontWeight: "bold",
  },
};

export default EditFile;
