import base64
import hashlib
import requests
import xml.etree.ElementTree as ET

# Configurações
url_protocolo = "https://sta-h.bcb.gov.br/staws/arquivos"
usuario = "444920001.S-DEVHOMOL"
senha = "dv527atc"
certificado = "/Users/gustavo/Downloads/certificado_bcb.pem"
caminho_arquivo = "/Users/gustavo/Downloads/4111_20250127.xml"

# Função para calcular hash SHA-256 do arquivo
def calcular_hash_arquivo(caminho):
    sha256 = hashlib.sha256()
    with open(caminho, "rb") as arquivo:
        for bloco in iter(lambda: arquivo.read(4096), b""):
            sha256.update(bloco)
    return sha256.hexdigest()

# Gera a autenticação em Base64
def gerar_auth(usuario, senha):
    credenciais = f"{usuario}:{senha}"
    return base64.b64encode(credenciais.encode("utf-8")).decode("utf-8")

# Etapa 1: Solicitar protocolo de envio
def solicitar_protocolo():
    hash_arquivo = calcular_hash_arquivo(caminho_arquivo)
    tamanho_arquivo = str(len(open(caminho_arquivo, "rb").read()))
    
    xml_envio = f"""<?xml version="1.0" encoding="UTF-8"?>
    <Parametros>
      <IdentificadorDocumento>4111</IdentificadorDocumento>
      <Hash>{hash_arquivo}</Hash>
      <Tamanho>{tamanho_arquivo}</Tamanho>
      <NomeArquivo>4111_20250127.xml</NomeArquivo>
      <Observacao>Teste de envio no ambiente de homologação</Observacao>
    </Parametros>"""

    headers = {
        "Content-Type": "application/xml",
        "Authorization": f"Basic {gerar_auth(usuario, senha)}"
    }

    resposta = requests.post(url_protocolo, headers=headers, data=xml_envio, cert=certificado, verify="/Users/gustavo/Downloads/fullchain2023.pem")

    if resposta.status_code == 200:
        xml_resposta = ET.fromstring(resposta.text)
        protocolo = xml_resposta.find(".//Protocolo").text
        return protocolo
    else:
        raise Exception(f"Erro ao solicitar protocolo: {resposta.status_code} - {resposta.text}")

# Etapa 2: Enviar arquivo com protocolo recebido
def transmitir_arquivo(protocolo):
    url_upload = f"https://sta-h.bcb.gov.br/staws/arquivos/{protocolo}/conteudo"
    headers = {"Authorization": f"Basic {gerar_auth(usuario, senha)}"}

    with open(caminho_arquivo, "rb") as file:
        resposta = requests.put(url_upload, headers=headers, data=file, cert=certificado, verify="/Users/gustavo/Downloads/fullchain2023.pem")

    if resposta.status_code == 200:
        return True
    else:
        raise Exception(f"Erro ao transmitir arquivo: {resposta.status_code} - {resposta.text}")

# Etapa 3: Verificar status da transmissão
def verificar_transmissao(protocolo):
    url_status = f"https://sta-h.bcb.gov.br/staws/arquivos/{protocolo}/posicaoupload"
    headers = {"Authorization": f"Basic {gerar_auth(usuario, senha)}"}
    
    resposta = requests.get(url_status, headers=headers, cert=certificado, verify="/Users/gustavo/Downloads/fullchain2023.pem")

    if resposta.status_code == 200:
        return resposta.text
    else:
        raise Exception(f"Erro ao verificar status: {resposta.status_code} - {resposta.text}")

# Fluxo de execução
try:
    protocolo = solicitar_protocolo()
    print(f"Protocolo recebido: {protocolo}")
    
    if transmitir_arquivo(protocolo):
        print("Arquivo transmitido com sucesso!")

    status = verificar_transmissao(protocolo)
    print("Status da transmissão:", status)

except Exception as e:
    print("Erro no processo:", str(e))
