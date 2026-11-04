part of '../main.dart';

/// The same official artwork assets used by Prince's website wordmark.
class ShopPickBrand extends StatelessWidget {
  final bool compact;
  final String? subtitle;

  const ShopPickBrand({super.key, this.compact = false, this.subtitle});

  @override
  Widget build(BuildContext context) {
    final height = compact ? 34.0 : 54.0;
    final wordmark = Semantics(
        label: 'SHOPPICK',
        image: true,
        child: SizedBox(
            width: height * 4.9,
            height: height,
            child: Row(children: [
              Image.asset('assets/images/shoppick-mark.png',
                  width: height * 640 / 610,
                  height: height,
                  excludeFromSemantics: true),
              Expanded(
                  child: Image.asset(
                      'assets/images/shoppick-wordmark-hoppick.png',
                      fit: BoxFit.contain,
                      alignment: Alignment.centerLeft,
                      excludeFromSemantics: true)),
            ])));
    return FittedBox(
        fit: BoxFit.scaleDown,
        alignment: compact ? Alignment.centerLeft : Alignment.center,
        child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment:
                compact ? CrossAxisAlignment.start : CrossAxisAlignment.center,
            children: [
              wordmark,
              if (subtitle != null)
                Text(subtitle!,
                    style: const TextStyle(
                        fontSize: 12,
                        color: teal,
                        fontWeight: FontWeight.w700)),
            ]));
  }
}
