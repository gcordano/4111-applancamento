import React, { useEffect, useState } from "react";
import { BrowserRouter as Router, Route, Routes, Navigate } from "react-router-dom";
import Login from "./components/Login";
import FileList from "./components/FileList";
import CreateFile from "./components/CreateFile";
import EditFile from "./components/EditFile";

function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(!!localStorage.getItem("token"));

  useEffect(() => {
    const checkAuth = () => {
      const token = localStorage.getItem("token");
      setIsAuthenticated(!!token);
    };
    checkAuth(); // ✅ Verifica a autenticação assim que o componente monta
  window.addEventListener("storage", checkAuth);

  return () => window.removeEventListener("storage", checkAuth);
}, []);

  return (
    <Router>
      <Routes>
        <Route path="/" element={<Login setIsAuthenticated={setIsAuthenticated} />} />
        <Route path="/files" element={isAuthenticated ? <FileList /> : <Navigate to="/" />} />
        <Route path="/create" element={isAuthenticated ? <CreateFile /> : <Navigate to="/" />} />
        <Route path="/edit/:id" element={isAuthenticated ? <EditFile /> : <Navigate to="/" />} />
      </Routes>
    </Router>
  );
}

export default App;
