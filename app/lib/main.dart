import 'package:flutter/material.dart';
import 'Home.dart';
import 'About.dart';
import 'Login.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({Key? key}) : super(key: key);

  // This widget is the root of your application.
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
        title: 'WikiCrowd',
        theme: ThemeData(
          primarySwatch: Colors.blue,
        ),
        routes: {
          '/': (_) => const Home(title: 'WikiCrowd'),
          '/about': (_) => const About(),
          '/login': (_) => const Login(),
        });
  }
}
