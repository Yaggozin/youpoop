import sys # <--- CORREÇÃO AQUI
import os
from PIL import Image

# --- CONFIGURAÇÕES ---
TARGET_WIDTH = 480
TARGET_HEIGHT = 270
BACKGROUND_COLOR = (0, 0, 0)
# ---------------------

def otimizar_thumbnail(caminho_original, caminho_destino, qualidade=80):
    try:
        # Verifica se arquivo existe
        if not os.path.exists(caminho_original):
            print(f"Erro: Arquivo nao encontrado: {caminho_original}")
            return False

        img = Image.open(caminho_original)
        
        # Converte para RGB (remove transparencia de PNG para evitar erros no JPEG)
        if img.mode in ('RGBA', 'P'):
            img = img.convert('RGB')
            
        original_width, original_height = img.size
        
        # Logica de redimensionamento (Letterbox)
        scale_ratio = min(TARGET_WIDTH / original_width, TARGET_HEIGHT / original_height)
        new_width = int(original_width * scale_ratio)
        new_height = int(original_height * scale_ratio)
        
        img_resized = img.resize((new_width, new_height), Image.Resampling.LANCZOS)
        
        # Cria fundo preto
        canvas = Image.new('RGB', (TARGET_WIDTH, TARGET_HEIGHT), BACKGROUND_COLOR)
        
        # Centraliza
        x_offset = (TARGET_WIDTH - new_width) // 2
        y_offset = (TARGET_HEIGHT - new_height) // 2
        canvas.paste(img_resized, (x_offset, y_offset))
        
        # Garante que a pasta existe
        os.makedirs(os.path.dirname(caminho_destino), exist_ok=True)
            
        # Salva (sobrescrevendo ou criando novo)
        canvas.save(caminho_destino, "JPEG", quality=qualidade, optimize=True)
        return True
        
    except Exception as e:
        print(f"Erro Python: {e}")
        return False

# --- Execucao via Linha de Comando (Chamada pelo PHP) ---
if __name__ == "__main__":
    # O PHP envia: python script.py [input] [output]
    if len(sys.argv) < 3:
        print("Faltando argumentos")
        sys.exit(1)

    input_path = sys.argv[1]
    output_path = sys.argv[2]

    sucesso = otimizar_thumbnail(input_path, output_path)
    
    if sucesso:
        print("SUCESSO")
    else:
        print("FALHA")
        sys.exit(1)