<?php
 
namespace App\Logging;
 
use Illuminate\Log\Logger;
use Monolog\Formatter\LineFormatter;

use hpsynapse\moduser\Facades\UserAuth;
 
class DefaultSynapseFormatter
{
    /**
     * Customize the given logger instance.
     */
    public function __invoke(Logger $logger): void
    {
        $prefix = 'tenant:'.config('tenant.id','0').'-userId:'.UserAuth::user('id');
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] [".$prefix."] %channel%.%level_name%: %message% %context% %extra%\n"
                ,'d-m-Y H:i:s.u',true,true,true));
        }
    }
}