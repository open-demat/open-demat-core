<?php

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Si tu utilises dotenv en test (optionnel)
if (file_exists(dirname(__DIR__).'/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Boot kernel test
$kernelClass = $_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS'] ?? null;
if (!$kernelClass) {
    throw new RuntimeException('KERNEL_CLASS missing in env.');
}

/** @var Symfony\Component\HttpKernel\KernelInterface $kernel */
$kernel = new $kernelClass('test', true);
$kernel->boot();

$container = $kernel->getContainer();

// Crée le schéma en SQLite
/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();

$metadata = $em->getMetadataFactory()->getAllMetadata();
if (!empty($metadata)) {
    $tool = new SchemaTool($em);
    $tool->dropSchema($metadata);
    $tool->createSchema($metadata);
}

$kernel->shutdown();