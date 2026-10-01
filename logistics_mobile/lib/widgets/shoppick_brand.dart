import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../config/app_config.dart';
import 'common.dart';

/// Reuses the official website logo without changing its artwork.
class ShopPickBrand extends StatelessWidget {
  final String subtitle;
  final bool compact;

  const ShopPickBrand({
    super.key,
    required this.subtitle,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final logo = SvgPicture.asset(
      'assets/images/shoppick_logo.svg',
      width: compact ? 32 : 80,
      height: compact ? 32 : 80,
      fit: BoxFit.contain,
      excludeFromSemantics: true,
      errorBuilder: (context, error, stackTrace) => SizedBox.square(
        dimension: compact ? 32 : 80,
      ),
    );
    // The wordmark remains visible even if the decorative logo cannot load.
    final wording = Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment:
          compact ? CrossAxisAlignment.start : CrossAxisAlignment.center,
      children: [
        const Wordmark(),
        Text(subtitle,
            style: TextStyle(
                fontSize: compact ? 12 : 16,
                color: teal,
                fontWeight: FontWeight.w700)),
      ],
    );
    if (!compact) {
      return Column(mainAxisSize: MainAxisSize.min, children: [
        logo,
        const SizedBox(height: 12),
        wording,
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
