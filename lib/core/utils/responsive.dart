import 'package:flutter/widgets.dart';

/// Shared breakpoints for adapting layouts across phone, tablet, web and
/// Windows desktop windows from the same widget tree.
class Breakpoints {
  Breakpoints._();

  static const double tablet = 600;
  static const double desktop = 1024;
}

enum ScreenSize { mobile, tablet, desktop }

ScreenSize screenSizeFor(double width) {
  if (width >= Breakpoints.desktop) return ScreenSize.desktop;
  if (width >= Breakpoints.tablet) return ScreenSize.tablet;
  return ScreenSize.mobile;
}

ScreenSize screenSizeOf(BuildContext context) => screenSizeFor(MediaQuery.sizeOf(context).width);

bool isWide(BuildContext context) => screenSizeOf(context) != ScreenSize.mobile;

/// Column count for a product/tile grid given the available width, so grids
/// stop being hardcoded to a fixed count that only suits a phone.
int gridColumnsFor(double width, {int mobile = 2, int tablet = 3, int desktop = 5}) {
  switch (screenSizeFor(width)) {
    case ScreenSize.desktop:
      return desktop;
    case ScreenSize.tablet:
      return tablet;
    case ScreenSize.mobile:
      return mobile;
  }
}
