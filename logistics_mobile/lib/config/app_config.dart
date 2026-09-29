import 'package:flutter/material.dart';

const apiBaseUrl = String.fromEnvironment('API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1');
const teal = Color(0xFF008F87);
const orange = Color(0xFFFF8A32);
const navy = Color(0xFF172B4D);
ThemeData appTheme() => ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(seedColor: teal, primary: teal),
      scaffoldBackgroundColor: const Color(0xFFF4F7FA),
      appBarTheme: const AppBarTheme(
          backgroundColor: Colors.white, foregroundColor: navy),
      cardTheme: const CardThemeData(
          color: Colors.white,
          elevation: 0,
          margin: EdgeInsets.only(bottom: 12)),
      inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(14))),
      filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
              minimumSize: const Size(48, 52),
              padding:
                  const EdgeInsets.symmetric(horizontal: 20, vertical: 14))),
    );
