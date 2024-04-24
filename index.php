<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <!--для отображения изображения с камеры-->
    <video id="video" width="640" height="480" autoplay></video>
    <canvas id="qrcode"></canvas>
</body>
<script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('qrcode');
    const context = canvas.getContext('2d');

    const img = new Image();
    img.onload = drawImageActualSize;
    img.src = 'img3.jpg';

    function drawImageActualSize() {
        canvas.width = this.naturalWidth;
        canvas.height = this.naturalHeight;
        context.drawImage(this, 0, 0);
        // context.drawImage(this, 0, 0, this.width, this.height);
    }


    navigator.mediaDevices.getUserMedia({
            video: true
        })
        .then(stream => {
            video.srcObject = stream;
            video.play();

            // Draw the video frame onto the canvas every 100ms
            setInterval(() => {
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
            }, 2000);
        })
        .catch(error => {
            console.log('Error accessing camera:', error);
        });
</script>

</html>