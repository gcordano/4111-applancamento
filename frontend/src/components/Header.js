import React from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { Button, Typography } from "@mui/material";

const logoUrl = process.env.REACT_APP_LOGODARK_URL;
const logoDarkUrl = process.env.REACT_APP_LOGO_URL;
const logoutUrl = process.env.REACT_APP_LOGOUT_URL || "/";
const filesUrl = process.env.REACT_APP_FILES_URL || "/files";

function Header({ showTitle, isDarkMode }) {
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

  // Estilos dinâmicos de acordo com o tema
  const headerStyle = {
    display: "flex",
    justifyContent: "space-between",
    alignItems: "center",
    padding: "10px 20px",
    backgroundColor: isDarkMode ? "#262626" : "#CCCCCC",
    borderBottom: "1px solid",
    borderBottomColor: isDarkMode ? "#444" : "#ddd",
    marginBottom: "20px",
  };

  const logoStyle = {
    height: "50px",
    width: "auto",
  };

  const titleStyle = {
    flexGrow: 1,
    textAlign: "center",
    color: isDarkMode ? "#FFFFFF" : "#000000",
  };

return (
    <header style={headerStyle}>
      <a href={logoutUrl} style={{ textDecoration: "none" }}>
        {/* Altera a logo conforme o tema */}
        <img src={isDarkMode ? logoDarkUrl : logoUrl} alt="Logo" style={logoStyle} />
      </a>

      {showTitle && isFileList && (
        <Typography variant="h5" style={titleStyle}>
          Saldos Contábeis Diários - Documento 4111
        </Typography>
      )}

      {!isLoginPage && (
        <div style={{ display: "flex", alignItems: "center" }}>
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
export default Header;