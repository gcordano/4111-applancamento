import React from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { Button, Typography } from "@mui/material";

// Pegando variáveis do .env
const logoUrl = process.env.REACT_APP_LOGO_URL;
const logoutUrl = process.env.REACT_APP_LOGOUT_URL || "/";
const filesUrl = process.env.REACT_APP_FILES_URL || "/files";

function Header({ showTitle }) {
  const location = useLocation();
  const navigate = useNavigate();

  // Verifica a página ativa
  const isLoginPage = location.pathname === "/";
  const isFileList = location.pathname === filesUrl;

  // Função de logout
  const handleLogout = () => {
    localStorage.removeItem("token");
    window.location.href = logoutUrl;
  };

  // Função para voltar à lista de arquivos
  const handleBack = () => {
    navigate(filesUrl);
  };

  return (
    <header style={styles.header}>
      <a href={logoutUrl} style={styles.logoLink}>
        <img src={logoUrl} alt="Logo" style={styles.logo} />
      </a>

      {/* Exibir título no centro somente no FileList */}
      {showTitle && isFileList && (
        <Typography variant="h5" style={styles.title}>
          Saldos Contábeis Diários - Documento 4111
        </Typography>
      )}

      {/* Se não estiver na tela de login, exibe os botões */}
      {!isLoginPage && (
        <div style={styles.buttonContainer}>
          {isFileList ? (
            <Button variant="contained" color="error" onClick={handleLogout}>
              Logout
            </Button>
          ) : (
            <Button variant="contained" color="primary" onClick={handleBack}>
              Voltar
            </Button>
          )}
        </div>
      )}
    </header>
  );
}

const styles = {
  header: {
    display: "flex",
    justifyContent: "space-between",
    alignItems: "center",
    padding: "10px 20px",
    backgroundColor: "#32373c",
    borderBottom: "1px solid #ddd",
    marginBottom: "20px",
  },
  logoLink: {
    textDecoration: "none",
  },
  logo: {
    height: "50px",
    width: "auto",
  },
  title: {
    flexGrow: 1,
    textAlign: "center",
    color: "#FFFFFF",
  },
  buttonContainer: {
    display: "flex",
    alignItems: "center",
  },
};

export default Header;
