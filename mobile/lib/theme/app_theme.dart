import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  // Brand Palette (Adopsi Identitas Web Jepara Cargo)
  static const Color primary = Color(0xFF8B5A2B); // Warna Utama Kayu Jati Jepara
  static const Color primaryDark = Color(0xFF6B421E);
  static const Color primaryLight = Color(0xFFA67C52);
  static const Color secondary = Color(0xFF1E293B); // Deep Navy Slate
  static const Color bgCanvas = Color(0xFFFBFBFD); // Apple/Web Clean Background
  static const Color surfaceCard = Colors.white;
  static const Color textHeadline = Color(0xFF1D1D1F);
  static const Color textMain = Color(0xFF1D1D1F);
  static const Color textMuted = Color(0xFF86868B);
  static const Color borderLight = Color(0x14000000); // 7% Black

  // Accents
  static const Color accentGreen = Color(0xFF10B981);
  static const Color accentOrange = Color(0xFFF59E0B);
  static const Color accentBlue = Color(0xFF3B82F6);

  static ThemeData get lightTheme {
    final fontName = GoogleFonts.plusJakartaSans().fontFamily;

    return ThemeData(
      useMaterial3: true,
      fontFamily: fontName,
      scaffoldBackgroundColor: bgCanvas,
      colorScheme: ColorScheme.fromSeed(
        seedColor: primary,
        primary: primary,
        secondary: secondary,
        surface: surfaceCard,
        onSurface: textHeadline,
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: bgCanvas,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
          fontFamily: fontName,
          fontSize: 17,
          fontWeight: FontWeight.w700,
          color: textHeadline,
          letterSpacing: -0.3,
        ),
        iconTheme: const IconThemeData(color: textHeadline),
      ),
      cardTheme: CardThemeData(
        color: surfaceCard,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: const BorderSide(color: borderLight, width: 1),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(98), // Pill Button khas Web
          ),
          textStyle: TextStyle(
            fontFamily: fontName,
            fontWeight: FontWeight.w600,
            fontSize: 14,
            letterSpacing: -0.2,
          ),
        ),
      ),
    );
  }
}
