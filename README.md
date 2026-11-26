# FruitSenses — PHP + TensorFlow Lite (tanpa Flask dan tanpa login web)

Project ini menggantikan `app.py` Flask dari pengguna:

- `GET /` → halaman upload seperti `HTML_INTERFACE` Python semula (`index.php`).
- `POST /classify` → menerima `multipart/form-data` dengan field **`image`**, menjalankan model TFLite via **PHP FFI + native TensorFlow Lite C API**, lalu mengembalikan JSON.
- **Tidak ada halaman login, database user, atau password pada web.** Login **tetap di Flutter**, tidak diubah.

> **Perhatian:** PHP tidak memiliki fungsi bawaan untuk memuat file `.tflite`. PHP FFI membutuhkan library TensorFlow Lite native yang sesuai sistem operasi. Paket ini menyediakan **source PHP**, tetapi **belum menyertakan** file model `.tflite` milik Anda maupun `.dll`/`.so` hasil kompilasi TensorFlow Lite. Klasifikasi baru akan berfungsi setelah keduanya dipasang.

## 1. Struktur file

```text
fruitsenses_php_tflite/
├── index.php                 # Web uji upload (tanpa login)
├── classify.php              # API klasifikasi gambar
├── config.php                # Konfigurasi lokasi model, library, label
├── router.php                # Router PHP built-in server (opsional)
├── .htaccess                 # Rewrite /classify dan blok file sensitif
├── lib/
│   ├── ApiException.php
│   └── TfLiteClassifier.php  # Preprocess gambar + PHP FFI / TFLite
├── models/
│   └── model_klasifikasi_buah.tflite   # ANDA HARUS TAMBAHKAN
└── runtime/
    └── tensorflowlite_c.dll          # ANDA HARUS TAMBAHKAN (Windows)
```

## 2. Laragon / Windows

1. Ekstrak ke `C:\laragon\www\fruitsenses_php_tflite`.
2. Jalankan Laragon > **Start All** (Apache; MySQL tidak diperlukan).
3. Aktifkan ekstensi `gd`, `fileinfo`, dan `ffi` di PHP. Cari `php.ini` milik PHP yang dipakai Apache Laragon. Pastikan:
   ```ini
   extension=gd
   extension=ffi
   ffi.enable=true
   upload_max_filesize=6M
   post_max_size=7M
   ```
   Beberapa paket PHP memakai nama `php_ffi.dll`; ikuti aturan PHP build yang Anda pakai. **Restart Apache** setelah mengubah `php.ini`.
