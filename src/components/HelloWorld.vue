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
      <video id="zxing-video" ref="videoElement" autoplay playsinline></video>
      <div class="scan-box"></div>
      <div class="camera-controls">
        <div
          v-if="cameras.length > 1"
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
            <div class="camera-details">{{ getCameraDetails(camera) }}</div>
          </div>
        </div>
        <div class="modal-buttons">
          <button @click="showCameraSelection = false">Отмена</button>
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
          <button @click="submitManualCode">Отправить</button>
        </div>
      </div>
    </div>

    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <div class="modal-text" :style="{ color: modalTextColor }">
          {{ modalMessage }}
        </div>
        <div class="modal-buttons">
          <button @click="showModal = false">Ок</button>
        </div>
      </div>
    </div>

    <div v-if="fileError" class="file-error">{{ fileError }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from "vue";
import {
  BrowserMultiFormatReader,
  DecodeHintType
} from '@zxing/library';

const formats = [
  // Добавьте нужные форматы
  DecodeHintType.QR_CODE,
  DecodeHintType.EAN_13,
  DecodeHintType.CODE_128,
  DecodeHintType.DATA_MATRIX,
  DecodeHintType.AZTEC,
];

// Создаем читателя
const codeReader = new BrowserMultiFormatReader(formats);

// --- Состояния ---
const CAMERA_PERMISSION_TIMEOUT = 5 * 60 * 1000;
let cameraPermissionTimeout = null;

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
const videoElement = ref(null);
const cameras = ref([]);
const showModal = ref(false);
const modalMessage = ref("");
const modalTextColor = ref("#00c48c");
const showCameraSelection = ref(false);

// --- Lifecycle ---
onMounted(async () => {
  await getCameras();
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
  await stopScanner();
  if (cameraPermissionTimeout) clearTimeout(cameraPermissionTimeout);
});

// --- Функции работы с камерой ---
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
    const devices = await codeReader.listVideoInputDevices();
    cameras.value = devices;
    return devices;
  } catch (err) {
    console.error("Ошибка получения камер:", err);
    return [];
  }
};

const getCameraName = (camera) => {
  let name = camera.label || "Камера";
  name = name.replace(/\([^)]*\)/g, "").trim();
  if (name.toLowerCase().includes("back") || name.toLowerCase().includes("rear")) {
    return "Основная камера";
  } else if (name.toLowerCase().includes("front")) {
    return "Фронтальная камера";
  }
  return name;
};

const getCameraDetails = (camera) => {
  const resolutionMatch = camera.label.match(/\d+x\d+/);
  const resolution = resolutionMatch ? resolutionMatch[0] : "";
  let cameraType = "";
  if (camera.label.includes("0.5x") || camera.label.includes("ultrawide")) {
    cameraType = "0.5x (широкоугольная)";
  } else if (camera.label.includes("1x")) {
    cameraType = "1x (стандартная)";
  } else if (camera.label.includes("2x") || camera.label.includes("telephoto")) {
    cameraType = "2x (телефото)";
  }
  return [resolution, cameraType].filter(Boolean).join(" · ");
};

const selectCamera = async (cameraId) => {
  selectedCameraId.value = cameraId;
  saveCameraAccess(cameraId);
  showCameraSelection.value = false;
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

    const stream = await navigator.mediaDevices.getUserMedia({
      video: {
        deviceId: selectedCameraId.value ? { exact: selectedCameraId.value } : undefined,
        facingMode: "environment",
      },
    });

    if (videoElement.value) {
      videoElement.value.srcObject = stream;
      await new Promise((resolve) => (videoElement.value.onloadedmetadata = resolve));
      videoElement.value.play();
    }

    // Начинаем сканирование
    codeReader.decodeFromVideoDevice(
      selectedCameraId.value,
      "zxing-video",
      onScanSuccess
    );
  } catch (err) {
    console.error("Ошибка запуска камеры:", err);
    showManualInput.value = true;
    stopScanner();
  } finally {
    isScanning.value = false;
  }
};

const stopScanner = async () => {
  if (codeReader) codeReader.reset();
  if (videoElement.value && videoElement.value.srcObject) {
    const stream = videoElement.value.srcObject;
    const tracks = stream.getTracks();
    tracks.forEach((track) => track.stop());
    videoElement.value.srcObject = null;
  }
  cameraActive.value = false;
};

const toggleTorch = async () => {
  if (!torchSupported.value) return;
  const track = videoElement.value?.srcObject?.getVideoTracks()[0];
  if (!track) return;

  try {
    const capabilities = track.getCapabilities();
    if (capabilities.torch) {
      torchActive.value = !torchActive.value;
      await track.applyConstraints({
        advanced: [{ torch: torchActive.value }],
      });
    }
  } catch (err) {
    console.error("Ошибка управления фонариком:", err);
    torchSupported.value = false;
  }
};

// --- Загрузка файла ---
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

    const img = await loadImageFromFile(file);
    const result = await codeReader.decodeFromImage(undefined, img);

    if (result) {
      onScanSuccess(result.text);
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

const loadImageFromFile = (file) =>
  new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = reject;
      img.src = e.target.result;
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });

// --- Обработка результатов ---
let isProcessing = false;
const onScanSuccess = async (decodedText) => {
  if (isProcessing) return;
  isProcessing = true;
  scanResult.value = decodedText;
  showModal.value = true;
  modalMessage.value = `Отсканирован QR-код:\n${decodedText}`;
  modalTextColor.value = "#00c48c";
  stopScanner();
  setTimeout(() => {
    isProcessing = false;
  }, 2000);
};

// --- Ручной ввод ---
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
#zxing-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.scan-box {
  width: 80vw;
  height: 80vw;
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  border: 2px dashed #fff;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
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
  word-break: break-all;
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
</style>