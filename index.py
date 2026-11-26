import os
from flask import Flask, request, jsonify, render_template_string
import numpy as np
from PIL import Image
import io


import tflite_runtime.interpreter as tflite

app = Flask(__name__)


current_dir = os.path.dirname(os.path.abspath(__file__))

MODEL_PATH = os.path.join(
    current_dir,
    'models',
    'model_klasifikasi_buah.tflite'
)

IMG_SIZE = 160
labels = ['Apple', 'Banana', 'Guava', 'Orange', 'Lime', 'Pomegranate']

interpreter = None
input_details = None
output_details = None

HTML_INTERFACE = """
<!DOCTYPE html>
<html>
<head>
    <title>FruitSense Server</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; text-align: center; padding: 40px; background-color: #f4f4f9; }
        .container { max-width: 500px; margin: auto; padding: 30px; background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 10px; }
        p { color: #666; }
        input[type=file] { margin: 20px 0; padding: 10px; border: 1px dashed #ccc; width: 80%; }
        button { background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; transition: 0.3s; }
        button:hover { background-color: #218838; }
        .footer { margin-top: 20px; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🍎 FruitSense AI Server</h1>
        <p>Server klasifikasi buah aktif. Silakan upload gambar.</p>
        <form action="/classify" method="post" enctype="multipart/form-data">
            <input type="file" name="image" accept="image/*" required>
            <br>
            <button type="submit">🔍 Mulai Prediksi</button>
        </form>
    </div>
    <div class="footer">Powered by Flask & TFLite Runtime</div>
</body>
</html>
"""

def load_model():
    """Download model dari URL dan load ke Memori"""
    global interpreter, input_details, output_details
    
    if interpreter is None:
        print(f"⬇️ Sedang mendownload model dari: {MODEL_PATH} ...")
        try:
            if not os.path.exists(MODEL_PATH):
                raise FileNotFoundError(
                    f"Model tidak ditemukan: {MODEL_PATH}"
                )

            interpreter = tflite.Interpreter(model_path=MODEL_PATH)
            interpreter.allocate_tensors()

            input_details = interpreter.get_input_details()
            output_details = interpreter.get_output_details()

            print("✅ Model berhasil dimuat.")
                
        except Exception as e:
            print(f"❌ Error saat load model dari URL: {e}")
            interpreter = "ERROR"

@app.route('/', methods=['GET'])
def index():
    return render_template_string(HTML_INTERFACE)

@app.route('/classify', methods=['POST'])
def classify_image():
    if interpreter is None:
        load_model()
    
    if interpreter == "ERROR" or interpreter is None:
        return jsonify({'error': 'Server Error: Model gagal dimuat.'}), 500

    # Cek file upload
    if 'image' not in request.files:
        return jsonify({'error': 'Tidak ada file gambar dikirim.'}), 400

    file = request.files['image']
    if file.filename == '':
        return jsonify({'error': 'Nama file kosong.'}), 400
    
    try:
        # 1. BACA & PROSES GAMBAR
        img_bytes = file.read()
        image = Image.open(io.BytesIO(img_bytes)).convert('RGB')
        
        # Resize sesuai input model (160x160)
        image = image.resize((IMG_SIZE, IMG_SIZE))
        
        # Normalisasi (0-255 menjadi 0.0-1.0) ubah ke float32
        img_array = np.array(image, dtype=np.float32) / 255.0
        
        # Tambah dimensi batch (dari (160,160,3) jadi (1,160,160,3))
        img_array = np.expand_dims(img_array, axis=0)

        # PROSES PREDIKSI (INFERENCE)
        # Set data ke input tensor
        interpreter.set_tensor(input_details[0]['index'], img_array)
        
        # Jalankan prediksi
        interpreter.invoke()
        
        # Ambil hasil dari output tensor
        predictions = interpreter.get_tensor(output_details[0]['index'])
        
        # HASIL (Softmax vs Sigmoid)
        if len(predictions[0]) > 1:
            result_index = np.argmax(predictions[0])
            confidence = float(predictions[0][result_index])
        else:
            # Jika outputnya hanya 1 angka (Binary Class)
            probability = predictions[0][0]
            if probability > 0.5:
                result_index = 1 
                confidence = probability
            else:
                result_index = 0
                confidence = 1.0 - probability

        # label
        if result_index < len(labels):
            predicted_class = labels[result_index]
        else:
            predicted_class = "Unknown"

        # sent RESPONSE JSON
        return jsonify({
            'success': True,
            'prediction': predicted_class,
            'confidence': float(confidence),
            'confidence_percentage': f"{float(confidence) * 100:.2f}%"
        })

    except Exception as e:
        print(f"Error saat klasifikasi: {e}")
        return jsonify({'error': str(e)}), 500
if __name__ == '__main__':
  
    print("🚀 Menjalankan FruitSense Server...")
    app.run(host='0.0.0.0', port=5001, debug=True)