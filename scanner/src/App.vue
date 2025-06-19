<script setup>
import HelloWorld from './components/HelloWorld.vue'
</script>

<template>
  <HelloWorld />
</template>

<style lang="scss">
@import "./styles/global.scss";

.content {
  margin: 0 auto;
  padding: 8vw 2vw 40vw;
  margin-top: 15vw;

  &.content-no-navbar {
    padding: 0 2vw;
    margin-top: 24vw;
  }

  &.content-no-header {
    margin-top: 0;
  }
}

.spinner {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}

.dot {
  width: 12px;
  height: 12px;
  margin: 0 4px;
  background: #42b883;
  border-radius: 50%;
  animation: bounce 0.6s infinite alternate;
}

.dot:nth-child(1) {
  animation-delay: 0s;
}

.dot:nth-child(2) {
  animation-delay: 0.2s;
}

.dot:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes bounce {
  from {
    transform: translateY(0);
  }

  to {
    transform: translateY(-10px);
  }
}

.contact-prompt {
  display: flex;
  flex-direction: column;
  align-items: center;
  // justify-content: center;
  height: 100vh;
  text-align: center;
  padding: 20px;
  background-color: #fff;
  z-index: 9999;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;

  .logo {
    width: 100px;
    height: 100px;
    margin-bottom: 20px;
  }

  p {
    font-size: 18px;
    color: #333;
  }
}

@media (min-width: 768px) or (orientation: landscape) {
  .content {
    max-width: 390px;
    margin: 86px auto 0;
    padding: 32px 8px 160px;

    &.content-no-navbar {
      padding: 0 8px;
      margin-top: 96px;
    }
  }
}
</style>