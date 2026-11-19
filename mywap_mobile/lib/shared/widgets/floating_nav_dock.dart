import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// Item data model for [FloatingNavDock].
class FloatingNavDockItem {
  const FloatingNavDockItem({
    required this.icon,
    required this.selectedIcon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final bool selected;
  final VoidCallback onTap;
}

/// Floating Navigation Dock — Apple-inspired curved bottom navigation dock.
///
/// Features:
/// - Rapat ke bahagian bawah peranti dengan lengkungan anggun di bahagian atas.
/// - Butang kamera tengah timbul terkeluar (elevated protrusion) melepasi sempadan atas dock.
/// - State aktif/tap yang diperhalusi dengan mikro-indikator Apple dan tipografi kemas.
/// - Tiada jurang janggal di bawah skrin, menyokong sepenuhnya Home Indicator iOS & gesture bar Android.
class FloatingNavDock extends StatelessWidget {
  const FloatingNavDock({
    super.key,
    required this.items,
    required this.onCenterTap,
    this.centerTooltip,
    this.centerGradient,
    this.centerIcon = Icons.qr_code_scanner_rounded,
  }) : assert(items.length == 4, 'FloatingNavDock expects exactly 4 items (2 left, 2 right)');

  final List<FloatingNavDockItem> items;
  final VoidCallback onCenterTap;
  final String? centerTooltip;
  final List<Color>? centerGradient;
  final IconData centerIcon;

  @override
  Widget build(BuildContext context) {
    final mq = MediaQuery.of(context);
    final bottomInset = mq.padding.bottom > 0 ? mq.padding.bottom : 8.0;

    return Stack(
      clipBehavior: Clip.none,
      alignment: Alignment.topCenter,
      children: [
        // Badan Utama Dock — Rapat ke lengkungan tepi skrin bawah telefon
        Container(
          width: double.infinity,
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF12241C).withValues(alpha: 0.08),
                blurRadius: 24,
                offset: const Offset(0, -4),
              ),
              BoxShadow(
                color: const Color(0xFF12241C).withValues(alpha: 0.04),
                blurRadius: 6,
                offset: const Offset(0, -1),
              ),
            ],
            border: Border(
              top: BorderSide(
                color: const Color(0xFF12241C).withValues(alpha: 0.06),
                width: 0.8,
              ),
            ),
          ),
          child: Padding(
            padding: EdgeInsets.only(
              left: 8,
              right: 8,
              top: 6,
              bottom: bottomInset,
            ),
            child: SizedBox(
              height: 54,
              child: Row(
                children: [
                  Expanded(child: _DockItem(item: items[0])),
                  Expanded(child: _DockItem(item: items[1])),
                  // Ruang khas untuk butang kamera timbul di tengah
                  const SizedBox(width: 60),
                  Expanded(child: _DockItem(item: items[2])),
                  Expanded(child: _DockItem(item: items[3])),
                ],
              ),
            ),
          ),
        ),

        // Butang Kamera / Imbas Timbul Terkeluar di Bahagian Atas
        Positioned(
          top: -18,
          child: _DockCenterButton(
            onTap: onCenterTap,
            tooltip: centerTooltip,
            gradient: centerGradient ?? AppColors.heroGradient,
            icon: centerIcon,
          ),
        ),
      ],
    );
  }
}

/// Butang tengah bulat timbul (Elevated Protruding Center Button).
class _DockCenterButton extends StatefulWidget {
  const _DockCenterButton({
    required this.onTap,
    required this.gradient,
    required this.icon,
    this.tooltip,
  });

  final VoidCallback onTap;
  final List<Color> gradient;
  final IconData icon;
  final String? tooltip;

  @override
  State<_DockCenterButton> createState() => _DockCenterButtonState();
}

class _DockCenterButtonState extends State<_DockCenterButton>
    with SingleTickerProviderStateMixin {
  double _scale = 1.0;

  void _onTapDown(_) => setState(() => _scale = 0.92);
  void _onTapUp(_) => setState(() => _scale = 1.0);
  void _onTapCancel() => setState(() => _scale = 1.0);

  @override
  Widget build(BuildContext context) {
    Widget button = AnimatedScale(
      scale: _scale,
      duration: const Duration(milliseconds: 120),
      curve: Curves.easeInOut,
      child: GestureDetector(
        onTapDown: _onTapDown,
        onTapUp: _onTapUp,
        onTapCancel: _onTapCancel,
        onTap: widget.onTap,
        child: Container(
          width: 56,
          height: 56,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: widget.gradient,
            ),
            border: Border.all(
              color: AppColors.white,
              width: 3.5,
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.movementGreen.withValues(alpha: 0.42),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
              const BoxShadow(
                color: Color(0x1F12241C),
                blurRadius: 8,
                offset: Offset(0, 3),
              ),
            ],
          ),
          child: Center(
            child: Icon(
              widget.icon,
              color: AppColors.white,
              size: 26,
            ),
          ),
        ),
      ),
    );

    if (widget.tooltip != null && widget.tooltip!.isNotEmpty) {
      return Tooltip(
        message: widget.tooltip!,
        child: button,
      );
    }

    return button;
  }
}

/// Item tab dock dengan mikro-interaksi Apple yang anggun.
class _DockItem extends StatelessWidget {
  const _DockItem({required this.item});

  final FloatingNavDockItem item;

  @override
  Widget build(BuildContext context) {
    final activeColor = AppColors.movementGreen;
    final inactiveColor = const Color(0xFF8E8E93); // Apple System Gray

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: item.onTap,
        splashColor: activeColor.withValues(alpha: 0.08),
        highlightColor: Colors.transparent,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 2),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                curve: Curves.easeOutCubic,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 3),
                decoration: BoxDecoration(
                  color: item.selected
                      ? activeColor.withValues(alpha: 0.12)
                      : Colors.transparent,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(
                  item.selected ? item.selectedIcon : item.icon,
                  color: item.selected ? activeColor : inactiveColor,
                  size: 22,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                item.label,
                style: TextStyle(
                  color: item.selected ? activeColor : inactiveColor,
                  fontSize: 10.5,
                  fontWeight: item.selected ? FontWeight.w700 : FontWeight.w500,
                  letterSpacing: -0.2,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
