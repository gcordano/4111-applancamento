import React, { useState, useEffect } from "react";
import axios from "axios";
import Header from "./Header";
import { useParams } from "react-router-dom";

// Pegando valores do .env
const apiUrl = process.env.REACT_APP_API_URL;
const cnpj = process.env.REACT_APP_CNPJ;
const conta1 = process.env.REACT_APP_CONTA1;
const conta2 = process.env.REACT_APP_CONTA2;

function EditFile() {
  const { id } = useParams();
  const [fileName, setFileName] = useState(""); // Estado para o nome do arquivo
  const [tipoRemessa, setTipoRemessa] = useState("I");
  const [saldoDia1, setSaldoDia1] = useState("");
  const [saldoDia2, setSaldoDia2] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchFile = async () => {
      const token = localStorage.getItem("token");
      try {
        const response = await axios.get(`${apiUrl}/files.php?id=${id}`, {
          headers: { Authorization: `Bearer ${token}` },
        });

        if (response.data && response.data.name) {
          setFileName(response.data.name); // Captura o nome correto
        }

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
      await axios.put(`${apiUrl}/files.php?id=${id}`, data, {
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
    return <div style={styles.loading}>Carregando...</div>;
  }

  return (
    <div style={styles.container}>
      <Header />
      <h1 style={styles.title}>Editar Documento {fileName}</h1>
      <form onSubmit={(e) => e.preventDefault()} style={styles.form}>
        <div style={styles.formGroup}>
          <label style={styles.label}>Tipo de Remessa</label>
          <select
            value={tipoRemessa}
            onChange={(e) => setTipoRemessa(e.target.value)}
            style={styles.select}
            required
          >
            <option value="I">I (Primeira remessa do documento)</option>
            <option value="S">S (Substituir documento enviado e aceito)</option>
          </select>
        </div>

        <div style={styles.formGroup}>
          <label style={styles.label}>CNPJ</label>
          <input type="text" value={cnpj} disabled style={styles.inputDisabled} />
        </div>

        <h3 style={styles.subtitle}>Contas</h3>

        <div style={styles.row}>
          <input type="text" value={conta1} disabled style={styles.inputDisabled} />
          <input
            type="number"
            placeholder="Saldo do Dia"
            value={saldoDia1}
            onChange={(e) => setSaldoDia1(e.target.value)}
            style={styles.inputInline}
            required
          />
        </div>

        <div style={styles.row}>
          <input type="text" value={conta2} disabled style={styles.inputDisabled} />
          <input
            type="number"
            placeholder="Saldo do Dia"
            value={saldoDia2}
            onChange={(e) => setSaldoDia2(e.target.value)}
            style={styles.inputInline}
            required
          />
        </div>

        <button type="button" style={styles.submitButton} onClick={handleSave}>
          Salvar Alterações
        </button>
      </form>
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
    padding: "20px",
    borderRadius: "10px",
    boxShadow: "0px 4px 6px rgba(0, 0, 0, 0.1)",
  },
  formGroup: {
    marginBottom: "15px",
  },
  label: {
    display: "block",
    marginBottom: "5px",
    color: "#FFFFFF",
  },
  inputDisabled: {
    width: "96.4%",
    padding: "10px",
    borderRadius: "5px",
    border: "1px solid #ddd",
    fontSize: "1rem",
    backgroundColor: "#e0e0e0",
    color: "#555",
  },
  inputInline: {
    width: "50%",
    padding: "10px",
    borderRadius: "5px",
    border: "1px solid #ddd",
    fontSize: "1rem",
    marginLeft: "10px",
  },
  row: {
    display: "flex",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: "15px",
  },
  select: {
    width: "100%",
    padding: "10px",
    borderRadius: "5px",
    border: "1px solid #ddd",
    fontSize: "1rem",
    backgroundColor: "#FFFFFF",
    color: "#000000",
  },
  subtitle: {
    marginTop: "20px",
    color: "#FFFFFF",
  },
  submitButton: {
    width: "100%",
    padding: "10px",
    backgroundColor: "#007BFF",
    color: "#FFFFFF",
    border: "none",
    borderRadius: "5px",
    fontSize: "1rem",
    cursor: "pointer",
  },
  loading: {
    textAlign: "center",
    color: "#FFFFFF",
    fontSize: "1.5rem",
  },
};

export default EditFile;
