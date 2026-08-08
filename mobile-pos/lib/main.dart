import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'login_page.dart';
import 'offline_store.dart';
import 'pos_page.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  try {
    await Firebase.initializeApp();
  } catch (_) {}
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'NYEMIL BEBS POS',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.deepPurple),
        useMaterial3: true,
      ),
      home: const _StartupGate(),
    );
  }
}

/// Cek sesi login tersimpan lebih dulu — kalau ada, langsung masuk ke POS
/// tanpa perlu login ulang (supaya app tetap bisa dipakai saat offline).
class _StartupGate extends StatelessWidget {
  const _StartupGate();

  @override
  Widget build(BuildContext context) {
    return FutureBuilder(
      future: OfflineStore.getSession(),
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Scaffold(
            body: Center(child: CircularProgressIndicator()),
          );
        }

        final session = snapshot.data;
        if (session != null) {
          return PosHomePage(
            apiToken: session.apiToken,
            kasirName: session.kasirName,
          );
        }

        return const LoginPage();
      },
    );
  }
}
