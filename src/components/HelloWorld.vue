<template>
  <div class="qr-scanner-container">
    <div class="scanner-controls">
      <div @click="startCameraScan" :class="{ disabled: isScanning }">
        {{ cameraActive ? "Сканирование..." : "Сканировать" }}
      </div>
      <div @click="triggerFileInput" :class="{ disabled: isScanning }">
        Выбрать из галереи
      </div>
      <div @click="showManualInput = true" :class="{ disabled: isScanning }">
        Ввести вручную
      </div>
    </div>

    <div v-if="cameraActive" class="camera-view">
      <div id="qr-scanner" class="scanner-area"></div>
      <div class="camera-controls">
        <div
          v-if="cameras.length >= 1"
          class="control-button"
          @click="showCameraSelection = true"
        >
          Сменить камеру
        </div>
        <div
          v-if="torchSupported"
          class="control-button"
          @click="toggleTorch"
          :class="{ active: torchActive }"
        >
          {{ torchActive ? "Выкл. свет" : "Вкл. свет" }}
        </div>
        <div class="control-button close-button" @click="stopScanner">
          Закрыть
        </div>
      </div>
    </div>

    <div v-if="showCameraSelection" class="modal-overlay">
      <div class="modal-content camera-selection">
        <h3>Выберите камеру</h3>
        <div class="camera-list">
          <div
            v-for="camera in cameras"
            :key="camera.id"
            class="camera-item"
            @click="selectCamera(camera.id)"
            :class="{ active: selectedCameraId === camera.id }"
          >
            <div class="camera-name">{{ getCameraName(camera) }}</div>
            <div class="camera-details">
              {{ getCameraDetails(camera) }}
            </div>
          </div>
        </div>
        <div class="modal-buttons">
          <Button label="Отмена" @click="showCameraSelection = false" />
        </div>
      </div>
    </div>

    <input
      type="file"
      ref="fileInput"
      accept="image/*"
      style="display: none"
      @change="handleFileUpload"
    />

    <div
      v-if="showManualInput"
      class="modal-overlay"
      @click.self="showManualInput = false"
    >
      <div class="modal-content">
        <h3>Не удалось отсканировать?</h3>
        <p>Введите символы рядом с QR</p>
        <input
          type="text"
          v-model="manualCode"
          placeholder="qr123456"
          @keyup.enter="submitManualCode"
        />
        <div class="modal-buttons">
          <Button label="Отправить" @click="submitManualCode" :submit="true" />
        </div>
      </div>
    </div>

    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <div class="modal-text">{{ modalMessage }}</div>
        <div class="modal-buttons">
          <Button label="Ок" @click="showModal = false" :submit="true" />
        </div>
      </div>
    </div>

    <div v-if="fileError" class="file-error">{{ fileError }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from "vue";
import { Html5Qrcode } from "html5-qrcode";
import Button from "@/components/Button.vue";

// === Состояния ===
const CAMERA_PERMISSION_TIMEOUT = 5 * 60 * 1000;
let cameraPermissionTimeout = null;

const html5Qrcode = ref(null);
const isScanning = ref(false);
const showManualInput = ref(false);
const manualCode = ref("");
const scanResult = ref("");
const cameraActive = ref(false);
const torchActive = ref(false);
const torchSupported = ref(false);
const selectedCameraId = ref(null);
const lastCameraAccess = ref(null);
const fileError = ref("");
const fileInput = ref(null);
const cameras = ref([]);
const showCameraSelection = ref(false);

const props = {
  qrbox: 250,
  fps: 10,
};

onMounted(() => {
  window.addEventListener("resize", handleResize);

  const savedAccess = localStorage.getItem("cameraAccess");
  if (savedAccess) {
    const { cameraId, timestamp } = JSON.parse(savedAccess);
    if (Date.now() - timestamp < CAMERA_PERMISSION_TIMEOUT) {
      selectedCameraId.value = cameraId;
      lastCameraAccess.value = timestamp;
    } else {
      localStorage.removeItem("cameraAccess");
    }
  }
});

onUnmounted(async () => {
  window.removeEventListener("resize", handleResize);

  await stopScanner();
  if (cameraPermissionTimeout) clearTimeout(cameraPermissionTimeout);
});

const handleResize = async () => {
  if (html5Qrcode.value?.isScanning) {
    const viewportWidth = Math.min(window.innerWidth, window.innerHeight) - 40;
    const qrboxSize = Math.min(viewportWidth, props.qrbox);
    html5Qrcode.value.updateScannerConfig({
      qrbox: { width: qrboxSize, height: qrboxSize },
    });
  }
};

// === Функции работы с камерой ===
const saveCameraAccess = (cameraId) => {
  const accessData = {
    cameraId,
    timestamp: Date.now(),
  };
  localStorage.setItem("cameraAccess", JSON.stringify(accessData));
  lastCameraAccess.value = accessData.timestamp;
  if (cameraPermissionTimeout) clearTimeout(cameraPermissionTimeout);
  cameraPermissionTimeout = setTimeout(() => {
    localStorage.removeItem("cameraAccess");
    lastCameraAccess.value = null;
    selectedCameraId.value = null;
  }, CAMERA_PERMISSION_TIMEOUT);
};

const getCameras = async () => {
  try {
    const availableCameras = await Html5Qrcode.getCameras();
    cameras.value = availableCameras;
    return availableCameras;
  } catch (error) {
    console.error("Ошибка получения камер:", error);
    return [];
  }
};

const getCameraName = (camera) => {
  if (!camera.label) return "Камера";
  
  // Удаляем лишнюю информацию в скобках
  let name = camera.label.replace(/\([^)]*\)/g, '').trim();
  
  // Упрощаем стандартные названия
  if (name.includes('back') || name.toLowerCase().includes('rear')) {
    return "Основная камера";
  } else if (name.includes('front')) {
    return "Фронтальная камера";
  }
  
  return name || "Камера";
};

