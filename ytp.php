<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Vídeo</title>
<style>

body {
    font-family: Arial, sans-serif;
    background-color: #f1f1f1;
    margin: 0;
    padding: 20px;
    display: flex;
    justify-content: center;
}

.videos-selection {
    display: flex;
    align-items: center;
    background-color: #ffffff;
    padding: 10px 0;
    border: 1px solid #ccc;
    overflow: hidden;
    max-width: 100%;
    position: relative;
}

.videos-selection::before,
.videos-selection::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 40px; /* Largura da área de "desvanecimento" */
    pointer-events: none; /* ESSENCIAL: Permite clicar no que está "por baixo" */
    z-index: 10; /* Garante que fique acima da lista de filtros */
}

/* Gradiente da Esquerda */
.videos-selection::before {
    left: 0;
    background: #fffffff2;
    border-right: 1px solid #f1f1f1; /* Para simular a borda lateral */
}

/* Gradiente da Direita */
.videos-selection::after {
    right: 0;
    background: #fffffff2;
    border-left: 1px solid #f1f1f1; /* Para simular a borda lateral */
}

.nav-arrow {
    background: #f1f1f1;
    border: 1px solid #ccc;
    color: #666;
    padding: 20px 5px;
    cursor: pointer;
    font-size: 18px;
    line-height: 0;
    margin: 0 5px;
    height: 100px;
    
    /* PROPRIEDADES CHAVE DE FIXAÇÃO */
    position: absolute; /* Remove do fluxo flexível */
    z-index: 30; /* Garante que fique acima de tudo (incluindo os gradientes) */
    
    /* Centraliza o ícone da seta */
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Posiciona as setas individualmente */
.left-arrow {
    left: 0; /* Fixa a seta esquerda no canto esquerdo */
}

.right-arrow {
    right: 0; /* Fixa a seta direita no canto direito */
}

/* .nav-arrow {
    background: #f1f1f1;
    border: 1px solid #ccc;
    color: #666;
    padding: 20px 5px;
    cursor: pointer;
    font-size: 18px;
    line-height: 0;
    margin: 0 5px;
    height: 100px;
    z-index: 20;
    position: relative;
} */

.nav-arrow:hover {
    background-color: #e0e0e0;
}

.nav-arrow:disabled {
    cursor: default;
    opacity: 0.5;
}

.videos-list {
    position: relative;
    display: flex;
    gap: 10px;
    overflow: hidden;
    padding: 0 5px;
    /*transition: left 0.2s ease-out;*/
    transition: left 0.3s ease-in-out;
    width: auto;
    flex-wrap: nowrap;
    min-width: 830px;
}

.video-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    padding: 5px;
    border: 1px solid transparent;
    position: relative;
    width: 130px;
    flex-shrink: 0;
}

.video-card.active {
    border: 1px solid #4d90fe;
    background-color: #f5f5f5;
}

.video-card:hover {
    background-color: #f9f9f9;
}

.video-name {
    font-size: 12px;
    text-align: center;
}

.video-preview {
    width: 120px;
    height: 90px;
    background: black;
    background-size: cover;
    background-position: center;
    margin-bottom: 5px;
    filter: brightness(1) contrast(1);
}

.all-button {
    bottom: 30px;
    padding: 3px 8px;
    background-color: #f5f5f5;
    border: 1px solid #ccc;
    font-size: 10px;
    cursor: pointer;
    display: block;
}

</style>

</head>
<body>

    <section class="videos-selection">
        <button class="nav-arrow left-arrow">❮</button>

        <div style="
        position: relative;
        overflow: hidden;">
            <div class="videos-list">
                <div class="video-card">
                    <div class="video-preview"></div>
                    <span class="video-name">Vídeo 1</span>
                </div>

                <div class="video-card">
                    <div class="video-preview"></div>
                    <span class="video-name">Vídeo 2</span>
                </div>

                <div class="video-card">
                    <div class="video-preview"></div>
                    <span class="video-name">Vídeo 3</span>
                </div>

                <div class="video-card">
                    <div class="video-preview"></div>
                    <span class="video-name">Vídeo 4</span>
                </div>
                
                <div class="video-card">
                    <div class="video-preview"></div>
                    <span class="video-name">Vídeo 5</span>
                </div>

                <div class="video-card">
                    <div class="video-preview"></div>
                    <span class="video-name">Vídeo 6</span>
                </div>

                <div class="video-card">
                    <div class="video-preview"></div>
                    <span class="video-name">Vídeo 7</span>
                </div>
            </div>
        </div>

        <button class="nav-arrow right-arrow">❯</button>
    </section>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const filtersList = document.querySelector('.videos-list');
    const leftArrow = document.querySelector('.left-arrow');
    const rightArrow = document.querySelector('.right-arrow');
    const filterCards = document.querySelectorAll('.video-card');

    // Largura de um card (130px) + gap (10px) = 140px
    const cardWidthWithGap = 140;
    // Quantos cards queremos mostrar (5)
    const cardsToShow = 5;
    // Posição de rolagem atual (em número de cards)
    let currentScrollPosition = 0;

    // Ajustar a largura do 'videos-list' para mostrar 5 cards de uma vez.
    // Largura ideal: (140px * 5) = 700px. Você precisa adicionar essa largura
    // ao contêiner pai que tem 'overflow: hidden'.
    // No seu CSS, a classe '.videos-selection' deve ser o contêiner de visualização.

    // No entanto, para simplificar, usaremos o 'videos-list' e
    // faremos a rolagem programaticamente.

    /**
     * Atualiza o estado das setas (desabilita/habilita).
     */
    function updateArrows() {
        // Se a posição for 0, não é possível rolar para a esquerda.
        leftArrow.disabled = currentScrollPosition === 0;

        // Se a próxima rolagem exceder o número total de cards menos os 5 visíveis,
        // não é possível rolar mais para a direita.
        const maxScroll = filterCards.length - cardsToShow;
        rightArrow.disabled = currentScrollPosition >= maxScroll;

        // Adiciona um estilo 'disabled' se quiser mais feedback visual
        leftArrow.style.opacity = leftArrow.disabled ? 0.5 : 1;
        rightArrow.style.opacity = rightArrow.disabled ? 0.5 : 1;
    }

    /**
     * Rola a lista de filtros.
     */
    function scrollFilters(direction) {
        // 'direction' será 1 para direita, -1 para esquerda.
        currentScrollPosition += direction;

        // Garante que a posição não seja negativa.
        currentScrollPosition = Math.max(0, currentScrollPosition);
        // Garante que a posição não exceda o máximo.
        currentScrollPosition = Math.min(filterCards.length - cardsToShow, currentScrollPosition);

        // Calcula o valor do deslocamento (em pixels)
        const scrollValue = currentScrollPosition * cardWidthWithGap;

        // Aplica a transformação CSS para mover a lista.
        filtersList.style.left = `-${scrollValue}px`;

        updateArrows();
    }

    // Adicionar ouvintes de evento (event listeners)
    rightArrow.addEventListener('click', () => scrollFilters(1));
    leftArrow.addEventListener('click', () => scrollFilters(-1));

    // Inicializa o estado das setas.
    updateArrows();
});
</script>
</body>
</html>