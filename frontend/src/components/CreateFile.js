import React, { useState, useEffect } from "react";
import axios from "axios";
import Header from "./Header";
import { Box, Typography, FormControl, InputLabel, Select, MenuItem, TextField, Button, CircularProgress } from "@mui/material";

function CreateFile() {
  const [tipoRemessa, setTipoRemessa] = useState("I");
  const [saldoDia1, setSaldoDia1] = useState("");
  const [saldoDia2, setSaldoDia2] = useState("");
  const [dataBase, setDataBase] = useState(""); // Estado para armazenar a data calculada
  const [cnpjList, setCnpjList] = useState([]);
  const [selectedCnpj, setSelectedCnpj] = useState(""); // Definindo o estado de CNPJ selecionado
  const [contas, setContas] = useState([]);
  const [loading, setLoading] = useState(false);

  const apiUrl = process.env.REACT_APP_API_URL;

  useEffect(() => {
    // Função para calcular a data do arquivo
    const calculateDataBase = () => {
      const today = new Date();
      let dataArquivo = new Date(today); // Copiar a data de hoje

      // Se hoje for segunda-feira, retroceder para a última sexta-feira
      if (today.getDay() === 1) {
        dataArquivo.setDate(today.getDate() - 3); // Segunda-feira - 3 dias = Sexta-feira
      } else {
        dataArquivo.setDate(today.getDate() - 1); // Subtrair 1 dia, para pegar o dia anterior
      }

      // Formatando para o formato YYYYMMDD
      const dia = String(dataArquivo.getDate()).padStart(2, "0");
      const mes = String(dataArquivo.getMonth() + 1).padStart(2, "0");
      const ano = dataArquivo.getFullYear();

      const formattedDate = `${ano}${mes}${dia}`;
      setDataBase(formattedDate); // Atualizando o estado com a data formatada
    };

    // Chama a função para calcular a data ao carregar o componente
    calculateDataBase();

    // Carregar CNPJs e Contas
    axios.get(`${apiUrl}/src/routes/movimentacao.php?route=getCnpjsEContas`)
      .then(response => {
        const groupedCnpjs = response.data.reduce((acc, item) => {
          let existingCnpj = acc.find(c => c.id === item.id);
          if (!existingCnpj) {
            existingCnpj = { id: item.id, cnpj: item.cnpj, name: item.name, contas: [] };
            acc.push(existingCnpj);
          }
          existingCnpj.contas.push({ guid: item.conta_id, conta: item.conta });
          return acc;
        }, []);
        setCnpjList(groupedCnpjs);
      })
  }, [apiUrl]);

  // Função para lidar com a troca do CNPJ selecionado
  const handleCnpjChange = (e) => {
    const selected = e.target.value;
    setSelectedCnpj(selected);

    const cnpjSelecionado = cnpjList.find(cnpj => cnpj.id === parseInt(selected));
    if (cnpjSelecionado) {
      setContas(cnpjSelecionado.contas);
    } else {
      setContas([]);
    }

    setSaldoDia1("");
    setSaldoDia2("");
  };

  const handleCreateFile = async (e) => {
    e.preventDefault();
    setLoading(true);
    const token = localStorage.getItem("token");

    if (contas.length !== 2) {
      alert("O CNPJ selecionado precisa ter exatamente duas contas.");
      setLoading(false);
      return;
    }

    const data = {
      id_conta_1: contas[0].guid,
      saldo_conta_1: saldoDia1,
      id_conta_2: contas[1].guid,
      saldo_conta_2: saldoDia2,
      tipo_remessa: tipoRemessa,
      data_movimento: dataBase, // Passando a data calculada
    };

    try {
      const response = await axios.post(`${apiUrl}/src/routes/movimentacao.php?route=create`, data, {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });

      if (response.status === 201) {
        alert("Movimentação criada com sucesso!");
        setTimeout(() => {
          window.location.href = "/files";
        }, 1000);
      } else {
        alert("Erro ao criar movimentação. Verifique os dados.");
      }
    } catch (error) {
      if (error.response && error.response.data.message) {
        alert(error.response.data.message);  // Exibe a mensagem de erro retornada pela API
      } else {
        alert("Erro ao criar movimentação. Já existe uma movimentação ativa para essa data.");
      }
    } finally {
      setLoading(false);
    }
  };


  return (
    <Box sx={styles.container}>
      
      {/* Header fixo no topo */}
      <Box sx={styles.headerWrapper}>
        <Header />
      </Box>
  
      <Typography variant="h4" sx={styles.title}>
        Criar Documento 4111_{dataBase}.xml
      </Typography>
  
      <Box component="form" onSubmit={handleCreateFile} sx={styles.form}>
        
        {/* Tipo de Remessa */}
        <FormControl fullWidth sx={styles.formGroup}>
          <InputLabel>Tipo de Remessa</InputLabel>
          <Select 
            value={tipoRemessa} 
            onChange={(e) => setTipoRemessa(e.target.value)} 
            required
            sx={styles.input}
          >
            <MenuItem value="I">I (Primeira remessa do documento)</MenuItem>
          </Select>
        </FormControl>
  
        {/* CNPJ */}
        <FormControl fullWidth sx={styles.formGroup}>
          <InputLabel>CNPJ</InputLabel>
          <Select 
            value={selectedCnpj} 
            onChange={handleCnpjChange} 
            required
            sx={styles.input}
          >
            <MenuItem value="">Selecione um CNPJ</MenuItem>
            {cnpjList.map(cnpj => (
              <MenuItem key={cnpj.id} value={cnpj.id}>
                {cnpj.cnpj} - {cnpj.name}
              </MenuItem>
            ))}
          </Select>
        </FormControl>
  
        <Typography variant="h6" sx={styles.subtitle}>Contas</Typography>
  
        {contas.length === 2 && (
          <>
            <Box sx={styles.row}>
              <TextField 
                fullWidth 
                label="Conta 1" 
                value={contas[0].conta} 
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
                value={contas[1].conta} 
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
          type="submit"
          variant="contained"
          color="success"
          fullWidth
          sx={styles.submitButton}
          disabled={loading}
        >
          {loading ? <CircularProgress size={24} color="inherit" /> : "Criar"}
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
    paddingTop: "20px",
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
    backgroundColor: "#E0E0E0", // 🔹 Cinza claro para inputs desativados
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

export default CreateFile;
