import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { loginUser } from "../api/api"; // Importando a função de login
import Header from "./Header";
import Button from "@mui/material/Button";

function Login({ setIsAuthenticated }) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  // 🔹 Verifica se o usuário já está autenticado
  useEffect(() => {
    const token = localStorage.getItem("token");
    if (token) {
      setIsAuthenticated(true);
      navigate("/files");
    }
  }, [setIsAuthenticated, navigate]);

  const handleLogin = async (e) => {
    e.preventDefault();
    setLoading(true);
  
    const response = await loginUser(email, password);

    if (response?.token) {
      localStorage.setItem("token", response.token);
      setIsAuthenticated(true);
      navigate("/files");
    } else {
      alert(response?.message || "Erro ao fazer login.");
    }
  
    setLoading(false);
  };

  return (
    <div style={styles.container}>
      <div style={styles.headerContainer}>
        <Header />
      </div>
      <div style={styles.loginBox}>
        <h1 style={styles.title}>Lançamentos Contábeis</h1>
        <form onSubmit={handleLogin} style={styles.form}>
          <input
            type="email"
            placeholder="E-mail"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            style={styles.input}
            required
          />
          <input
            type="password"
            placeholder="Senha"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            style={styles.input}
            required
          />
          <Button
            type="submit"
            variant="contained"
            color="success"
            style={styles.loginButton}
            disabled={loading}
          >
            {loading ? "Aguarde..." : "Login"}
          </Button>
        </form>
      </div>
    </div>
  );
}

const styles = {
  container: {
    backgroundColor: "#32373C",
    color: "#FFFFFF",
    minHeight: "100vh",
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
    justifyContent: "center",
    padding: "20px",
  },
  headerContainer: {
    position: "absolute",
    top: 0,
    left: 0,
    width: "100%",
  },
  loginBox: {
    backgroundColor: "#444B52",
    padding: "30px",
    borderRadius: "8px",
    boxShadow: "0px 4px 10px rgba(0, 0, 0, 0.3)",
    width: "100%",
    maxWidth: "400px",
    textAlign: "center",
  },
  title: {
    marginBottom: "20px",
    fontSize: "1.8rem",
    color: "#FFFFFF",
  },
  form: {
    display: "flex",
    flexDirection: "column",
    gap: "15px",
  },
  input: {
    width: "100%",
    padding: "10px",
    fontSize: "1rem",
    borderRadius: "5px",
    border: "1px solid #ddd",
    outline: "none",
    boxSizing: "border-box",
  },
  loginButton: {
    marginTop: "10px",
    width: "100%",
    padding: "10px",
  },
};

export default Login;
