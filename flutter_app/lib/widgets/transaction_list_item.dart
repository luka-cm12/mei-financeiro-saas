import 'package:flutter/material.dart';
import '../models/transaction.dart';
import '../utils/theme.dart';

class TransactionListItem extends StatelessWidget {
  final Transaction transaction;
  final VoidCallback? onTap;
  final VoidCallback? onDelete;

  const TransactionListItem({
    super.key,
    required this.transaction,
    this.onTap,
    this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final isReceita = transaction.type == 'receita';
    final color = isReceita ? AppColors.receita : AppColors.despesa;
    
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        onTap: onTap,
        leading: CircleAvatar(
          backgroundColor: color.withOpacity(0.1),
          child: Icon(
            _getCategoryIcon(),
            color: color,
            size: 20,
          ),
        ),
        title: Text(
          transaction.description,
          style: const TextStyle(
            fontWeight: FontWeight.w500,
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (transaction.categoryName != null) ...[
              const SizedBox(height: 4),
              Text(
                transaction.categoryName!,
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.grey[600],
                ),
              ),
            ],
            const SizedBox(height: 2),
            Text(
              transaction.formattedDate,
              style: TextStyle(
                fontSize: 12,
                color: Colors.grey[600],
              ),
            ),
          ],
        ),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  '${isReceita ? '+' : '-'} ${transaction.formattedAmount}',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: color,
                    fontSize: 16,
                  ),
                ),
                if (transaction.paymentMethod != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    transaction.paymentMethod!,
                    style: TextStyle(
                      fontSize: 10,
                      color: Colors.grey[600],
                    ),
                  ),
                ],
              ],
            ),
            if (onDelete != null) ...[
              const SizedBox(width: 8),
              IconButton(
                icon: const Icon(Icons.delete, size: 20),
                onPressed: onDelete,
                color: Colors.grey[600],
                constraints: const BoxConstraints(),
                padding: EdgeInsets.zero,
              ),
            ],
          ],
        ),
      ),
    );
  }

  IconData _getCategoryIcon() {
    if (transaction.categoryIcon != null) {
      // Mapear ícones do Material Design
      switch (transaction.categoryIcon) {
        case 'shopping_cart':
          return Icons.shopping_cart;
        case 'build':
          return Icons.build;
        case 'account_balance':
          return Icons.account_balance;
        case 'inventory':
          return Icons.inventory;
        case 'campaign':
          return Icons.campaign;
        case 'directions_car':
          return Icons.directions_car;
        case 'restaurant':
          return Icons.restaurant;
        case 'computer':
          return Icons.computer;
        case 'receipt':
          return Icons.receipt;
        case 'more_horiz':
          return Icons.more_horiz;
        default:
          return transaction.isReceita ? Icons.trending_up : Icons.trending_down;
      }
    }
    
    return transaction.isReceita ? Icons.trending_up : Icons.trending_down;
  }
}