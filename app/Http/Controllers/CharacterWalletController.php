<?php
namespace App\Http\Controllers;
use App\Domain\Wallet\WalletService; use App\Models\Character;
class CharacterWalletController extends Controller { public function show(Character $character,WalletService $wallet){$this->authorize('view',$character);return view('characters.wallet.show',['character'=>$character,'balance'=>$wallet->balance($character),'transactions'=>$wallet->transactions($character)]);} }
