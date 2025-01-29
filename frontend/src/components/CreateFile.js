import React, { useState } from "react";
import axios from "axios";
import Header from "./Header";

function CreateFile() {
  const [tipoRemessa, setTipoRemessa] = useState("I");
  const [saldoDia1, setSaldoDia1] = useState("");
  const [saldoDia2, setSaldoDia2] = useState("");

  // Valores fixos
  const cnpj = "44478623";
  const conta1 = "3097000003";
  const conta2 = "4193000009";

  const handleCreateFile = async (e) => {
    e.preventDefault();
    const token = localStorage.getItem("token");

    const data = {
      cnpj, // CNPJ fixo
      contas: [
        { codigoConta: conta1, saldoDia: saldoDia1 },
        { codigoConta: conta2, saldoDia: saldoDia2 },
      ],
      tipoRemessa,
    };

    try {
      await axios.post("http://localhost:8000/files.php", data, {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });
      alert("Arquivo criado com sucesso!");
      window.location.href = "/files";
    } catch (error) {
      console.error("Erro ao criar arquivo:", error);
      alert("Erro ao criar arquivo. Tente novamente.");
    }
  };

  return (
    <div style={styles.container}>
      <Header />
      <h1 style={styles.title}>Lançamento 4111</h1>
      <form onSubmit={handleCreateFile} style={styles.form}>
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

        <button type="submit" style={styles.submitButton}>
          Criar
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
    width: "100%",
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
    backgroundColor: "#4CAF50",
    color: "#FFFFFF",
    border: "none",
    borderRadius: "5px",
    fontSize: "1rem",
    cursor: "pointer",
  },
};

export default CreateFile;
