<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>YouTube 2012 Player Estilo Clássico</title>
<style>
body {
  background: #bd5e5e;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
  margin: 0;
  font-family: Arial, sans-serif;
}

.yt-player {
  width: 640px;
  background: #111;
  border: 1px solid #333;
  border-radius: 4px;
  position: relative;
  box-shadow: 0 0 15px rgba(0,0,0,0.8);
}

.yt-player video,
.yt-player ruffle-embed {
  width: 100%;
  height: 360px;
  display: block;
  background: black;
}

/* Controles */
.yt-controls {
  position: absolute;
  bottom: 0;
  width: 100%;
  height: 36px;
  background: linear-gradient(to top, #0E0E0E, #282828, #494748);
  display: flex;
  align-items: center;
  padding: 0 6px;
  box-sizing: border-box;
  font-size: 12px;
}

.yt-btn {
  background: linear-gradient(to top, #0E0E0E, #282828, #494748);
  width: 28px;
  height: 28px;
  background-size: cover;
  cursor: pointer;
  margin-right: 6px;
  border-radius: 0%;
  border: 1px solid #333;
}

.yt-btn:hover {
  background-color: rgb(158, 158, 158);
}

.yt-progress-container {
  flex: 1;
  height: 42px;
  right: -92px;
  display: flex;
  cursor: pointer;
  margin: 0 -135px;
  position: relative;
}

.yt-progress-bg {
  width: 100%;
  height: 4px;
  background: #555;
  border-radius: 0%;
  position: relative;
}

.yt-progress-buffer {
  height: 100%;
  background: #888;
  width: 0%;
  position: absolute;
  border-radius: 0px;
}

.yt-progress-filled {
  height: 100%;
  background: linear-gradient(to right, #f00, #cc0000);
  width: 0%;
  position: absolute;
  border-radius: 0px;
}

/* Tempo */
.yt-time {
  background: linear-gradient(to top, rgba(0,0,0,0.8), rgba(0,0,0,0.4));
  color: #bcbcbc;
  font-size: 12px;
  font-weight: normal;
  text-align: right;
  border-radius: 7%;
  flex-shrink: 0;
  margin-left: 4px;
}

/* Volume */
.yt-volume-slider {
  width: 70px;
  margin-left: 6px;
  border-radius: 0px;
}

/* Dropdown */
.dropdown {
  position: relative;
}

.dropdown-content {
  display: none;
  position: absolute;
  bottom: 36px;
  background: #222;
  min-width: 100px;
  border: 1px solid #444;
  z-index: 10;
  border-radius: 0px;
}

.dropdown-content div {
  padding: 4px 8px;
  color: #fff;
  cursor: pointer;
}

.dropdown-content div:hover {
  background: #444;
}

/* Botões extras */
.yt-extra-btns {
  display: flex;
  gap: 4px;
  margin-left: 6px;
}
</style>
</head>
<body>

<div class="yt-player">
  <!-- Agora é um vídeo real -->
  <video id="video" src="Video.mp4"></video>

  <div class="yt-controls">
    <div class="yt-btn" id="playPause" 
         style="background-image:url('play.png');"></div>

  div> class="yt-progress-container" id="progressContainer">
      <div class="yt-progress-bg">
        <div class="yt-progress-buffer" id="progressBuffer"></div>
        <div class="yt-progress-filled" id="progressFilled"></div>
      </div>
    </div>

    <div class="yt-time" id="timeDisplay">0:00 / 0:00</div>

    <input type="range" id="volumeSlider" class="yt-volume-slider" min="0" max="1" step="0.01" value="1">

    <div class="yt-extra-btns">
      <div class="dropdown">
        <div class="yt-btn" id="speedBtn" 
             style="background-image:url('https://files.softicons.com/download/toolbar-icons/status-icons-set-by-iconleak/ico/9.ico');"></div>
        <div class="dropdown-content" id="speedMenu">
          <div data-speed="2">2x Speed</div>
          <div data-speed="1.5">1.5x Speed</div>
          <div data-speed="1">Normal Speed</div>
          <div data-speed="0.5">½ Speed</div>
          <div data-speed="0.25">¼ Speed</div>
        </div>
      </div>
      <div class="yt-btn" id="annoBtn" 
           style="background-image:url('https://upload.wikimedia.org/wikipedia/commons/1/12/YouTube_annotations_icon.png');"></div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/@ruffle-rs/ruffle"></script>

<script>
const video = document.getElementById('video');
const playPauseBtn = document.getElementById('playPause');
const progressContainer = document.getElementById('progressContainer');
const progressFilled = document.getElementById('progressFilled');
const progressBuffer = document.getElementById('progressBuffer');
const timeDisplay = document.getElementById('timeDisplay');
const volumeSlider = document.getElementById('volumeSlider');

// Play/Pause
playPauseBtn.addEventListener('click', () => {
  if(video.paused) video.play();
  else video.pause();
});
video.addEventListener('play', () => {
  playPauseBtn.style.backgroundImage = "url('play.png')";
});
video.addEventListener('pause', () => {
  playPauseBtn.style.backgroundImage = "url('play.png')";
});

// Progress
video.addEventListener('timeupdate', () => {
  const percent = (video.currentTime / video.duration) * 100;
  progressFilled.style.width = percent + '%';
  timeDisplay.textContent = formatTime(video.currentTime) + ' / ' + formatTime(video.duration);
});

// Buffer simulation
video.addEventListener('progress', () => {
  if(video.buffered.length > 0){
    const bufferedEnd = video.buffered.end(video.buffered.length - 1);
    const percent = (bufferedEnd / video.duration) * 100;
    progressBuffer.style.width = percent + '%';
  }
});

// Seek
progressContainer.addEventListener('click', (e) => {
  const rect = progressContainer.getBoundingClientRect();
  const clickX = e.clientX - rect.left;
  video.currentTime = (clickX / rect.width) * video.duration;
});

// Volume
volumeSlider.addEventListener('input', () => {
  video.volume = volumeSlider.value;
});

// Speed dropdown
const speedBtn = document.getElementById('speedBtn');
const speedMenu = document.getElementById('speedMenu');
speedBtn.addEventListener('click', () => {
  speedMenu.style.display = speedMenu.style.display === 'block' ? 'none' : 'block';
});
speedMenu.querySelectorAll('div').forEach(item => {
  item.addEventListener('click', () => {
    video.playbackRate = parseFloat(item.dataset.speed);
    speedMenu.style.display = 'none';
  });
});

// Annotations button
const annoBtn = document.getElementById('annoBtn');
annoBtn.addEventListener('click', () => alert("Annotations toggled!"));

// Time format
function formatTime(seconds) {
  const min = Math.floor(seconds / 60);
  const sec = Math.floor(seconds % 60);
  return min + ':' + (sec < 10 ? '0' : '') + sec;
}
</script>

</body>
</html>
