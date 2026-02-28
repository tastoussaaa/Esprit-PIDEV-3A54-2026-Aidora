<?php

namespace App\Service;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;

class BotManFactoryService
{
    public function create(): BotMan
    {
        DriverManager::loadDriver(\BotMan\Drivers\Web\WebDriver::class);

        $config = [
            // 'conversation_cache_time' => 30,
        ];

        return BotManFactory::create($config);
    }
}
