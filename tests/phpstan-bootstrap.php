<?php
// tests/phpstan-bootstrap.php

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/../.env');

// Boot Symfony kernel
$kernel = new Kernel('dev', true);
$kernel->boot();

/** @var EntityManagerInterface $em */
$em = $kernel->getContainer()->get('doctrine')->getManager();

return $em;