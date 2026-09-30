<?php

namespace App\Console\Commands;

use App\Services\AbogadosBot\Runtime;
use Illuminate\Console\Command;
use Throwable;

final class RunAbogadosBot extends Command
{
    protected $signature='abogados-bot:run {--health}';
    protected $description='Process the isolated Abogados WhatsApp inbox and transactional outbox.';
    public function handle(): int
    {
        try{
            $bot=new Runtime();$result=$this->option('health')?$bot->healthSummary():$bot->process();
            $this->line(json_encode($result,JSON_UNESCAPED_UNICODE));return self::SUCCESS;
        }catch(Throwable $ex){
            $this->error('Abogados bot: '.get_class($ex));return self::FAILURE;
        }
    }
}
