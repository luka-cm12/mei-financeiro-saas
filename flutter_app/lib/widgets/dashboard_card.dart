import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class DashboardCard extends StatelessWidget {
  final String title;
  final double value;
  final Color color;
  final IconData icon;
  final bool isLarge;

  const DashboardCard({
    super.key,
    required this.title,
    required this.value,
    required this.color,
    required this.icon,
    this.isLarge = false,
  });

  @override
  Widget build(BuildContext context) {
    final formattedValue = NumberFormat.currency(
      locale: 'pt_BR',
      symbol: 'R\$',
    ).format(value);

    return Card(
      elevation: 2,
      child: Container(
        padding: EdgeInsets.all(isLarge ? 24 : 16),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          gradient: LinearGradient(
            colors: [
              color.withOpacity(0.8),
              color,
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: isLarge ? 18 : 14,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
                Icon(
                  icon,
                  color: Colors.white,
                  size: isLarge ? 32 : 24,
                ),
              ],
            ),
            SizedBox(height: isLarge ? 16 : 8),
            Text(
              formattedValue,
              style: TextStyle(
                color: Colors.white,
                fontSize: isLarge ? 28 : 20,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
      ),
    );
  }
}