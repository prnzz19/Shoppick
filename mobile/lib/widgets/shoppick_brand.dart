part of '../main.dart';

/// Official SHOPPICK artwork with the same sizing as the Logistics app.
class ShopPickBrand extends StatelessWidget {
  final bool compact;
  final String? subtitle;

  const ShopPickBrand({super.key, this.compact = false, this.subtitle});

  @override
  Widget build(BuildContext context) {
    final size = compact ? 32.0 : 80.0;
    final logo = SvgPicture.asset(
      'assets/images/shoppick_logo.svg',
      width: size,
      height: size,
      fit: BoxFit.contain,
      excludeFromSemantics: true,
      errorBuilder: (context, error, stackTrace) =>
          SizedBox.square(dimension: size),
    );
    // Keep the wordmark visible even if the decorative SVG cannot load.
    final wording = Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment:
          compact ? CrossAxisAlignment.start : CrossAxisAlignment.center,
      children: [
        Text.rich(TextSpan(
          style: TextStyle(
              fontSize: compact ? 24 : 32,
              fontWeight: FontWeight.w900,
              letterSpacing: compact ? -.8 : 1.2),
          children: const [
            TextSpan(text: 'SHOP', style: TextStyle(color: teal)),
            TextSpan(text: 'PICK', style: TextStyle(color: orange)),
          ],
        )),
        if (subtitle != null)
          Text(subtitle!,
              style: const TextStyle(
                  fontSize: 12, color: teal, fontWeight: FontWeight.w700)),
      ],
    );
    if (!compact) {
      return Column(mainAxisSize: MainAxisSize.min, children: [
        logo,
        const SizedBox(height: 12),
        FittedBox(fit: BoxFit.scaleDown, child: wording),
      ]);
    }
    return Row(children: [
      logo,
      const SizedBox(width: 10),
      Flexible(
        child: SizedBox(
          height: kToolbarHeight - 8,
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: wording,
          ),
        ),
      ),
    ]);
  }
}
