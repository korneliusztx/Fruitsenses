<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FruitSense AI Server · PHP</title>
    <style>
        :root { color-scheme: light; font-family: 'Segoe UI', Tahoma, sans-serif; }
        * {box-sizing: border-box}
        body {background:#f4f4f9;min-height:100vh;margin:0;display:grid;place-items:center;padding:32px 16px;color:#30313a}
        .container {width:100%;max-width:525px;background:white;padding:32px;border-radius:17px;box-shadow:0 8px 30px #202a3612}
        h1 {margin:0 0 9px;font-size:27px} p {color:#666d77;line-height:1.6;margin:0 0 24px}
        .upload {border:2px dashed #b9d8c5;border-radius:12px;padding:18px;background:#f9fefb;text-align:center}
        input[type=file] {max-width:100%;font-size:14px}
        button {border:0;color:white;background:#28a745;border-radius:9px;padding:13px 23px;cursor:pointer;font-size:15px;font-weight:700;width:100%;margin-top:16px}
        button:hover {background:#218838} button:disabled {opacity:.65;cursor:progress}
        .result {display:none;background:#f2fbf5;border-radius:10px;border:1px solid #d7efde;margin-top:20px;padding:16px;word-break:break-word}
        .result.error {background:#fff6f6;border-color:#fac4c4}
        .result h2 {margin:0 0 7px;font-size:17px}
        .result strong {color:#12682c}
        .result.error strong {color:#b42318}
        .footer {text-align:center;color:#a1a3ac;font-size:12px;margin-top:23px}
        small {display:block;margin-top:9px;color:#73837a}
        .preview {max-width:100%;max-height:220px;object-fit:contain;border-radius:10px;margin:14px auto 0;display:none}
        .score {display:flex;justify-content:space-between;border-bottom:1px solid #e0eae3;padding:5px 0;font-size:14px}
    </style>
</head>
<body>
<main class="container">
    <h1>🍎 FruitSense AI Server</h1>
    <p>Server klasifikasi buah menggunakan <strong>PHP + TensorFlow Lite</strong>. Silakan unggah gambar untuk mencoba model.</p>
    <form id="form" action="./classify.php" method="post" enctype="multipart/form-data">
        <div class="upload">
            <label for="image"><strong>Pilih foto buah</strong></label>
            <div style="margin-top:13px"><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" required></div>
            <img class="preview" id="preview" alt="Pratinjau gambar">
            <small>JPG / PNG / WEBP • Maksimal 5 MB</small>
        </div>
        <button id="submit" type="submit">🔍 Mulai Prediksi</button>
    </form>
    <div id="result" class="result" role="status" aria-live="polite">
        <h2 id="resultTitle"></h2>
        <div id="resultContents"></div>
    </div>
    <div class="footer">Powered by PHP FFI &amp; TensorFlow Lite C API · Tanpa login web</div>
</main>
<script>
    const form = document.getElementById('form');
    const fileInput = document.getElementById('image');
    const preview = document.getElementById('preview');
    const button = document.getElementById('submit');
    const result = document.getElementById('result');
    const resultTitle = document.getElementById('resultTitle');
    const resultContents = document.getElementById('resultContents');
    fileInput.addEventListener('change', () => {
        result.style.display = 'none';
        const file = fileInput.files[0];
        if (!file) { preview.style.display = 'none'; return; }
        preview.src = URL.createObjectURL(file);
        preview.onload = () => URL.revokeObjectURL(preview.src);
        preview.style.display = 'block';
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const file = fileInput.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) { showError('Ukuran file maksimal 5 MB.'); return; }
        button.disabled = true;
        button.textContent = '⏳ Memproses model...';
        result.style.display = 'none';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json' },
                redirect: 'follow'
            });
            const raw = await response.text();
            const contentType = (response.headers.get('content-type') || '').toLowerCase();
            let json;
            try {
                json = JSON.parse(raw);
            } catch (_) {
                const html = /^\s*<(?:!doctype|html|head|body|br|b|div|p)\b/i.test(raw);
                const clue = html
                    ? 'Server mengembalikan HTML, bukan JSON. Periksa apakah classify.php tersedia dan dieksekusi oleh PHP (bukan dialihkan ke Python/Gunicorn atau halaman 404 hosting).'
                    : 'Respons bukan JSON valid. Periksa output dan error log PHP.';
                throw new Error(
                    `HTTP ${response.status}. ${clue} URL: ${response.url}. Content-Type: ${contentType || 'tidak diketahui'}.`
                );
            }
            if (!response.ok || json.success !== true) {
                throw new Error(`HTTP ${response.status}: ${json.error || json.message || 'Prediksi gagal.'}`);
            }
            result.classList.remove('error');
            resultTitle.textContent = '✅ Hasil Klasifikasi';
            resultContents.replaceChildren();
            const pred = document.createElement('p');
            pred.style.margin = '0 0 7px';
            pred.append('Buah: ');
            const strong = document.createElement('strong');
            strong.textContent = json.prediction || 'Unknown';
            pred.appendChild(strong);
            const conf = document.createElement('p');
            conf.style.margin = '0 0 10px';
            conf.textContent = 'Confidence: ' + (json.confidence_percentage || 'N/A');
            resultContents.append(pred, conf);
            for (const [label, score] of Object.entries(json.probabilities || {})) {
                const row = document.createElement('div');
                row.className = 'score';
                const title = document.createElement('span');
                title.textContent = label;
                const value = document.createElement('span');
                value.textContent = (Number(score) * 100).toFixed(2) + '%';
                row.append(title, value);
                resultContents.appendChild(row);
            }
            result.style.display = 'block';
        } catch (error) { showError(error.message || 'Terjadi kesalahan.'); }
        finally {button.disabled = false; button.textContent = '🔍 Mulai Prediksi';}
    });
    function showError(message) {
        result.classList.add('error');
        resultTitle.textContent = '❌ Prediksi tidak berhasil';
        resultContents.textContent = message;
        result.style.display = 'block';
    }
</script>
</body>
</html>
