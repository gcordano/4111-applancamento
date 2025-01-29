import sys

def transmitir_arquivo(file_id):
    print(f"Transmitindo o arquivo com ID: {file_id}")
    # Aqui você adiciona a lógica da transmissão real

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("ID do arquivo não fornecido!")
        sys.exit(1)

    file_id = sys.argv[1]
    transmitir_arquivo(file_id)
