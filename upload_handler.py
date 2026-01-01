# Importa a função (nome em português) do arquivo (nome em inglês)
from .image_optimization import otimizar_thumbnail

def handle_video_upload(video_file, original_thumbnail_file):
    
    # ... (lógica de upload) ...
    
    # Variáveis de caminho podem ser em inglês, se desejar
    original_thumb_path = original_thumbnail_file
    optimized_thumb_path = 'uploads/thumbnails/video_123_480p.jpg'
    
    # Chama a função
    success = otimizar_thumbnail(
        caminho_original=original_thumb_path, 
        caminho_destino=optimized_thumb_path,
        max_largura=480, 
        qualidade=80     
    )
    
    # ...