4. Copy model `model_klasifikasi_buah.tflite` ke folder `models/`.
5. Siapkan library **`tensorflowlite_c.dll`** TensorFlow Lite C API untuk **Windows 64-bit** dan tempatkan di `runtime/`. Pastikan juga dependensi DLL runtime yang diminta binary ada dalam PATH/proses Apache. Versi dan arsitektur DLL harus kompatibel dengan server Anda.
6. Cara membangun library dari source (proses ini memerlukan toolchain C++/CMake; bukan Python inference):
   - Siapkan source TensorFlow, CMake dan compiler C++ Windows.
   - Ikuti [dokumentasi build CMake TensorFlow Lite C API](https://github.com/tensorflow/tensorflow/blob/master/tensorflow/lite/g3doc/guide/build_cmake.md).
   - Perintah menurut dokumentasi (dijalankan dari direktori build yang sesuai dengan tata letak source TensorFlow):
     ```powershell
     cmake ../tensorflow_src/tensorflow/lite/c
     cmake --build . -j --config Release
     ```
   - Hasil build Windows bernama `tensorflowlite_c.dll`. **Perlu diperhatikan**: proses build bisa memerlukan pengaturan compiler/toolchain tambahan dan bergantung versi TensorFlow; jalankan mengikuti petunjuk build pada versi yang Anda pilih.
7. Buka `http://localhost/fruitsenses_php_tflite/` dan upload gambar.

**Catatan FFI:** `ffi.enable=true` mengizinkan native calls dan berisiko di server publik. Aktifkan hanya pada environment yang Anda kendalikan; untuk produksi isolasikan layanan inferensi dalam service/container yang aman.

## 3. Cara lain: PHP built-in server

```bash
cd fruitsenses_php_tflite
php -d ffi.enable=true -S 0.0.0.0:8000 router.php
```

Akses `http://localhost:8000/`. Untuk HTTP request dari ponsel, gunakan IP LAN komputer dan buka firewall sesuai port.

## 4. Flutter: ubah API URL, login tetap di Flutter

Pada `lib/scan_page.dart`, ubah:

```dart
static const String API_URL = 'http://192.168.1.35/fruitsenses_php_tflite/classify';
```

Ganti `192.168.1.35` dengan IP komputer Laragon Anda. Jika Apache berjalan di port selain 80, cantumkan `:PORT`.

Jika memakai router PHP pada port 8000:

```dart
static const String API_URL = 'http://192.168.1.35:8000/classify';
```

**Tidak perlu mengubah kode login Flutter**. Jika Android memblokir HTTP cleartext, gunakan HTTPS untuk produksi; untuk pengujian lokal atur kebijakan network security Android secara terbatas.

**Penting:** Source `scan_page.dart` lama menjalankan request tanpa `await`, lalu timer berpindah halaman. Perbaiki nanti agar menunggu API sebelum navigasi, supaya tidak tampil `Unknown` ketika API masih memproses.

## 5. Tes melalui terminal

```bash
curl -X POST http://localhost/fruitsenses_php_tflite/classify \
  -F "image=@buah.jpg"
```

Contoh respons **ilustrasi**:

```json
{
  "success": true,
  "prediction": "Apple",
  "confidence": 0.95,
  "confidence_percentage": "95.00%",
  "probability_raw": 0.95,
  "probabilities": { "Apple": 0.95, "Banana": 0.01, "Guava": 0.01, "Orange": 0.01, "Lime": 0.01, "Pomegranate": 0.01 }
}
```

Dua field inti `prediction` dan `confidence` kompatibel dengan Flutter lama. `probability_raw` disediakan sebagai alias *confidence* untuk menghindari nilai nol pada Flutter lama, **bukan nilai raw logits atau ukuran kualitas buah**.

## 6. Kesamaan vs perbedaan dengan Flask asli

- Resize ke `160 × 160`, RGB, `/255.0`, batch `[1,160,160,3]`, sesuai pipeline dasar Python pengguna.
- **Penting:** `PIL.Image.resize()` dan `GD.imagecopyresampled()` memakai metode resampling berbeda, sehingga prediksi pada gambar sama **tidak selalu identik**; lakukan pengujian parity model sebelum produksi.
- Mendukung input/output float32, uint8 dan int8 **per-tensor quantization**. Model dengan dynamic shape, tipe lain, atau quantization per-channel/custom ops mungkin perlu penyesuaian.
- `labels` mengikuti kode Python yang diberikan: `['Apple','Banana','Guava','Orange','Lime','Pomegranate']`.
- Output multi-kelas harus sama jumlahnya dengan `labels`. Jika model hanya menghasilkan **satu nilai sigmoid**, isi `binary_labels` dengan *dua nama kelas sebenarnya*; server sengaja tidak mengarang label kualitas.
- `output_format='auto'` menganggap output ter-normalisasi bila jumlah skor mendekati 1; jika output berupa logits dipakai softmax. Jika Anda mengetahui jenis output model dengan pasti, ubah `output_format` menjadi `logits` atau `probabilities`.
- **Confidence prediksi jenis buah bukan kualitas buah**. Tidak ada output `quality` karena model dan kelas kualitasnya belum diverifikasi.

## 7. Jika error

| Pesan error | Solusi |
|---|---|
| `Library TensorFlow Lite native belum tersedia` | Letakkan `.dll` / `.so` di folder runtime. |
| `Ekstensi FFI belum aktif` | Aktifkan `extension=ffi` dan `ffi.enable=true` pada PHP web lalu restart Apache. |
| `Ekstensi GD belum aktif` | Aktifkan `extension=gd`. |
| `Model .tflite belum tersedia` | Tambah file `models/model_klasifikasi_buah.tflite`. |
| `Model gagal dibuat menjadi interpreter` | Versi runtime dan operasi model belum kompatibel; cek native logs. |
| `Output model ... tidak sesuai labels` | Urutan/jumlah label harus sesuai output layer model. |
| `Tensor input tidak cocok` | Set `image_size` sesuai shape input model dan pipeline training. |
| Flutter error koneksi | Periksa IP PC, Wi-Fi yang sama, port Apache, firewall, dan izin HTTP Android. |

Dokumentasi resmi:
- TensorFlow Lite C API: https://github.com/tensorflow/tensorflow/blob/master/tensorflow/lite/c/README.md
- TensorFlow Lite build: https://github.com/tensorflow/tensorflow/blob/master/tensorflow/lite/g3doc/guide/build_cmake.md
- PHP FFI: https://www.php.net/manual/en/book.ffi.php