const getCameraDetails = (camera) => {
  if (!camera.label) return "";
  
  // Извлекаем разрешение из названия камеры
  const resolutionMatch = camera.label.match(/\d+x\d+/);
  const resolution = resolutionMatch ? resolutionMatch[0] : "";
  
  // Определяем тип камеры (1x, 0.5x и т.д.)
  let cameraType = "";
  if (camera.label.includes('0.5x') || camera.label.includes('ultrawide')) {
    cameraType = "0.5x (широкоугольная)";
  } else if (camera.label.includes('1x')) {
    cameraType = "1x (стандартная)";
  } else if (camera.label.includes('2x') || camera.label.includes('telephoto')) {
    cameraType = "2x (телефото)";
  }
  
  return [resolution, cameraType].filter(Boolean).join(" · ");
};

const selectCamera = async (cameraId) => {
  selectedCameraId.value = cameraId;
  saveCameraAccess(cameraId);
  showCameraSelection.value = false;
  
  // Перезапускаем сканер с новой камерой
  await stopScanner();
  await startCameraScan();
};

const startCameraScan = async () => {
  if (isScanning.value || cameraActive.value) return;
  try {
    isScanning.value = true;
    cameraActive.value = true;
    scanResult.value = "";
    torchActive.value = false;
    
    await nextTick();
    
    if (!html5Qrcode.value) {
      html5Qrcode.value = new Html5Qrcode("qr-scanner");
    }
    
    const viewportWidth = Math.min(window.innerWidth, window.innerHeight) - 40;
    const qrboxSize = Math.min(viewportWidth, props.qrbox);
    const config = {
      fps: props.fps,
      qrbox: { width: qrboxSize, height: qrboxSize },
      disableFlip: true,
      videoConstraints: {
        facingMode: "environment",
        zoom: 1.0,
      },
      formatsToSupport: [
        Html5Qrcode.QR_CODE,
        Html5Qrcode.AZTEC,
        Html5Qrcode.CODE_128,
        Html5Qrcode.DATA_MATRIX,
      ],
      ignoreIfStillFor: 2,
    };
    
    // Получаем список доступных камер
    const availableCameras = await getCameras();
    let cameraId = selectedCameraId.value;

    // Если камера не выбрана, пытаемся выбрать оптимальную
    if (!cameraId && availableCameras.length > 0) {
      // Сначала ищем тыльную камеру с максимальным разрешением
      const rearCameras = availableCameras.filter(cam => 
        cam.label.toLowerCase().includes('back') || 
        cam.label.toLowerCase().includes('rear')
      );
      
      if (rearCameras.length > 0) {
        // Сортируем по разрешению (предполагая, что оно указано в названии)
        rearCameras.sort((a, b) => {
          const getResolution = (label) => {
            const match = label.match(/\d+x\d+/);
            if (!match) return 0;
            const [w, h] = match[0].split('x').map(Number);
            return w * h;
          };
          return getResolution(b.label) - getResolution(a.label);
        });
        
        cameraId = rearCameras[0].id;
      } else {
        // Если тыльных камер нет, берем первую
        cameraId = availableCameras[0].id;
      }
      
      selectedCameraId.value = cameraId;
      saveCameraAccess(cameraId);
    }

    await html5Qrcode.value.start(
      cameraId,
      config,
      onScanSuccess,
      onScanFailure
    );

    // Проверка поддержки фонарика
    try {
      const hasFlash = await html5Qrcode.value.hasFlashSupport();
      torchSupported.value = hasFlash;
    } catch {
      torchSupported.value = false;
    }
  } catch (err) {
    console.error("Ошибка камеры:", err);
    showManualInput.value = true;
    stopScanner();
  } finally {
    isScanning.value = false;
  }
};

