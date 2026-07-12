# Billetera de oro

`character_wallets` mantiene el saldo actual para consultas rápidas. `gold_transactions` es el ledger inmutable y auditable: cada cambio conserva importe, saldo anterior, saldo posterior y razón técnica. WalletService actualiza ambos atómicamente dentro de una transacción y con bloqueos pesimistas; nunca debe existir un saldo sin su movimiento ni un movimiento sin su saldo.

El saldo no puede ser negativo y los créditos se comprueban contra `PHP_INT_MAX` antes de sumar. Una `idempotency_key` global opcional evita procesar dos veces la misma operación; mide hasta 191 caracteres y solo permite recuperar el movimiento previo si todos sus datos relevantes coinciden. `reason_code` es un código técnico estable, separado del texto visible y de la descripción.

MySQL 5.7 no aplica realmente restricciones `CHECK`. Por ello `amount > 0` se protege con `BIGINT UNSIGNED`, validación autoritativa repetida dentro de WalletService, ausencia de escrituras web directas y pruebas. No se crean triggers. Toda escritura futura debe pasar por WalletService.

El ledger no se edita ni elimina. Su FK usa RESTRICT: un personaje con movimientos no puede borrarse físicamente y deberá archivarse o adoptar soft deletes en el futuro. Las referencias permitirán vincular movimientos con cacerías, misiones, tiendas, crafteo y comercio sin implementar todavía esos sistemas.
