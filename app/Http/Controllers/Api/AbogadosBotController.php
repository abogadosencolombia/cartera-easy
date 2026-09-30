<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AbogadosBot\Runtime;
use App\Services\AbogadosBot\GoogleSources;
use Illuminate\Http\Request;
use Throwable;

final class AbogadosBotController extends Controller
{
    public function receive(Request $request)
    {
        abort_if(strlen($request->getContent())>2*1024*1024,413);
        $bot=new Runtime();
        abort_unless($bot->authenticated((string)$request->header('X-Abogados-Webhook-Token')),401);
        abort_unless($request->input('instance')==='abogados',403);
        try { return response()->json($bot->receive($request->json()->all())); }
        catch(Throwable $ex){
            // No message content, headers, exception trace or credentials in ordinary logs.
            \Log::warning('Abogados bot receive failed',['type'=>get_class($ex)]);
            return response()->json(['error'=>'EVENT_NOT_PERSISTED'],503);
        }
    }
    public function dashboard(Request $request)
    {
        abort_unless($request->user()?->tipo_usuario==='admin',403);
        $bot=new Runtime();
        return response()->view('abogados-bot.dashboard',['health'=>$bot->healthSummary(),'tickets'=>$bot->tickets(),'google'=>(new GoogleSources())->status()])
            ->header('Cache-Control','private, no-store')->header('X-Robots-Tag','noindex, nofollow');
    }
    public function googleStart(Request $request)
    {
        abort_unless($request->user()?->tipo_usuario==='admin',403);
        $state=bin2hex(random_bytes(32));$verifier=bin2hex(random_bytes(32));
        $request->session()->put('abogados_google_oauth',['state'=>$state,'verifier'=>$verifier,'at'=>time()]);
        return redirect()->away((new GoogleSources())->authorizationUrl($state,$verifier));
    }
    public function googleCallback(Request $request)
    {
        abort_unless($request->user()?->tipo_usuario==='admin',403);
        $saved=$request->session()->pull('abogados_google_oauth');
        abort_unless(is_array($saved)&&time()-$saved['at']<=900&&hash_equals($saved['state'],(string)$request->query('state')),403);
        if($request->has('error')||!$request->has('code'))return redirect('/abogados-bot')->with('google_status','La conexión no fue autorizada.');
        try{(new GoogleSources())->exchange((string)$request->query('code'),$saved['verifier']);$message='Gmail y Drive conectados en modo de solo lectura a la cuenta de Abogados.';}
        catch(Throwable $ex){$code=preg_match('/^GOOGLE_[A-Z_0-9]{1,55}$/D',$ex->getMessage())?$ex->getMessage():'GOOGLE_CONNECTION_FAILED';$message='No se completó la conexión: '.$code;}
        return redirect('/abogados-bot')->with('google_status',$message)->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }
}