const stopScanner = async () => {
  if (html5Qrcode.value?.isScanning) {
    try {
      if (torchActive.value) {
        await html5Qrcode.value.turnOffFlash();
      }
      await html5Qrcode.value.stop();
    } catch (err) {
      console.error("Ошибка остановки сканера:", err);
    }
  }
  cameraActive.value = false;
  torchActive.value = false;
};

const toggleTorch = async () => {
  if (!html5Qrcode.value || !torchSupported.value) return;
  try {
    if (torchActive.value) {
      await html5Qrcode.value.turnOffFlash();
    } else {
      await html5Qrcode.value.turnOnFlash();
    }
    torchActive.value = !torchActive.value;
  } catch (err) {
    console.error("Ошибка фонарика:", err);
    torchSupported.value = false;
  }
};

// === Сканирование файла ===
const triggerFileInput = () => {
  fileInput.value.click();
};

const handleFileUpload = async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  try {
    isScanning.value = true;
    scanResult.value = "";
    fileError.value = "";

    // Создаём временный контейнер
    const tempScannerDiv = document.createElement("div");
    tempScannerDiv.id = "qr-scanner";
    tempScannerDiv.style.display = "none";
    document.body.appendChild(tempScannerDiv);

    const html5QrCodeReader = new Html5Qrcode("qr-scanner");
    const decodedText = await html5QrCodeReader.scanFile(file, false);

    // Убираем временный элемент
    document.body.removeChild(tempScannerDiv);

    if (decodedText) {
      onScanSuccess(decodedText);
    } else {
      fileError.value = "QR-код не найден на изображении.";
      showManualInput.value = true;
    }
  } catch (err) {
    console.error("Ошибка при сканировании файла:", err);
    fileError.value = "Не удалось прочитать файл.";
    showManualInput.value = true;
  } finally {
    isScanning.value = false;
    event.target.value = "";
  }
};

let isProcessing = false;

const onScanSuccess = async (decodedText) => {
  if (isProcessing) return;
  isProcessing = true;

  scanResult.value = decodedText;
  const normalizedCode = decodedText.trim().toLowerCase();

  showModal.value = true;
  modalMessage.value = `Отсканирован QR-код:\n${decodedText}`;
  modalTextColor.value = "#00c48c";

  stopScanner();
  setTimeout(() => {
    isProcessing = false;
  }, 2000);
};

