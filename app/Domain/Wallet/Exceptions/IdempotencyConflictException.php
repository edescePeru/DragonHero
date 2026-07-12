<?php
namespace App\Domain\Wallet\Exceptions;
use DomainException;
class IdempotencyConflictException extends DomainException {}
