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

  // Recupera o tema salvo no localStorage (padrão: true - dark mode)
  const [isDarkMode] = useState(() => {
    const saved = localStorage.getItem("isDarkMode");
    return saved ? JSON.parse(saved) : true; // true para o modo escuro por padrão
  });

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
      <Box sx={isDarkMode ? darkStyles.loadingContainer : lightStyles.loadingContainer}>
        <CircularProgress color="inherit" />
        <Typography variant="h6">Carregando arquivo...</Typography>
      </Box>
    );
  }

  return (
    <Box sx={isDarkMode ? darkStyles.container : lightStyles.container}>

      {/* Header fixo no topo */}
      <Box sx={isDarkMode ? darkStyles.headerWrapper : lightStyles.headerWrapper}>
        <Header isDarkMode={isDarkMode} />
      </Box>

      <Typography variant="h4" sx={isDarkMode ? darkStyles.title : lightStyles.title}>
        Editar Documento {fileName}
      </Typography>
  
      <Box component="form" sx={isDarkMode ? darkStyles.form : lightStyles.form}>
        
        {/* Tipo de Remessa */}
        <FormControl fullWidth sx={isDarkMode ? darkStyles.formGroup : lightStyles.formGroup}>
          <InputLabel>Tipo de Remessa</InputLabel>
          <Select value={tipoRemessa} onChange={(e) => setTipoRemessa(e.target.value)} required sx={isDarkMode ? darkStyles.input : lightStyles.input}>
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
          sx={isDarkMode ? darkStyles.inputDisabled : lightStyles.inputDisabled} 
        />
  
        <Typography variant="h6" sx={isDarkMode ? darkStyles.subtitle : lightStyles.subtitle}>Contas</Typography>
  
        {/* Contas e Saldos */}
        {contas.length === 2 && (
          <>
            <Box sx={isDarkMode ? darkStyles.row : lightStyles.row}>
              <TextField 
                fullWidth 
                label="Conta 1" 
                value={contas[0].numero} 
                disabled 
                sx={isDarkMode ? darkStyles.inputDisabled : lightStyles.inputDisabled} 
              />
              <TextField
                fullWidth
                type="number"
                label="Saldo do Dia"
                value={saldoDia1}
                onChange={(e) => setSaldoDia1(e.target.value)}
                required
                sx={isDarkMode ? darkStyles.input : lightStyles.input}
              />
            </Box>
  
            <Box sx={isDarkMode ? darkStyles.row : lightStyles.row}>
              <TextField 
                fullWidth 
                label="Conta 2" 
                value={contas[1].numero} 
                disabled 
                sx={isDarkMode ? darkStyles.inputDisabled : lightStyles.inputDisabled} 
              />
              <TextField
                fullWidth
                type="number"
                label="Saldo do Dia"
                value={saldoDia2}
                onChange={(e) => setSaldoDia2(e.target.value)}
                required
                sx={isDarkMode ? darkStyles.input : lightStyles.input}
              />
            </Box>
          </>
        )}
  
        <Button
          variant="contained"
          color="primary"
          fullWidth
          sx={isDarkMode ? darkStyles.submitButton : lightStyles.submitButton}
          onClick={handleSave}
        >
          Salvar Alterações
        </Button>
      </Box>
    </Box>
  );  
}

const darkStyles = {
  container: {
    backgroundColor: "#262626",
    color: "#FFFFFF",
    minHeight: "100vh",
    padding: "20px",
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
  },
  headerWrapper: {
    width: "100%",
    marginBottom: "20px",
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
    backgroundColor: "#1C1C1C",
    padding: "25px",
    borderRadius: "10px",
    boxShadow: "0px 4px 6px rgba(0, 0, 0, 0.1)",
  },
  formGroup: {
    marginBottom: "15px",
  },
  subtitle: {
    marginBottom: "15px",
    color: "#FFFFFF",
  },
  row: {
    display: "flex",
    gap: "15px",
    flexDirection: "row",
    marginBottom: "15px",
  },
  input: {
    backgroundColor: "#FFFFFF",
    color: "#000000",
    borderRadius: "5px",
  },
  inputDisabled: {
    backgroundColor: "#E0E0E0",
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

const lightStyles = {
  container: {
    backgroundColor: "#CCCCCC",
    color: "#000000",
    minHeight: "100vh",
    padding: "20px",
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
  },
  headerWrapper: {
    width: "100%",
    marginBottom: "20px",
  },
  title: {
    textAlign: "center",
    marginBottom: "20px",
    fontSize: "2rem",
    color: "#000000",
  },
  form: {
    maxWidth: "600px",
    margin: "0 auto",
    backgroundColor: "#FFFFFF",
    padding: "25px",
    borderRadius: "10px",
    boxShadow: "0px 4px 6px rgba(0, 0, 0, 0.1)",
  },
  formGroup: {
    marginBottom: "15px",
  },
  subtitle: {
    marginBottom: "15px",
    color: "#000000",
  },
  row: {
    display: "flex",
    gap: "15px",
    flexDirection: "row",
    marginBottom: "15px",
  },
  input: {
    backgroundColor: "#FFFFFF",
    color: "#000000",
    borderRadius: "5px",
  },
  inputDisabled: {
    backgroundColor: "#E0E0E0",
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
