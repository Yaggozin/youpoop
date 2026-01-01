import subprocess
import os
import sys

# --- PASTA BASE ---
BASE_VIDEO_FOLDER = 'uploads/videos'
# ------------------

def transcodificar_para_mp4_otimizado(caminho_original, video_id):
    """
    Transcodifica o vídeo para MP4 (h264) com otimização faststart.
    Regra: Só faz downscale para 720p se o original for maior ou igual a 720p.
    Vídeos menores que 720p mantêm a resolução original.
    """
    
    # 1. Definir caminhos
    caminho_pasta_video = os.path.join(BASE_VIDEO_FOLDER, str(video_id))
    os.makedirs(caminho_pasta_video, exist_ok=True)
    
    # O arquivo de saída MP4 único
    output_mp4 = os.path.join(caminho_pasta_video, '720p_optimized.mp4')
    
    if not os.path.exists(caminho_original):
        print(f"Erro: Arquivo original não encontrado em {caminho_original}")
        return False
        
    print(f"  > Iniciando transcodificacao MP4 inteligente para o ID: {video_id}")

    # 2. Comando FFmpeg para MP4 (COM FILTRO INTELIGENTE)
    # Filtro: Se a altura de entrada (ih) for >= 720, escala para 720p. Senão, mantém a resolução original.
    ffmpeg_command = [
        'ffmpeg',
        '-i', caminho_original,
        '-vf', 'scale=if(gte(ih,720),-2:720,iw:-2)',      
        '-c:v', 'libx264',
        '-b:v', '2500k',            
        '-preset', 'medium',        
        '-c:a', 'aac',
        '-b:a', '128k',             
        '-movflags', 'faststart',   # Otimiza para streaming
        output_mp4                  
    ]
    
    try:
        subprocess.run(ffmpeg_command, check=True, capture_output=True, text=True)
        print(f"  > ✅ Transcodificacao MP4 concluida.")
        return True
    except subprocess.CalledProcessError as e:
        print(f"  ❌ Erro FFmpeg (codigo {e.returncode}). Log: {e.stderr}")
        return False
    except FileNotFoundError:
        print("  ❌ Erro: FFmpeg nao encontrado. Certifique-se de que esta instalado.")
        return False
        
# --- Execução via Linha de Comando ---
if __name__ == "__main__":
    if len(sys.argv) < 3:
        # sys.argv[0] é o nome do script
        print("Uso incorreto. Necessario: python video_transcoding.py <input_path> <video_id>")
        sys.exit(1)

    input_path = sys.argv[1]
    video_id = sys.argv[2]

    sucesso = transcodificar_para_mp4_otimizado(input_path, video_id) 
    
    if sucesso:
        print("SUCESSO")
    else:
        print("FALHA")
        sys.exit(1)