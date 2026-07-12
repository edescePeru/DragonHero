<?php
namespace App\Domain\Wallet;
final class GoldTransactionType { const CREDIT='credit'; const DEBIT='debit'; public static function all(){return [self::CREDIT,self::DEBIT];} }
