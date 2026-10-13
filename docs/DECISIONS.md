# Catatan Keputusan Arsitektur (Architectural Decision Records - ADR)

Dokumen ini mencatat keputusan-keputusan teknis fundamental, pertimbangan alternatif, dan konsekuensi arsitektural yang diambil dalam pengembangan **SIM Kos & Guesthouse**.

---

## ADR-001: Penggunaan Laravel 13 sebagai REST API Backend
- **Status**: Disetujui (Accepted)
- **Konteks**: Diperlukan fondasi backend yang tangguh, aman, memiliki ekosistem migrasi database yang solid, dan siap dioperasikan dalam skala production dengan performa tinggi.
- **Keputusan**: Menggunakan Laravel 13 dengan PHP 8.4 (NTS).
- **Konsekuensi**:
  - Memanfaatkan fitur modern PHP 8.4 (Property Hooks, Attributes, Asymmetric Visibility).
  - API Routes dikelola via `routes/api.php` yang terintegrasi dengan middleware pipeline.
  - Dokumentasi API dan standarisasi resource serializer sangat terstruktur.

---

## ADR-002: Arsitektur Flutter dengan Riverpod dan GoRouter
- **Status**: Disetujui (Accepted)
- **Konteks**: Aplikasi mobile harus mendukung Android dan iOS dengan satu basis kode, performa mulus (60/120 fps), manajemen state yang aman terhadap runtime error, dan routing deklaratif dengan deep linking.
- **Keputusan**:
  - **State Management**: `flutter_riverpod` (v2.6+). Riverpod dipilih karena bersifat compile-safe, tidak bergantung pada `BuildContext` widget tree untuk dependency injection, dan mempermudah testing.
  - **Navigasi**: `go_router` (v15+). Mendukung nested navigation, shell routes (bottom navigation bar), dan redirect guard untuk perlindungan autentikasi.
  - **Networking**: `dio` dengan custom Interceptor untuk injeksi Bearer Token Sanctum secara otomatis dan penanganan error 401 (Auto-logout).
- **Konsekuensi**:
  - Arsitektur modular per fitur (*Feature-First*).
  - UI konsisten dalam Bahasa Indonesia dengan format mata uang IDR (`NumberFormat.currency`) dan tanggal lokal.

---

## ADR-003: Autentikasi Menggunakan Laravel Sanctum
- **Status**: Disetujui (Accepted)
- **Konteks**: Aplikasi mobile berkomunikasi melalui stateless REST API. Dibutuhkan mekanisme token yang ringan tanpa overhead kompleks OAuth2 (seperti Passport) namun tetap mendukung multi-device login dan token revocation.
- **Keputusan**: Menggunakan **Laravel Sanctum** Personal Access Tokens.
- **Konsekuensi**:
  - Setiap perangkat yang login diberikan token acak berkekuatan tinggi (SHA-256 hash tersimpan di database).
  - Token dapat dicabut secara individual atau sekaligus saat pengguna memilih opsi *"Keluar dari semua perangkat"*.

---

## ADR-004: Model Hibrida Kos (Bulanan) dan Guesthouse (Harian)
- **Status**: Disetujui (Accepted)
- **Konteks**: Banyak pemilik properti mengombinasikan kamar kos jangka panjang dengan unit guesthouse jangka pendek untuk memaksimalkan okupansi dan arus kas harian.
- **Keputusan**: Mengadopsi struktur entitas terpadu di mana satu properti dapat memiliki tipe `kos`, `guesthouse`, atau `hybrid`. Tabel `bookings` mendukung kolom `rental_type` (`daily`, `weekly`, `monthly`, `quarterly`, `yearly`) dengan logika kalkulasi harga dinamis.
- **Konsekuensi**:
  - Logika anti-double-booking dirancang universal untuk mencegah kamar yang sedang ditempati penghuni bulanan disewakan harian, atau sebaliknya.
  - Tagihan deposit hanya diwajibkan pada tipe sewa bulanan/tahunan.

---

## ADR-005: Standar Keuangan Menggunakan Tipe Data Decimal
- **Status**: Disetujui (Accepted)
- **Konteks**: Kesalahan pembulatan pecahan rupiah pada penghitungan sewa, denda harian, dan pemakaian kwh listrik dapat menyebabkan diskrepansi buku besar keuangan.
- **Keputusan**: Seluruh kolom nominal uang, tarif kwh, dan denda menggunakan `DECIMAL(14, 2)` di database MySQL/MariaDB dan dibungkus dengan parser numerik presisi pada backend.