const onScanFailure = (errorMessage) => {
  console.warn(`Ошибка сканирования: ${errorMessage}`);
};

// === Ручной ввод ===
const submitManualCode = () => {
  const inputCode = manualCode.value.trim();
  if (!inputCode) return;

  showModal.value = true;
  modalMessage.value = `Введён код:\n${inputCode}`;
  modalTextColor.value = "#00c48c";

  showManualInput.value = false;
  manualCode.value = "";
};
</script>

<style scoped>
.qr-scanner-container {
  max-width: 600px;
  margin: 0 auto;
  padding: 20px;
}

.scanner-controls {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.scanner-controls div {
  width: 100%;
  padding: 2vw 4vw;
  background: transparent;
  border: none;
  border-bottom: 1px solid var(--border-light);
  margin-bottom: 8vw;
  font-family: RoobertBold, sans-serif;
  font-size: 9.6vw;
  line-height: 109%;
  user-select: none;
}

.scanner-controls div.disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.camera-view {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: black;
  z-index: 1000;
  display: flex;
  flex-direction: column;
}

.scanner-area {
  flex-grow: 1;
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  position: relative;
}

.scanner-area::before {
  content: "";
  display: block;
  padding-top: 100%; /* Сохраняет соотношение сторон 1:1 */
  position: relative;
}

#qr-scanner {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  overflow: hidden; /* Предотвращает растягивание */
}

.scanner-area video {
  width: 100%;
  height: 100%;
  object-fit: cover; /* Сохраняет пропорции видео */
}

.camera-controls {
  display: flex;
  justify-content: center;
  gap: 20px;
  padding: 15px;
  background: rgba(0, 0, 0, 0.7);
}

.control-button {
  padding: 10px 20px;
  background: rgba(255, 255, 255, 0.2);
  color: white;
  border-radius: 20px;
  cursor: pointer;
}

.control-button.active {
  background: rgba(255, 255, 255, 0.4);
}

.close-button {
  background: rgba(255, 68, 68, 0.8);
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1001;
}

.modal-content {
  background: white;
  padding: 20px;
  border-radius: 12px;
  width: 85%;
  max-width: 400px;
  text-align: center;
}

.modal-content.camera-selection {
  max-width: 90%;
  width: 90%;
}

.camera-list {
  max-height: 60vh;
  overflow-y: auto;
  margin: 15px 0;
}

.camera-item {
  padding: 12px;
  border-bottom: 1px solid #eee;
  cursor: pointer;
}

.camera-item:hover {
  background-color: #f5f5f5;
}

.camera-item.active {
  background-color: #e0f7fa;
}

.camera-name {
  font-weight: bold;
  margin-bottom: 4px;
}

.camera-details {
  font-size: 0.9em;
  color: #666;
}

.modal-text {
  white-space: pre-wrap;
  text-align: center;
  margin-bottom: 16px;
  color: v-bind('modalTextColor || "inherit"');
  font-size: 20px;
}

.modal-content input {
  width: 100%;
  margin: 15px 0;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 16px;
}

.file-error {
  color: red;
  margin-top: 10px;
  text-align: center;
}

.scan-result {
  margin-top: 20px;
  padding: 15px;
  background: #f0f8ff;
  border-radius: 8px;
  border: 1px solid #d0e0ff;
  text-align: center;
}

.scan-result h3 {
  margin-top: 0;
  color: #0066cc;
}

@media (min-width: 768px) or (orientation: landscape) {
  .scanner-controls div {
    width: 100%;
    padding: 8px 16px;
    background: transparent;
    border: none;
    border-bottom: 1px solid var(--border-light);
    margin-bottom: 32px;
    font-family: RoobertBold, sans-serif;
    font-size: 38px;
    line-height: 109%;
    user-select: none;
  }
}
</style>