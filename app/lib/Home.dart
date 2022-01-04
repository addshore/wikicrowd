import 'package:flutter/material.dart';

class Home extends StatefulWidget {
  const Home({Key? key, required this.title}) : super(key: key);
  final String title;

  @override
  State<Home> createState() => _HomeState();
}

class _HomeState extends State<Home> {
  bool authed = false;
  int _counter = 0;

  void _incrementCounter() {
    setState(() {
      _counter++;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
      ),
      bottomNavigationBar: Theme(
          data: Theme.of(context).copyWith(),
          child: BottomNavigationBar(
            // ignore: prefer_const_literals_to_create_immutables
            items: [
              const BottomNavigationBarItem(
                  icon: Icon(Icons.home), label: "Home"),
              const BottomNavigationBarItem(
                  icon: Icon(Icons.info), label: "About"),
            ],
            onTap: (value) {
              _incrementCounter();
              switch (value) {
                //case 0:
                // Do nothing as we are on this page
                // Navigator.pushNamed(context, '/');
                // break;
                case 1:
                  Navigator.pushNamed(context, '/about');
                  break;
              }
            },
          )),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            const Text(
              'Auth Status:',
            ),
            if (authed)
              Text(
                'Welcome authed user!',
                style: Theme.of(context).textTheme.headline4,
              ),
            if (!authed)
              Text(
                'You are not logged in',
                style: Theme.of(context).textTheme.headline4,
              ),
            ElevatedButton(
              onPressed: () {
                Navigator.pushNamed(context, '/login');
              },
              child: Text('Login'),
            ),
            const Text(
              'You have pushed the nav buttons this many times:',
            ),
            Text(
              '$_counter',
              style: Theme.of(context).textTheme.headline4,
            ),
          ],
        ),
      ),
    );
  }
}
