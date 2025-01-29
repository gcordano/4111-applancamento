import React from "react";

function Header() {
  return (
    <header style={styles.header}>
      <a href="/" style={styles.logoLink}>
        <img
          src="https://aticca.com.br/wp-content/uploads/logo.png" // link da logo aticca
          alt="Logo"
          style={styles.logo}
        />
      </a>
    </header>
  );
}

const styles = {
  header: {
    display: "flex",
    justifyContent: "stretch",
    alignItems: "stretch",
    padding: "10px 0",
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
};

export default Header;
