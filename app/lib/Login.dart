import 'dart:io';

import 'package:flutter/material.dart';
import 'package:webviewx/webviewx.dart';

// class WebViewExample extends StatefulWidget {
//   @override
//   WebViewExampleState createState() => WebViewExampleState();
// }

// class WebViewExampleState extends State<WebViewExample> {
//   @override
//   Widget build(BuildContext context) {
//     return WebView(
//       initialUrl: 'https://flutter.dev',
//     );
//   }
// }

class Login extends StatefulWidget {
  const Login({Key? key}) : super(key: key);

  @override
  State<Login> createState() => _LoginState();
}

class _LoginState extends State<Login> {
  late WebViewXController webviewController;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text("Login"),
      ),
      body: Center(
        child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              WebViewX(
                //initialContent: 'https://wikicrowd.toolforge.org/auth/login',
                initialContent: 'http://localhost/auth/login',
                initialSourceType: SourceType.url,
                width: 600,
                height: 600,
                onWebViewCreated: (controller) =>
                    webviewController = controller,
              ),
              ElevatedButton(
                onPressed: () {
                  Navigator.pop(context);
                },
                child: Text('Abandon ship!'),
              )
            ]),
      ),
    );
  }
}
