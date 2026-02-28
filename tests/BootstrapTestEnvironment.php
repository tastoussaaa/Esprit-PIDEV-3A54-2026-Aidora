<?php

namespace App\Tests;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class BootstrapTestEnvironment
{
    public static function setUp(): void
    {
        if ('test' !== $_ENV['APP_ENV'] ?? null) {
            throw new \RuntimeException('APP_ENV must be "test"');
        }
        
        // Create/setup test database
        $kernel = new Kernel($_ENV['APP_ENV'], (bool)($_ENV['APP_DEBUG'] ?? false));
        $kernel->boot();
        
        $application = new Application($kernel);
        $application->setAutoExit(false);
        
        // Drop and recreate database for clean state
        try {
            $application->run(new ArrayInput(['command' => 'doctrine:database:drop', '--if-exists' => true, '--force' => true]), new NullOutput());
        } catch (\Exception $e) {
            // Database might not exist yet
        }
        
        $application->run(new ArrayInput(['command' => 'doctrine:database:create']), new NullOutput());
        $application->run(new ArrayInput(['command' => 'doctrine:schema:create']), new NullOutput());
        
        $kernel->shutdown();
    }
}
