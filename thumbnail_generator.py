import subprocess
import os
import sys
import json

# --- CONFIGURAÇÕES ---
THUMB_WIDTH = 480
THUMB_HEIGHT = 270
OUTPUT_DIR = 'uploads/thumbnails/temp'
# ---------------------

def get_video_duration(input_path):
    """
    Usa o FFprobe para obter a duração do vídeo em segundos.
    """
    try:
        cmd = [
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            input_path
        ]
        result = subprocess.run(cmd, check=True, capture_output=True, text=True)
        return float(result.stdout.strip())
    except Exception as e:
        print(f"Erro ao obter a duração: {e}", file=sys.stderr)
        return 0.0

def generate_thumbnails(input_path, video_id):
    """
    Gera três thumbnails do vídeo: 10%, 50% e 90% da duração.
    """
    duration = get_video_duration(input_path)
    if duration == 0:
        return []

    # Cria a pasta temporária para o ID específico
    temp_dir = os.path.join(OUTPUT_DIR, str(video_id))
    os.makedirs(temp_dir, exist_ok=True)
    
    # Momentos de captura (em segundos)
    timestamps = {
        'start': duration * 0.1,
        'middle': duration * 0.5,
        'end': duration * 0.9
    }
    
    output_files = []

    for name, time_sec in timestamps.items():
        # Formata o tempo em HH:MM:SS.ms (exigido pelo -ss)
        time_str = "{:02d}:{:02d}:{:02d}.{:03d}".format(
            int(time_sec // 3600),
            int((time_sec % 3600) // 60),
            int(time_sec % 60),
            int((time_sec * 1000) % 1000)
        )
        
        # Nome do arquivo de saída (ex: 15_middle.jpg)
        output_name = f"{video_id}_{name}.jpg"
        output_path = os.path.join(temp_dir, output_name)
        
        # Comando FFmpeg para extrair um único frame
        ffmpeg_command = [
            'ffmpeg',
            '-ss', time_str,
            '-i', input_path,
            '-vframes', '1',  # Apenas um frame
            '-vf', f'scale={THUMB_WIDTH}:{THUMB_HEIGHT}:force_original_aspect_ratio=increase,crop={THUMB_WIDTH}:{THUMB_HEIGHT}',
            '-q:v', '2',      # Qualidade alta (low VBR)
            output_path
        ]
        
        try:
            # Silencia a saída do FFmpeg (opcional, mas limpa o log)
            subprocess.run(ffmpeg_command, check=True, capture_output=True, text=True)
            output_files.append({
                'name': name,
                'path': os.path.join(OUTPUT_DIR, str(video_id), output_name), # Caminho relativo para o cliente
                'time_sec': round(time_sec, 2)
            })
        except subprocess.CalledProcessError as e:
            print(f"Erro FFmpeg ao extrair {name}: {e.stderr}", file=sys.stderr)
            continue
            
    # Retorna o array de caminhos em formato JSON (para o PHP ler)
    print(json.dumps(output_files))
    print("SUCESSO")
    return output_files


# --- Execução via Linha de Comando ---
if __name__ == "__main__":
    if len(sys.argv) < 3:
        # sys.argv[0] é o nome do script
        print("Faltando argumentos: <input_path> <video_id>", file=sys.stderr)
        sys.exit(1)

    input_path = sys.argv[1]
    video_id = sys.argv[2]
    
    # Chama a função e imprime JSON na saída padrão
    generate_thumbnails(input_path, video_id)