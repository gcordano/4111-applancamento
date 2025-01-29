import React from "react";
import { BrowserRouter as Router, Route, Routes, Navigate } from "react-router-dom";
import Login from "./components/Login";
import FileList from "./components/FileList";
import CreateFile from "./components/CreateFile";
import EditFile from "./components/EditFile";


function App() {
  const isAuthenticated = !!localStorage.getItem("token");

  return (
    <Router>
      <Routes>
        {/* Rota de Login */}
        <Route path="/" element={!isAuthenticated ? <Login /> : <Navigate to="/files" />} />

        {/* Rota de Lista de Arquivos */}
        <Route path="/files" element={isAuthenticated ? <FileList /> : <Navigate to="/" />} />

        {/* Rota de Criação de Arquivos */}
        <Route path="/create" element={isAuthenticated ? <CreateFile /> : <Navigate to="/" />} />

        {/* Rota de Edição de Arquivo */}
        <Route path="/edit/:id" element={isAuthenticated ? <EditFile /> : <Navigate to="/" />} />
      </Routes>
    </Router>
  );
}

export default App;
