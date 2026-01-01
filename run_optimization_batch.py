import os
# Certifique-se de que a função otimizar_thumbnail está no image_optimization.py
from image_optimization import otimizar_thumbnail 

# --- CONFIGURAÇÕES ---
# O diretório onde suas thumbnails NÃO otimizadas estão atualmente
INPUT_DIR = 'uploads/thumbnails_antigas/' 
# O diretório onde você quer salvar as thumbnails OTIMIZADAS
OUTPUT_DIR = 'uploads/thumbnails/' 
# ---------------------

def processar_lote_de_thumbnails():
    """
    Percorre o diretório de entrada, otimiza e salva no diretório de saída.
    Lida com nomes de arquivo sem extensão padrão.
    """
    # Cria o diretório de saída se ele não existir
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    
    print(f"Iniciando o processamento em lote no diretório: {INPUT_DIR}")
    
    # Percorre todos os arquivos no diretório de entrada
    for filename in os.listdir(INPUT_DIR):
        
        # Ignora diretórios
        if os.path.isdir(os.path.join(INPUT_DIR, filename)):
            continue
        
        # O caminho completo para o arquivo original (ex: 'uploads/thumbnails_antigas/68e4ed9ca9a9b3.86554292')
        caminho_original = os.path.join(INPUT_DIR, filename)
        
        # O novo nome do arquivo será o nome original + .jpg (ex: '68e4ed9ca9a9b3.86554292.jpg')
        # Sempre usaremos o formato JPG para o arquivo OTIMIZADO
        caminho_destino = os.path.join(OUTPUT_DIR, f"{filename}.jpg")

        print(f"Processando: {filename}...")
        
        # Chama a função de otimização
        # A função otimizar_thumbnail (Pillow) tentará descobrir o formato do arquivo original
        # mesmo sem a extensão no nome.
        otimizar_thumbnail(
            caminho_original=caminho_original, 
            caminho_destino=caminho_destino
            # max_largura e qualidade usam os defaults internos
        )

    print("\n✅ Processamento em lote concluído!")
    print(f"As novas thumbnails otimizadas estão em: {OUTPUT_DIR}")

# Executa a função
if __name__ == "__main__":
    processar_lote_de_thumbnails()