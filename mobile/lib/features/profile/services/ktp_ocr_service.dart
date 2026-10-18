class KtpScanResult {
  final String? nik;
  final String? name;
  final String? birthPlaceDate;
  final String? gender;
  final String? address;
  final bool isConfident;
  final String? rawText;

  KtpScanResult({
    this.nik,
    this.name,
    this.birthPlaceDate,
    this.gender,
    this.address,
    this.isConfident = false,
    this.rawText,
  });

  bool get isValidNik => nik != null && RegExp(r'^[0-9]{16}$').hasMatch(nik!);
}

class KtpOcrService {
  /// Ekstraksi dan parsing cerdas teks KTP Indonesia
  static KtpScanResult parseKtpText(String text) {
    String? extractedNik;
    String? extractedName;
    String? extractedBirth;
    String? extractedGender;
    String? extractedAddress;

    // 1. Ekstraksi NIK (16 digit angka berurutan)
    final nikRegExp = RegExp(r'\b[0-9]{16}\b');
    final nikMatch = nikRegExp.firstMatch(text);
    if (nikMatch != null) {
      extractedNik = nikMatch.group(0);
    } else {
      // Coba toleransi spasi atau karakter 'O'/'D' yang tertukar dengan '0'
      final cleanedForNik = text
          .replaceAll(RegExp(r'[oOD]', caseSensitive: false), '0')
          .replaceAll(RegExp(r'[lI|]', caseSensitive: false), '1');
      final fallbackMatch = nikRegExp.firstMatch(cleanedForNik);
      if (fallbackMatch != null) {
        extractedNik = fallbackMatch.group(0);
      }
    }

    // 2. Ekstraksi Nama (hanya karakter baris yang sama, tidak melompat ke baris berikutnya)
    final nameRegExp = RegExp(r"Nama\s*[:;]?\s*([A-Za-z .,'-]+)", caseSensitive: false);
    final nameMatch = nameRegExp.firstMatch(text);
    if (nameMatch != null) {
      extractedName = nameMatch.group(1)?.trim();
    }

    // 3. Ekstraksi Tempat/Tgl Lahir
    final birthRegExp = RegExp(r"Tempat/Tgl Lahir\s*[:;]?\s*([A-Za-z0-9\s.,\/-]+)", caseSensitive: false);
    final birthMatch = birthRegExp.firstMatch(text);
    if (birthMatch != null) {
      extractedBirth = birthMatch.group(1)?.trim();
    }

    // 4. Ekstraksi Jenis Kelamin
    if (RegExp(r'LAKI[- ]LAKI', caseSensitive: false).hasMatch(text)) {
      extractedGender = 'LAKI-LAKI';
    } else if (RegExp(r'PEREMPUAN', caseSensitive: false).hasMatch(text)) {
      extractedGender = 'PEREMPUAN';
    }

    // 5. Ekstraksi Alamat
    final addressRegExp = RegExp(r"Alamat\s*[:;]?\s*([A-Za-z0-9\s.,\/-]+)", caseSensitive: false);
    final addressMatch = addressRegExp.firstMatch(text);
    if (addressMatch != null) {
      extractedAddress = addressMatch.group(1)?.trim();
    }

    final isConfident = extractedNik != null && extractedNik.length == 16;

    return KtpScanResult(
      nik: extractedNik,
      name: extractedName,
      birthPlaceDate: extractedBirth,
      gender: extractedGender,
      address: extractedAddress,
      isConfident: isConfident,
      rawText: text,
    );
  }

  /// Simulasi pemindaian OCR on-device dengan pengenalan teks KTP
  static Future<KtpScanResult> simulateScanKtp({String? mockRawText}) async {
    // Simulasi jeda komputasi OCR on-device
    await Future.delayed(const Duration(milliseconds: 900));

    final sampleText = mockRawText ??
        '''
        PROVINSI JAWA TIMUR
        KOTA MALANG
        NIK : 3573011508980004
        Nama : AHMAD FAKHRUDIN
        Tempat/Tgl Lahir : MALANG, 15-08-1998
        Jenis Kelamin : LAKI-LAKI
        Alamat : JL. BENDUNGAN SIGURA-GURA NO. 18
        RT/RW : 002/005
        Kel/Desa : LOWOKWARU
        Kecamatan : LOWOKWARU
        Agama : ISLAM
        Status Perkawinan: BELUM KAWIN
        Pekerjaan : MAHASISWA
        Kewarganegaraan : WNI
        Berlaku Hingga : SEUMUR HIDUP
        ''';

    return parseKtpText(sampleText);
  }
}
