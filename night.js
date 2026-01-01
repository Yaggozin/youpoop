function aplicarModoNoite() {
    const dataHoraBrasil = new Date().toLocaleString('en-US', {
        timeZone: 'America/Sao_Paulo',
        hour: 'numeric',
        hour12: false
    });

    const hora = parseInt(dataHoraBrasil.split(' ')[0]);

    const horaInicioNoite = 18;
    const horaFimNoite = 6;

    let eNoite;
    if (hora >= horaInicioNoite || hora < horaFimNoite) {
        eNoite = true;
    } else {
        eNoite = false;
    }

    const elementoBody = document.body;

    if (eNoite) {
        elementoBody.classList.add('night-mode');
    } else {
        elementoBody.classList.remove('night-mode');
    }
}

aplicarModoNoite();

setInterval(aplicarModoNoite, 60 * 60 * 1000);
