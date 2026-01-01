import os
import mysql.connector
from video_transcoding import transcodificar_para_hls_720p_inteligente # Importa a função
from mysql.connector import Error

# --- CONFIGURAÇÕES DE BANCO DE DADOS ---
DB_CONFIG = {
    'host': '127.0.0.1',        # Seu host
    'database': 'ytp_db',       # O nome do banco de dados que você me forneceu
    'user': 'seu_usuario_db',   # <--- MUDAR: Seu usuário do MySQL
    'password': 'sua_senha_db'  # <--- MUDAR: Sua senha do MySQL
}
# ----------------------------------------

def conectar_db():
    """Tenta conectar ao banco de dados."""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except Error as e:
        print(f"Erro ao conectar ao MySQL: {e}")
        return None

def obter_videos_para_processar(conn):
    """Busca vídeos antigos (assumindo que terminam em .mp4)."""
    sql_select = """
    SELECT id, video_path 
    FROM videos 
    WHERE video_path LIKE '%.mp4' 
       OR video_path LIKE '%.mov';
    """
    cursor = conn.cursor()
    cursor.execute(sql_select)
    return cursor.fetchall()

def atualizar_caminho_hls_no_db(conn, video_id, novo_caminho):
    """Atualiza o video_path com o novo caminho HLS."""
    sql_update = "UPDATE videos SET video_path = %s WHERE id = %s"
    cursor = conn.cursor()
    cursor.execute(sql_update, (novo_caminho, video_id))
    conn.commit()

def deletar_arquivo_original(caminho):
    """Deleta o arquivo original (MP4) do sistema de arquivos."""
    try:
        # Verifica se o arquivo existe antes de tentar deletar
        if os.path.exists(caminho):
            os.remove(caminho)
            print(f"  > Arquivo original deletado com sucesso: {caminho}")
            return True
        else:
            print(f"  ⚠️ Arquivo original não encontrado, ignorando exclusão: {caminho}")
            return False
    except OSError as e:
        print(f"  ❌ Erro crítico ao deletar arquivo original: {e}")
        return False


def processar_lote_de_videos():
    """Função principal que gerencia o fluxo de transcodificação."""
    conn = conectar_db()
    if not conn:
        return

    videos_para_processar = obter_videos_para_processar(conn)
    print(f"Iniciando processamento de {len(videos_para_processar)} vídeos brutos.")
    
    for id_video, caminho_original_mp4 in videos_para_processar:
        print(f"\n--- Processando Vídeo ID: {id_video} (Caminho: {caminho_original_mp4}) ---")
        
        # 1. Transcodificação
        sucesso_transcodificacao = transcodificar_para_hls_720p_inteligente(
            caminho_original=caminho_original_mp4,
            video_id=id_video
        )
        
        if sucesso_transcodificacao:
            # Novo caminho HLS (Ex: uploads/videos/6/720p_playlist.m3u8)
            caminho_destino_hls = f'{BASE_VIDEO_FOLDER}/{id_video}/720p_playlist.m3u8'
            
            # 2. DELETAR o arquivo original (economia de espaço)
            deletar_arquivo_original(caminho_original_mp4)
            
            # 3. ATUALIZAR o DB
            atualizar_caminho_hls_no_db(conn, id_video, caminho_destino_hls)
            print(f"  > ✅ DB atualizado: video_path aponta para HLS.")
        else:
            print(f"❌ Transcodificação falhou para o ID {id_video}. O arquivo original NÃO foi deletado.")

    conn.close()
    print("\n✅ Processamento de lote de vídeos concluído.")

if __name__ == "__main__":
    # ⚠️ REFORÇO: FAÇA UM BACKUP DO SEU BANCO DE DADOS ANTES DE RODAR ESTE SCRIPT!
    processar_lote_de_videos()