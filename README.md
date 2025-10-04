# 🌐 Sorot Dunia

![Sorot Dunia](../img/logo.png)

**Sorot Dunia** adalah platform web berita sederhana yang dirancang untuk menyajikan berita lokal dan internasional dengan tampilan bersih, terstruktur, dan mudah dikembangkan. README ini ditulis untuk membantu kamu (pengembang / admin) memulai, mengonfigurasikan, dan mengembangkan proyek ini lebih lanjut.

---

## 🎯 Tujuan Proyek

1. Menyajikan berita secara cepat dan rapi.
2. Menjadi basis yang mudah dikembangkan untuk fitur-fitur jurnalistik (kategori, tag, draft, publikasi terjadwal, dsb.).
3. Memberikan struktur kode yang mudah dipahami bagi developer pemula sampai menengah.

---

## ✨ Fitur Utama (lebih lengkap)

* Halaman beranda yang menampilkan ringkasan berita terbaru.
* Halaman kategori untuk mengelompokkan berita.
* Halaman detail artikel lengkap dengan gambar, metadata, dan slug SEO.
* Pencarian sederhana berdasarkan judul / isi.
* Sistem draf (opsional) untuk menyimpan artikel sebelum dipublikasi.
* Struktur modular view (multi-view) sehingga mudah menambah layout baru.
* Responsif: tampilan mendukung mobile dan desktop.
* Upload gambar untuk artikel (pastikan folder `uploads/` punya permission).

---

## 🛠️ Teknologi & Struktur Proyek

| Komponen | Teknologi | Keterangan |
|----------|-----------|------------|
| Backend / logika | PHP | Pemrosesan data berita & routing |
| Frontend | HTML / CSS / JavaScript | Antarmuka pengguna & interaktivitas |
| Struktur folder | `project/`, `multi-view.php`, `index.php`, dll | Organisasi modul tampilan & logika |
| Database (opsional) | — | Jika kamu memakai DB, sambungkan di modul backend |

---


## 📥 Instalasi & Setup (Langkah demi langkah)

1. Clone repository:

   ```bash
   git clone https://github.com/ESTAS-crypto/sorot-dunia.git
   cd sorot-dunia
   ```

